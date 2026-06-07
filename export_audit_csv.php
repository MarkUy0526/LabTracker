<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
  http_response_code(401);
  echo 'Unauthorized';
  exit;
}

require 'db.php';
require 'equipment_condition_helpers.php';
require 'audit_snapshot_helpers.php';

function auditCsvQtyGroup(array $item, string $prefix): array
{
  return [
    (int)($item[$prefix . '_qty'] ?? 0),
    (int)($item[$prefix . '_working_qty'] ?? 0),
    (int)($item[$prefix . '_not_working_qty'] ?? 0),
    (int)($item[$prefix . '_maintenance_qty'] ?? 0),
  ];
}

function auditCsvChangeSummary(array $item): string
{
  $changes = [];
  $labels = [
    'T' => ['previous_qty', 'actual_qty'],
    'W' => ['previous_working_qty', 'actual_working_qty'],
    'NW' => ['previous_not_working_qty', 'actual_not_working_qty'],
    'M' => ['previous_maintenance_qty', 'actual_maintenance_qty'],
  ];

  foreach ($labels as $label => [$previousKey, $actualKey]) {
    $previous = (int)($item[$previousKey] ?? 0);
    $actual = (int)($item[$actualKey] ?? 0);
    if ($previous !== $actual) {
      $changes[] = "{$label}: {$previous} -> {$actual}";
    }
  }

  $previousStatus = $item['previous_status'] ?? 'Complete';
  $status = $item['status'] ?? 'Complete';
  if ($previousStatus !== $status) {
    $changes[] = "Status: {$previousStatus} -> {$status}";
  }

  $previousNotes = trim((string)($item['previous_notes'] ?? ''));
  $notes = trim((string)($item['damage_notes'] ?? ''));
  if ($previousNotes !== $notes) {
    $changes[] = 'Notes changed';
  }

  if (isset($item['previous_found']) && !$item['previous_found']) {
    $changes[] = 'No previous snapshot record';
  }

  return $changes ? implode('; ', $changes) : 'No change from previous report';
}

try {
  ensureAuditItemConditionColumns($conn);
  ensureInventoryAuditSnapshotTables($conn);

  $audit_id = isset($_GET['audit_id']) ? (int)$_GET['audit_id'] : null;

  if (!$audit_id) {
    throw new Exception('Missing audit_id parameter');
  }

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
  $snapshot = getAuditSnapshotByAuditId($conn, $audit_id);
  if ($snapshot) {
    markAuditSnapshotExported($conn, $audit_id, $_SESSION['username'] ?? $audit['admin_name']);
    $snapshot = getAuditSnapshotByAuditId($conn, $audit_id);
  }
  $items = [];

  if ($snapshot) {
    $currentItems = getAuditSnapshotItems($conn, (int)$snapshot['id']);
    $previousItems = !empty($snapshot['previous_snapshot_id'])
      ? getAuditSnapshotItems($conn, (int)$snapshot['previous_snapshot_id'])
      : [];
    $equipmentIds = array_unique(array_merge(array_keys($currentItems), array_keys($previousItems)));

    foreach ($equipmentIds as $equipmentId) {
      $current = $currentItems[$equipmentId] ?? null;
      $previous = $previousItems[$equipmentId] ?? null;
      $base = $current ?: $previous;
      $items[] = [
        'equipment_name' => $base['equipment_name'],
        'previous_found' => $previous !== null,
        'previous_qty' => $previous ? (int)$previous['total_qty'] : 0,
        'previous_working_qty' => $previous ? (int)$previous['working_qty'] : 0,
        'previous_not_working_qty' => $previous ? (int)$previous['not_working_qty'] : 0,
        'previous_maintenance_qty' => $previous ? (int)$previous['maintenance_qty'] : 0,
        'previous_status' => $previous['status'] ?? 'Complete',
        'previous_notes' => $previous['notes'] ?? '',
        'actual_qty' => $current ? (int)$current['total_qty'] : 0,
        'actual_working_qty' => $current ? (int)$current['working_qty'] : 0,
        'actual_not_working_qty' => $current ? (int)$current['not_working_qty'] : 0,
        'actual_maintenance_qty' => $current ? (int)$current['maintenance_qty'] : 0,
        'status' => $current['status'] ?? 'Missing',
        'damage_notes' => $current['notes'] ?? '',
      ];
    }
  } else {
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

    while ($row = $items_result->fetch_assoc()) {
      $previousQty = (int)$row['expected_qty'];
      $previousWorking = (int)$row['expected_working_qty'];
      $previousNotWorking = (int)$row['expected_not_working_qty'];
      $previousMaintenance = (int)$row['expected_maintenance_qty'];
      if ($previousQty > 0 && $previousWorking + $previousNotWorking + $previousMaintenance === 0) {
        [$previousWorking, $previousNotWorking, $previousMaintenance] = allocateConditionQuantities($previousQty, $previousQty, 0, 0);
      }

      $actualQty = (int)$row['actual_qty'];
      $actualWorking = (int)$row['actual_working_qty'];
      $actualNotWorking = (int)$row['actual_not_working_qty'];
      $actualMaintenance = (int)$row['actual_maintenance_qty'];
      if ($actualQty > 0 && $actualWorking + $actualNotWorking + $actualMaintenance === 0) {
        [$actualWorking, $actualNotWorking, $actualMaintenance] = allocateConditionQuantities($actualQty, $previousWorking, $previousNotWorking, $previousMaintenance);
      }

      $items[] = [
        'equipment_name' => $row['equipment_name'],
        'previous_found' => true,
        'previous_qty' => $previousQty,
        'previous_working_qty' => $previousWorking,
        'previous_not_working_qty' => $previousNotWorking,
        'previous_maintenance_qty' => $previousMaintenance,
        'previous_status' => 'Complete',
        'previous_notes' => '',
        'actual_qty' => $actualQty,
        'actual_working_qty' => $actualWorking,
        'actual_not_working_qty' => $actualNotWorking,
        'actual_maintenance_qty' => $actualMaintenance,
        'status' => $row['status'],
        'damage_notes' => $row['damage_notes'],
      ];
    }
  }

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="inventory-audit-' . $audit['audit_date'] . '.csv"');

  $output = fopen('php://output', 'w');
  fputcsv($output, ['Inventory Audit Snapshot Comparison Report']);
  fputcsv($output, []);
  fputcsv($output, ['Audit ID', $audit_id]);
  fputcsv($output, ['Audit Date', $audit['audit_date']]);
  fputcsv($output, ['Admin', $audit['admin_name']]);
  fputcsv($output, ['Snapshot ID', $snapshot['id'] ?? 'Legacy']);
  fputcsv($output, ['Snapshot Timestamp', $snapshot['snapshot_at'] ?? '']);
  fputcsv($output, ['Created By', $snapshot['created_by'] ?? $audit['admin_name']]);
  fputcsv($output, ['Exported By', $snapshot['exported_by'] ?? '']);
  fputcsv($output, ['Exported At', $snapshot['exported_at'] ?? '']);
  fputcsv($output, ['Total Items', $audit['total_items']]);
  fputcsv($output, ['Complete', $audit['complete_count']]);
  fputcsv($output, ['Missing', $audit['missing_count']]);
  fputcsv($output, ['Damaged', $audit['damaged_count']]);
  fputcsv($output, []);

  fputcsv($output, [
    'Equipment Name',
    'Previous - T', 'Previous - W', 'Previous - NW', 'Previous - M',
    'New - T', 'New - W', 'New - NW', 'New - M',
    'Status', 'Change Summary', 'Notes'
  ]);

  foreach ($items as $item) {
    fputcsv($output, [
      $item['equipment_name'],
      ...auditCsvQtyGroup($item, 'previous'),
      ...auditCsvQtyGroup($item, 'actual'),
      $item['status'],
      auditCsvChangeSummary($item),
      $item['damage_notes']
    ]);
  }

  fputcsv($output, []);
  fputcsv($output, ['Signatories']);
  fputcsv($output, ['_____________________', '_____________________']);
  fputcsv($output, ['Mr. Lester D. Bernardino', 'Mr. Hiromi Rivas']);
  fputcsv($output, ['Chairperson', 'Applied Physics Professor']);

  fclose($output);
  exit;
} catch (Exception $e) {
  http_response_code(400);
  echo 'Error: ' . $e->getMessage();
}
?>
