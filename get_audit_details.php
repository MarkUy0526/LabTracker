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
  $audit_id = isset($_GET['audit_id']) ? (int)$_GET['audit_id'] : null;

  if (!$audit_id) {
    throw new Exception('Missing audit_id parameter');
  }

  // Get audit header
  $audit_stmt = $conn->prepare("
    SELECT id, audit_date, admin_name, status, total_items, complete_count, missing_count, damaged_count
    FROM inventory_audits
    WHERE id = ?
  ");
  $audit_stmt->bind_param("i", $audit_id);
  $audit_stmt->execute();
  $audit_result = $audit_stmt->get_result();

  if ($audit_result->num_rows === 0) {
    throw new Exception('Audit not found');
  }

  $audit = $audit_result->fetch_assoc();

  // Get audit items
  $items_stmt = $conn->prepare("
    SELECT
      id,
      equipment_id,
      equipment_name,
      expected_qty,
      actual_qty,
      status,
      damage_notes
    FROM audit_items
    WHERE audit_id = ?
    ORDER BY equipment_name ASC
  ");
  $items_stmt->bind_param("i", $audit_id);
  $items_stmt->execute();
  $items_result = $items_stmt->get_result();

  $items = [];
  while ($row = $items_result->fetch_assoc()) {
    $items[] = [
      'id' => (int)$row['id'],
      'equipment_id' => $row['equipment_id'],
      'equipment_name' => $row['equipment_name'],
      'expected_qty' => (int)$row['expected_qty'],
      'actual_qty' => (int)$row['actual_qty'],
      'status' => $row['status'],
      'damage_notes' => $row['damage_notes']
    ];
  }

  echo json_encode([
    'success' => true,
    'audit' => [
      'id' => (int)$audit['id'],
      'audit_date' => $audit['audit_date'],
      'admin_name' => $audit['admin_name'],
      'status' => $audit['status'],
      'total_items' => (int)$audit['total_items'],
      'complete_count' => (int)$audit['complete_count'],
      'missing_count' => (int)$audit['missing_count'],
      'damaged_count' => (int)$audit['damaged_count']
    ],
    'items' => $items
  ]);
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
