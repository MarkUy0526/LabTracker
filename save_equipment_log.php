<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

// Only logged-in admins can log equipment
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$equipment_id    = trim($data['equipment_id']    ?? '');
$equipment_name  = trim($data['equipment_name']  ?? '');
$total_qty       = intval($data['total_qty']      ?? 0);
$working_qty     = intval($data['working_qty']    ?? 0);
$not_working_qty = intval($data['not_working_qty'] ?? 0);
$account_person  = trim($data['account_person']  ?? '');
$action          = trim($data['action']           ?? 'Added');
$added_by        = $_SESSION['username'] ?? 'Admin';

// PH time (Asia/Manila = UTC+8)
$tz = new DateTimeZone('Asia/Manila');
$now = new DateTime('now', $tz);
$added_at_ph = $now->format('Y-m-d H:i:s');

if (!$equipment_id || !$equipment_name) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO equipment_log
        (equipment_id, equipment_name, total_qty, working_qty, not_working_qty, account_person, action, added_by, added_at_ph)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param(
    'ssiiiisss',
    $equipment_id,
    $equipment_name,
    $total_qty,
    $working_qty,
    $not_working_qty,
    $account_person,
    $action,
    $added_by,
    $added_at_ph
);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'added_at_ph' => $added_at_ph]);
} else {
    echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
