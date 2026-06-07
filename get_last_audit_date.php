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

  // Get last submitted audit
  $stmt = $conn->prepare("
    SELECT audit_date, submitted_at
    FROM inventory_audits
    WHERE status IN ('Submitted', 'Completed')
    ORDER BY audit_date DESC
    LIMIT 1
  ");
  $stmt->execute();
  $result = $stmt->get_result();

  $last_audit_date = null;
  $next_scheduled_date = null;

  if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $last_audit_date = $row['audit_date'];

    // Calculate next audit: 6 months from last
    $last_date = new DateTime($last_audit_date);
    $next_date = $last_date->add(new DateInterval('P6M'));
    $next_scheduled_date = $next_date->format('Y-m-d');
  }

  echo json_encode([
    'success' => true,
    'last_audit_date' => $last_audit_date,
    'next_scheduled_date' => $next_scheduled_date,
    'current_date' => date('Y-m-d')
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
