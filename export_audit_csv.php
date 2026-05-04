<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
  http_response_code(401);
  echo 'Unauthorized';
  exit;
}

require 'db.php';
require 'equipment_condition_helpers.php';

try {
  ensureAuditItemConditionColumns($conn);

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
    SELECT
      equipment_name,
      expected_qty,
      expected_working_qty,
      expected_not_working_qty,
      expected_maintenance_qty,
      actual_qty,
      actual_working_qty,
      actual_not_working_qty,
      actual_maintenance_qty,
      status,
      damage_notes
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
  fputcsv($output, ['Equipment Name', 'Expected T', 'Expected W', 'Expected NW', 'Expected M', 'Actual T', 'Actual W', 'Actual NW', 'Actual M', 'Status', 'Damage Notes']);

  // Write items
  while ($row = $items_result->fetch_assoc()) {
    $expectedQty = (int)$row['expected_qty'];
    $expectedWorking = (int)$row['expected_working_qty'];
    $expectedNotWorking = (int)$row['expected_not_working_qty'];
    $expectedMaintenance = (int)$row['expected_maintenance_qty'];
    if ($expectedQty > 0 && $expectedWorking + $expectedNotWorking + $expectedMaintenance === 0) {
      [$expectedWorking, $expectedNotWorking, $expectedMaintenance] = allocateConditionQuantities($expectedQty, $expectedQty, 0, 0);
    }

    $actualQty = (int)$row['actual_qty'];
    $actualWorking = (int)$row['actual_working_qty'];
    $actualNotWorking = (int)$row['actual_not_working_qty'];
    $actualMaintenance = (int)$row['actual_maintenance_qty'];
    if ($actualQty > 0 && $actualWorking + $actualNotWorking + $actualMaintenance === 0) {
      [$actualWorking, $actualNotWorking, $actualMaintenance] = allocateConditionQuantities($actualQty, $expectedWorking, $expectedNotWorking, $expectedMaintenance);
    }

    fputcsv($output, [
      $row['equipment_name'],
      $expectedQty,
      $expectedWorking,
      $expectedNotWorking,
      $expectedMaintenance,
      $actualQty,
      $actualWorking,
      $actualNotWorking,
      $actualMaintenance,
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
