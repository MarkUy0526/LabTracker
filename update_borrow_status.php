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

    if (!in_array($status, ['Approved', 'Denied', 'Pending'], true)) {
        echo json_encode(['success' => false, 'message' => "Invalid request status '$status'. Allowed statuses are Approved, Denied, and Pending."]);
        exit;
    }

    // Check if record exists
    $checkStmt = $conn->prepare("SELECT id, status FROM borrow_requests WHERE id = ?");
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Request not found.']);
        exit;
    }
    $request = $checkResult->fetch_assoc();
    if (($request['status'] ?? '') !== 'Pending') {
        echo json_encode(['success' => false, 'message' => "This request has already been marked as {$request['status']}. Refresh the Borrow Requests list before continuing."]);
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
        if ($stmt->affected_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'No request was updated. The selected request may already have the same status.']);
            exit;
        }
        echo json_encode(['success' => true, 'affectedRows' => $stmt->affected_rows]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status: ' . $stmt->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
