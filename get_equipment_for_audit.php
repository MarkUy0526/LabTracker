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
  ensureEquipmentMaintenanceColumn($conn);
  ensureAuditItemConditionColumns($conn);

  $query = "
    SELECT equipment_id, equipment_name, total_qty, working_qty, not_working_qty, maintenance_qty, account_person, description
    FROM equipment
    ORDER BY equipment_name ASC
  ";

  $result = $conn->query($query);

  if (!$result) {
    throw new Exception($conn->error);
  }

  $equipment = [];
  while ($row = $result->fetch_assoc()) {
    $equipment[] = [
      'equipment_id' => $row['equipment_id'],
      'equipment_name' => $row['equipment_name'],
      'expected_qty' => (int)$row['total_qty'],
      'expected_working_qty' => (int)$row['working_qty'],
      'expected_not_working_qty' => (int)$row['not_working_qty'],
      'expected_maintenance_qty' => (int)$row['maintenance_qty'],
      'account_person' => $row['account_person'],
      'description' => $row['description'],
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
    'count' => count($equipment)
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
