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

  $query = "
    SELECT
      be.equipment_name,
      COUNT(br.id) as borrow_frequency,
      SUM(be.quantity) as total_qty_borrowed
    FROM borrow_requests br
    JOIN borrowed_equipment be ON br.id = be.borrow_request_id
    WHERE br.status = 'Accepted'
      AND br.date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY be.equipment_name
    ORDER BY borrow_frequency DESC, total_qty_borrowed DESC
    LIMIT 10
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
      'equipment_name' => $row['equipment_name'],
      'borrow_frequency' => (int)$row['borrow_frequency'],
      'total_qty_borrowed' => (int)$row['total_qty_borrowed']
    ];
  }

  echo json_encode([
    'success' => true,
    'data' => $equipment,
    'count' => count($equipment),
    'period' => 'Last 6 months'
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
