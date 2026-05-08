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
require 'audit_snapshot_helpers.php';

try {
  ensureEquipmentMaintenanceColumn($conn);
  ensureAuditItemConditionColumns($conn);
  ensureEquipmentInventoryControlColumns($conn);
  ensureInventoryAuditSnapshotTables($conn);

  $previousSnapshot = getLatestAuditSnapshot($conn);
  $previousItems = $previousSnapshot ? getAuditSnapshotItems($conn, (int)$previousSnapshot['id']) : [];

  $query = "
    SELECT equipment_id, equipment_name, serial_number, internal_sn, total_qty, working_qty, not_working_qty, maintenance_qty, account_person, description
    FROM equipment
    ORDER BY equipment_name ASC
  ";

  $result = $conn->query($query);

  if (!$result) {
    throw new Exception($conn->error);
  }

  $equipment = [];
  while ($row = $result->fetch_assoc()) {
    $previous = $previousItems[$row['equipment_id']] ?? null;
    $previousTotal = $previous ? (int)$previous['total_qty'] : (int)$row['total_qty'];
    $previousWorking = $previous ? (int)$previous['working_qty'] : (int)$row['working_qty'];
    $previousNotWorking = $previous ? (int)$previous['not_working_qty'] : (int)$row['not_working_qty'];
    $previousMaintenance = $previous ? (int)$previous['maintenance_qty'] : (int)$row['maintenance_qty'];

    $equipment[] = [
      'equipment_id' => $row['equipment_id'],
      'equipment_name' => $row['equipment_name'],
      'serial_number' => $row['serial_number'],
      'internal_sn' => $row['internal_sn'],
      'account_person' => $row['account_person'],
      'description' => $row['description'],
      'previous_snapshot_id' => $previousSnapshot ? (int)$previousSnapshot['id'] : null,
      'previous_audit_id' => $previousSnapshot ? (int)$previousSnapshot['audit_id'] : null,
      'previous_snapshot_at' => $previousSnapshot['snapshot_at'] ?? null,
      'previous_found' => $previous !== null,
      'previous_qty' => $previousTotal,
      'previous_working_qty' => $previousWorking,
      'previous_not_working_qty' => $previousNotWorking,
      'previous_maintenance_qty' => $previousMaintenance,
      'previous_account_person' => $previous['account_person'] ?? $row['account_person'],
      'previous_description' => $previous['description'] ?? $row['description'],
      'previous_status' => $previous['status'] ?? 'Complete',
      'previous_notes' => $previous['notes'] ?? '',
      'actual_qty' => (int)$row['total_qty'],
      'actual_working_qty' => (int)$row['working_qty'],
      'actual_not_working_qty' => (int)$row['not_working_qty'],
      'actual_maintenance_qty' => (int)$row['maintenance_qty'],
      'status' => 'Complete',
      'damage_notes' => ''
    ];
  }

  echo json_encode([
    'success' => true,
    'data' => $equipment,
    'count' => count($equipment),
    'previous_snapshot' => $previousSnapshot ? [
      'id' => (int)$previousSnapshot['id'],
      'audit_id' => (int)$previousSnapshot['audit_id'],
      'snapshot_at' => $previousSnapshot['snapshot_at'],
      'created_by' => $previousSnapshot['created_by'],
      'item_count' => (int)$previousSnapshot['item_count']
    ] : null
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
