<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

require 'db.php';
require 'audit_snapshot_helpers.php';

try {
  $data = json_decode(file_get_contents('php://input'), true);

  if (!isset($data['audit_id'])) {
    throw new Exception('Missing audit_id');
  }

  $audit_id = (int)$data['audit_id'];
  $created_by = $_SESSION['username'] ?? ($_SESSION['admin_name'] ?? 'Admin');

  $conn->begin_transaction();
  ensureInventoryAuditSnapshotTables($conn);

  // Get counts from audit_items
  $count_stmt = $conn->prepare("
    SELECT
      COUNT(*) as total_items,
      SUM(CASE WHEN status = 'Complete' THEN 1 ELSE 0 END) as complete_count,
      SUM(CASE WHEN status = 'Missing' THEN 1 ELSE 0 END) as missing_count,
      SUM(CASE WHEN status = 'Damaged' THEN 1 ELSE 0 END) as damaged_count
    FROM audit_items
    WHERE audit_id = ?
  ");
  $count_stmt->bind_param("i", $audit_id);
  $count_stmt->execute();
  $count_result = $count_stmt->get_result();
  $counts = $count_result->fetch_assoc();

  // Update audit with counts and set status to Submitted
  $update_stmt = $conn->prepare("
    UPDATE inventory_audits
    SET
      status = 'Submitted',
      total_items = ?,
      complete_count = ?,
      missing_count = ?,
      damaged_count = ?,
      submitted_at = NOW()
    WHERE id = ?
  ");
  $update_stmt->bind_param(
    "iiiii",
    $counts['total_items'],
    $counts['complete_count'],
    $counts['missing_count'],
    $counts['damaged_count'],
    $audit_id
  );
  $update_stmt->execute();

  $snapshot_id = captureAuditSnapshot($conn, $audit_id, $created_by);

  $conn->commit();

  echo json_encode([
    'success' => true,
    'message' => 'Audit submitted successfully',
    'snapshot_id' => $snapshot_id,
    'summary' => [
      'total_items' => (int)$counts['total_items'],
      'complete_count' => (int)$counts['complete_count'],
      'missing_count' => (int)$counts['missing_count'],
      'damaged_count' => (int)$counts['damaged_count']
    ]
  ]);
} catch (Exception $e) {
  if ($conn->connect_errno) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
  } else {
    $conn->rollback();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
  }
}
?>
