<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

require 'db.php';

try {
  $data = json_decode(file_get_contents('php://input'), true);

  if (!isset($data['audit_id'])) {
    throw new Exception('Missing audit_id');
  }

  $audit_id = (int)$data['audit_id'];

  // Get all audit items
  $items_stmt = $conn->prepare("
    SELECT ai.equipment_id, ai.actual_qty, ai.status
    FROM audit_items ai
    WHERE ai.audit_id = ?
  ");
  $items_stmt->bind_param("i", $audit_id);
  $items_stmt->execute();
  $items_result = $items_stmt->get_result();

  $updated_count = 0;
  $conn->begin_transaction();

  while ($item = $items_result->fetch_assoc()) {
    $equipment_id = $item['equipment_id'];
    $actual_qty = (int)$item['actual_qty'];
    $status = $item['status'];

    // Get current equipment data to maintain proportions
    $eq_stmt = $conn->prepare("
      SELECT total_qty, working_qty, not_working_qty, maintenance_qty
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

      // Calculate new quantities maintaining proportions
      $new_working = 0;
      $new_not_working = 0;
      $new_maintenance = 0;

      if ($old_total > 0) {
        $working_ratio = $old_working / $old_total;
        $not_working_ratio = $old_not_working / $old_total;
        $maintenance_ratio = $old_maintenance / $old_total;

        $new_working = (int)round($actual_qty * $working_ratio);
        $new_not_working = (int)round($actual_qty * $not_working_ratio);
        $new_maintenance = (int)round($actual_qty * $maintenance_ratio);

        // Ensure total matches exactly
        $calc_total = $new_working + $new_not_working + $new_maintenance;
        $diff = $actual_qty - $calc_total;
        if ($diff > 0) {
          $new_working += $diff;
        } elseif ($diff < 0) {
          if ($new_not_working >= abs($diff)) {
            $new_not_working += $diff;
          } else {
            $new_working += $diff;
          }
        }
      } else {
        // If old total is 0, just set working
        $new_working = $actual_qty;
      }

      // Update equipment with new quantities
      $update_stmt = $conn->prepare("
        UPDATE equipment
        SET total_qty = ?, working_qty = ?, not_working_qty = ?, maintenance_qty = ?
        WHERE equipment_id = ?
      ");
      $update_stmt->bind_param("iiiss", $actual_qty, $new_working, $new_not_working, $new_maintenance, $equipment_id);

      if ($update_stmt->execute()) {
        $updated_count++;
      }
    }
  }

  $conn->commit();

  echo json_encode([
    'success' => true,
    'message' => 'Inventory updated successfully',
    'updated_count' => $updated_count
  ]);
} catch (Exception $e) {
  if ($conn->connect_errno) {
    $conn->rollback();
  }
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
