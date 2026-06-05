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
  date_default_timezone_set('Asia/Manila');

  // Get all_equipment parameter to include never-borrowed items
  $allEquipment = isset($_GET['all_equipment']) && $_GET['all_equipment'] === '1';
  $limit = $allEquipment ? '' : 'LIMIT 10';

  $query = "
    SELECT
      eq.equipment_id,
      eq.equipment_name,
      COUNT(CASE WHEN br.status='Accepted' THEN br.id END) as borrow_frequency,
      SUM(CASE WHEN br.status='Accepted' THEN be.quantity ELSE 0 END) as total_qty_borrowed,
      MAX(CASE WHEN br.status='Accepted' THEN br.date ELSE NULL END) as last_borrow_date,
      eq.total_qty,
      eq.available
    FROM equipment eq
    LEFT JOIN borrowed_equipment be ON eq.equipment_name = be.equipment_name
    LEFT JOIN borrow_requests br ON be.borrow_request_id = br.id
      AND br.status='Accepted'
      AND br.date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY eq.equipment_id, eq.equipment_name, eq.total_qty, eq.available
    ORDER BY borrow_frequency DESC, total_qty_borrowed DESC
    $limit
  ";

  $result = $conn->query($query);

  if (!$result) {
    throw new Exception($conn->error);
  }

  $equipment = [];
  $rank = 1;
  while ($row = $result->fetch_assoc()) {
    $equipment[] = [
      'rank' => $rank++,
      'equipment_id' => $row['equipment_id'],
      'equipment_name' => $row['equipment_name'],
      'borrow_frequency' => (int)$row['borrow_frequency'],
      'total_qty_borrowed' => (int)($row['total_qty_borrowed'] ?? 0),
      'last_borrow_date' => $row['last_borrow_date'],
      'total_qty' => (int)$row['total_qty'],
      'available' => (int)$row['available']
    ];
  }

  echo json_encode([
    'success' => true,
    'data' => $equipment,
    'count' => count($equipment),
    'period' => 'Last 6 months',
    'includes_all_equipment' => $allEquipment
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

