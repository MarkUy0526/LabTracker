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
      e.equipment_id,
      e.equipment_name,
      e.total_qty,
      e.available,
      COALESCE(stats.borrow_frequency, 0) AS borrow_frequency,
      COALESCE(stats.total_qty_borrowed, 0) AS total_qty_borrowed,
      stats.last_borrow_date
    FROM equipment e
    LEFT JOIN (
      SELECT
        be.equipment_name,
        COUNT(br.id) AS borrow_frequency,
        COALESCE(SUM(be.quantity), 0) AS total_qty_borrowed,
        MAX(br.date) AS last_borrow_date
      FROM borrow_requests br
      JOIN borrowed_equipment be ON br.id = be.borrow_request_id
      WHERE br.status = 'Approved'
        AND br.date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
      GROUP BY be.equipment_name
    ) stats ON stats.equipment_name = e.equipment_name
    ORDER BY borrow_frequency DESC, total_qty_borrowed DESC, e.equipment_name ASC
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
      'total_qty_borrowed' => (int)$row['total_qty_borrowed'],
      'total_inventory_count' => (int)$row['total_qty'],
      'current_availability' => (int)$row['available'],
      'last_borrow_date' => $row['last_borrow_date']
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
