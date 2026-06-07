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
  $audit_date = $_POST['audit_date'] ?? date('Y-m-d');
  $admin_name = $_POST['admin_name'] ?? ($_SESSION['admin_name'] ?? 'Admin');

  $stmt = $conn->prepare("
    INSERT INTO inventory_audits (audit_date, admin_name, status, created_at)
    VALUES (?, ?, 'Draft', NOW())
  ");

  $stmt->bind_param("ss", $audit_date, $admin_name);

  if ($stmt->execute()) {
    $audit_id = $stmt->insert_id;
    echo json_encode([
      'success' => true,
      'message' => 'Audit created successfully',
      'audit_id' => $audit_id,
      'audit_date' => $audit_date,
      'admin_name' => $admin_name
    ]);
  } else {
    throw new Exception($stmt->error);
  }
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
