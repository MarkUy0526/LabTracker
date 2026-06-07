<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

require 'db.php';
require 'equipment_condition_helpers.php';

try {
  ensureAuditItemConditionColumns($conn);

  $data = json_decode(file_get_contents('php://input'), true);

  if (!isset($data['audit_id']) || !isset($data['items']) || !is_array($data['items'])) {
    throw new Exception('Missing required fields: audit_id and items');
  }

  $audit_id = (int)$data['audit_id'];
  $items = $data['items'];

  // Start transaction
  $conn->begin_transaction();

  // Delete existing items for this audit (or update if exists)
  foreach ($items as $item) {
    $equipment_id = $item['equipment_id'];
    $equipment_name = $item['equipment_name'];
    $expected_qty = (int)($item['previous_qty'] ?? $item['expected_qty'] ?? $item['actual_qty'] ?? 0);
    $actual_qty = (int)$item['actual_qty'];
    $expected_working_qty = max(0, (int)($item['previous_working_qty'] ?? $item['expected_working_qty'] ?? 0));
    $expected_not_working_qty = max(0, (int)($item['previous_not_working_qty'] ?? $item['expected_not_working_qty'] ?? 0));
    $expected_maintenance_qty = max(0, (int)($item['previous_maintenance_qty'] ?? $item['expected_maintenance_qty'] ?? 0));
    $actual_working_qty = max(0, (int)($item['actual_working_qty'] ?? 0));
    $actual_not_working_qty = max(0, (int)($item['actual_not_working_qty'] ?? 0));
    $actual_maintenance_qty = max(0, (int)($item['actual_maintenance_qty'] ?? 0));
    $status = $item['status'] ?? 'Complete';
    $damage_notes = $item['damage_notes'] ?? '';

    if ($expected_working_qty + $expected_not_working_qty + $expected_maintenance_qty === 0 && $expected_qty > 0) {
      [$expected_working_qty, $expected_not_working_qty, $expected_maintenance_qty] = allocateConditionQuantities($expected_qty, $expected_qty, 0, 0);
    }

    $hasActualConditions = array_key_exists('actual_working_qty', $item)
      || array_key_exists('actual_not_working_qty', $item)
      || array_key_exists('actual_maintenance_qty', $item);

    if ($hasActualConditions) {
      $actual_qty = $actual_working_qty + $actual_not_working_qty + $actual_maintenance_qty;
    } else {
      [$actual_working_qty, $actual_not_working_qty, $actual_maintenance_qty] = allocateConditionQuantities(
        $actual_qty,
        $expected_working_qty,
        $expected_not_working_qty,
        $expected_maintenance_qty
      );
    }

    // Check if item exists
    $check_stmt = $conn->prepare("
      SELECT id FROM audit_items
      WHERE audit_id = ? AND equipment_id = ?
    ");
    $check_stmt->bind_param("is", $audit_id, $equipment_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
      // Update existing
      $update_stmt = $conn->prepare("
        UPDATE audit_items
        SET expected_qty = ?,
            expected_working_qty = ?,
            expected_not_working_qty = ?,
            expected_maintenance_qty = ?,
            actual_qty = ?,
            actual_working_qty = ?,
            actual_not_working_qty = ?,
            actual_maintenance_qty = ?,
            status = ?,
            damage_notes = ?,
            updated_at = NOW()
        WHERE audit_id = ? AND equipment_id = ?
      ");
      $update_stmt->bind_param(
        "iiiiiiiissis",
        $expected_qty,
        $expected_working_qty,
        $expected_not_working_qty,
        $expected_maintenance_qty,
        $actual_qty,
        $actual_working_qty,
        $actual_not_working_qty,
        $actual_maintenance_qty,
        $status,
        $damage_notes,
        $audit_id,
        $equipment_id
      );
      $update_stmt->execute();
    } else {
      // Insert new
      $insert_stmt = $conn->prepare("
        INSERT INTO audit_items (
          audit_id, equipment_id, equipment_name,
          expected_qty, expected_working_qty, expected_not_working_qty, expected_maintenance_qty,
          actual_qty, actual_working_qty, actual_not_working_qty, actual_maintenance_qty,
          status, damage_notes, created_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
      ");
      $insert_stmt->bind_param(
        "issiiiiiiiiss",
        $audit_id,
        $equipment_id,
        $equipment_name,
        $expected_qty,
        $expected_working_qty,
        $expected_not_working_qty,
        $expected_maintenance_qty,
        $actual_qty,
        $actual_working_qty,
        $actual_not_working_qty,
        $actual_maintenance_qty,
        $status,
        $damage_notes
      );
      $insert_stmt->execute();
    }
  }

  $conn->commit();

  echo json_encode([
    'success' => true,
    'message' => 'Audit items saved successfully',
    'items_count' => count($items)
  ]);
} catch (Exception $e) {
  if ($conn->connect_errno) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
  } else {
    $conn->rollback();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
  }
}
?>
