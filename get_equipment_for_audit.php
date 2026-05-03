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
  $query = "
    SELECT equipment_id, equipment_name, total_qty, account_person, description
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
      'account_person' => $row['account_person'],
      'description' => $row['description'],
      'actual_qty' => 0,
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
