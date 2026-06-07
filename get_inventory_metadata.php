<?php
session_start();
require 'db.php';
require 'equipment_condition_helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

echo json_encode([
    'success' => true,
    'metadata' => getInventoryMetadata($conn),
]);
?>
