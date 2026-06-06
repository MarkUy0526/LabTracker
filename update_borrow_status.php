<?php
require 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $status = $_POST['status'] ?? null;

    if (!$id || !$status) {
        echo json_encode(['success' => false, 'message' => 'Missing ID or status.']);
        exit;
    }

    // Sanitize inputs
    $id = (int)$id;
    $status = trim($status);

    // Check if record exists
    $checkStmt = $conn->prepare("SELECT id FROM borrow_requests WHERE id = ?");
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Request not found.']);
        exit;
    }

    // Update the status
    $stmt = $conn->prepare("UPDATE borrow_requests SET status = ? WHERE id = ?");

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param("si", $status, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'affectedRows' => $stmt->affected_rows]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status: ' . $stmt->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
