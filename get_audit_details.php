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
require 'audit_snapshot_helpers.php';

try {
  ensureAuditItemConditionColumns($conn);
  ensureInventoryAuditSnapshotTables($conn);

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

  $snapshot = getAuditSnapshotByAuditId($conn, $audit_id);
  $previousSnapshot = null;

  if ($snapshot) {
    $currentItems = getAuditSnapshotItems($conn, (int)$snapshot['id']);
    $previousItems = [];
    if (!empty($snapshot['previous_snapshot_id'])) {
      $previousItems = getAuditSnapshotItems($conn, (int)$snapshot['previous_snapshot_id']);
      $previousSnapshot = [
        'id' => (int)$snapshot['previous_snapshot_id']
      ];
    }

    $equipmentIds = array_unique(array_merge(array_keys($currentItems), array_keys($previousItems)));
    $items = [];
    foreach ($equipmentIds as $equipmentId) {
      $current = $currentItems[$equipmentId] ?? null;
      $previous = $previousItems[$equipmentId] ?? null;
      $base = $current ?: $previous;
      $actualQty = $current ? (int)$current['total_qty'] : 0;
      $actualWorking = $current ? (int)$current['working_qty'] : 0;
      $actualNotWorking = $current ? (int)$current['not_working_qty'] : 0;
      $actualMaintenance = $current ? (int)$current['maintenance_qty'] : 0;
      $previousQty = $previous ? (int)$previous['total_qty'] : 0;
      $previousWorking = $previous ? (int)$previous['working_qty'] : 0;
      $previousNotWorking = $previous ? (int)$previous['not_working_qty'] : 0;
      $previousMaintenance = $previous ? (int)$previous['maintenance_qty'] : 0;

      $items[] = [
        'equipment_id' => $equipmentId,
        'equipment_name' => $base['equipment_name'],
        'previous_found' => $previous !== null,
        'previous_qty' => $previousQty,
        'previous_working_qty' => $previousWorking,
        'previous_not_working_qty' => $previousNotWorking,
        'previous_maintenance_qty' => $previousMaintenance,
        'previous_status' => $previous['status'] ?? 'Complete',
        'previous_notes' => $previous['notes'] ?? '',
        'actual_qty' => $actualQty,
        'actual_working_qty' => $actualWorking,
        'actual_not_working_qty' => $actualNotWorking,
        'actual_maintenance_qty' => $actualMaintenance,
        'status' => $current['status'] ?? 'Missing',
        'damage_notes' => $current['notes'] ?? '',
        'account_person' => $current['account_person'] ?? $previous['account_person'] ?? '',
        'description' => $current['description'] ?? $previous['description'] ?? '',
      ];
    }
  } else {
    // Legacy audits created before snapshots existed.
    $items_stmt = $conn->prepare("
    SELECT
      id,
      equipment_id,
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

    $items = [];
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

      $items[] = [
        'id' => (int)$row['id'],
        'equipment_id' => $row['equipment_id'],
        'equipment_name' => $row['equipment_name'],
        'previous_found' => true,
        'previous_qty' => $expectedQty,
        'previous_working_qty' => $expectedWorking,
        'previous_not_working_qty' => $expectedNotWorking,
        'previous_maintenance_qty' => $expectedMaintenance,
        'previous_status' => 'Complete',
        'previous_notes' => '',
        'actual_qty' => $actualQty,
        'actual_working_qty' => $actualWorking,
        'actual_not_working_qty' => $actualNotWorking,
        'actual_maintenance_qty' => $actualMaintenance,
        'status' => $row['status'],
        'damage_notes' => $row['damage_notes']
      ];
    }
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
    'snapshot' => $snapshot ? [
      'id' => (int)$snapshot['id'],
      'audit_id' => (int)$snapshot['audit_id'],
      'snapshot_at' => $snapshot['snapshot_at'],
      'created_by' => $snapshot['created_by'],
      'exported_by' => $snapshot['exported_by'] ?? null,
      'exported_at' => $snapshot['exported_at'] ?? null,
      'previous_snapshot_id' => $snapshot['previous_snapshot_id'] ? (int)$snapshot['previous_snapshot_id'] : null,
      'item_count' => (int)$snapshot['item_count']
    ] : null,
    'previous_snapshot' => $previousSnapshot,
    'items' => $items
  ]);
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
