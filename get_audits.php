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
    SELECT
      id,
      audit_date,
      admin_name,
      status,
      total_items,
      complete_count,
      missing_count,
      damaged_count,
      submitted_at,
      created_at
    FROM inventory_audits
    WHERE status IN ('Submitted', 'Completed')
    ORDER BY audit_date DESC
    LIMIT 100
  ";

  $result = $conn->query($query);

  if (!$result) {
    throw new Exception($conn->error);
  }

  $audits = [];
  while ($row = $result->fetch_assoc()) {
    $audits[] = [
      'id' => (int)$row['id'],
      'audit_date' => $row['audit_date'],
      'admin_name' => $row['admin_name'],
      'status' => $row['status'],
      'total_items' => (int)$row['total_items'],
      'complete_count' => (int)$row['complete_count'],
      'missing_count' => (int)$row['missing_count'],
      'damaged_count' => (int)$row['damaged_count'],
      'submitted_at' => $row['submitted_at']
    ];
  }

  echo json_encode([
    'success' => true,
    'data' => $audits,
    'count' => count($audits)
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
