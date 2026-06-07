<?php
header('Content-Type: application/json');
session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

require 'db.php';
require 'equipment_condition_helpers.php';

try {
  ensureEquipmentMaintenanceColumn($conn);
  ensureEquipmentInventoryControlColumns($conn);
  ensureInventoryMetadataTable($conn);
  ensureAuditItemConditionColumns($conn);

  $data = json_decode(file_get_contents('php://input'), true);

  if (!isset($data['audit_id'])) {
    throw new Exception('Missing audit_id');
  }

  $audit_id = (int)$data['audit_id'];

  // Get all audit items
  $items_stmt = $conn->prepare("
    SELECT
      ai.equipment_id,
      ai.actual_qty,
      ai.actual_working_qty,
      ai.actual_not_working_qty,
      ai.actual_maintenance_qty,
      ai.status
    FROM audit_items ai
    WHERE ai.audit_id = ?
  ");
  $items_stmt->bind_param("i", $audit_id);
  $items_stmt->execute();
  $items_result = $items_stmt->get_result();

  $updated_count = 0;
  $edited_at = date('Y-m-d H:i:s');
  $conn->begin_transaction();

  while ($item = $items_result->fetch_assoc()) {
    $equipment_id = $item['equipment_id'];
    $actual_qty = (int)$item['actual_qty'];
    $actual_working = max(0, (int)$item['actual_working_qty']);
    $actual_not_working = max(0, (int)$item['actual_not_working_qty']);
    $actual_maintenance = max(0, (int)$item['actual_maintenance_qty']);
    $status = $item['status'];

    // Get current equipment data for fallback allocation and available recalculation.
    $eq_stmt = $conn->prepare("
      SELECT total_qty, working_qty, not_working_qty, maintenance_qty, available
      FROM equipment
      WHERE equipment_id = ?
    ");
    $eq_stmt->bind_param("s", $equipment_id);
    $eq_stmt->execute();
    $eq_result = $eq_stmt->get_result();

    if ($eq_result->num_rows > 0) {
      $eq = $eq_result->fetch_assoc();
      $old_total = (int)$eq['total_qty'];
      $old_working = (int)$eq['working_qty'];
      $old_not_working = (int)$eq['not_working_qty'];
      $old_maintenance = (int)$eq['maintenance_qty'];
      $old_available = (int)$eq['available'];

      if ($actual_working + $actual_not_working + $actual_maintenance > 0 || $actual_qty === 0) {
        $new_working = $actual_working;
        $new_not_working = $actual_not_working;
        $new_maintenance = $actual_maintenance;
        $actual_qty = $new_working + $new_not_working + $new_maintenance;
      } else {
        [$new_working, $new_not_working, $new_maintenance] = allocateConditionQuantities(
          $actual_qty,
          $old_working,
          $old_not_working,
          $old_maintenance
        );
      }

      $borrowed_qty = max(0, $old_working - $old_available);
      if ($new_working < $borrowed_qty) {
        throw new Exception("Working quantity for {$equipment_id} cannot be lower than currently borrowed quantity ({$borrowed_qty}).");
      }
      $new_available = $new_working - $borrowed_qty;

      // Update equipment with new quantities
      $update_stmt = $conn->prepare("
        UPDATE equipment
        SET total_qty = ?, working_qty = ?, not_working_qty = ?, maintenance_qty = ?, available = ?, last_edited_at = ?
        WHERE equipment_id = ?
      ");
      $update_stmt->bind_param("iiiiiss", $actual_qty, $new_working, $new_not_working, $new_maintenance, $new_available, $edited_at, $equipment_id);

      if ($update_stmt->execute()) {
        $updated_count++;
      }
    }
  }

  $conn->commit();
  if ($updated_count > 0) {
    setInventoryMetadata($conn, 'last_edited_at', $edited_at);
  }

  echo json_encode([
    'success' => true,
    'message' => 'Inventory updated successfully',
    'updated_count' => $updated_count
  ]);
} catch (Exception $e) {
  if (isset($conn) && !$conn->connect_errno) {
    $conn->rollback();
  }
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
