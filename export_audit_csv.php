<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
  http_response_code(401);
  echo 'Unauthorized';
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
    SELECT audit_date, admin_name, total_items, complete_count, missing_count, damaged_count
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
    SELECT equipment_name, expected_qty, actual_qty, status, damage_notes
    FROM audit_items
    WHERE audit_id = ?
    ORDER BY equipment_name ASC
  ");
  $items_stmt->bind_param("i", $audit_id);
  $items_stmt->execute();
  $items_result = $items_stmt->get_result();

  // Set headers for CSV download
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="inventory-audit-' . $audit['audit_date'] . '.csv"');

  // Create output
  $output = fopen('php://output', 'w');

  // Write audit summary
  fputcsv($output, ['Inventory Audit Report']);
  fputcsv($output, []);
  fputcsv($output, ['Audit Date', $audit['audit_date']]);
  fputcsv($output, ['Admin', $audit['admin_name']]);
  fputcsv($output, ['Total Items', $audit['total_items']]);
  fputcsv($output, ['Complete', $audit['complete_count']]);
  fputcsv($output, ['Missing', $audit['missing_count']]);
  fputcsv($output, ['Damaged', $audit['damaged_count']]);
  fputcsv($output, []);

  // Write items table header
  fputcsv($output, ['Equipment Name', 'Expected Qty', 'Actual Qty', 'Status', 'Damage Notes']);

  // Write items
  while ($row = $items_result->fetch_assoc()) {
    fputcsv($output, [
      $row['equipment_name'],
      $row['expected_qty'],
      $row['actual_qty'],
      $row['status'],
      $row['damage_notes']
    ]);
  }

  fclose($output);
  exit;
} catch (Exception $e) {
  http_response_code(400);
  echo 'Error: ' . $e->getMessage();
}
?>
