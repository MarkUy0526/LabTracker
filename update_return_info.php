<?php
require 'db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 1);

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['borrow_request_id'], $data['returned_items'])) {
    echo json_encode(["success" => false, "message" => "Invalid input"]);
    exit;
}

$borrowRequestId = $data['borrow_request_id'];

$updateStmt = $conn->prepare("
    UPDATE borrowed_equipment
    SET returned_on = IF(? = '', NULL, ?),
        remarks = IF(? = '', NULL, ?)
    WHERE borrow_request_id = ? AND equipment_name = ?
");

$getQtyStmt = $conn->prepare("
    SELECT quantity FROM borrowed_equipment WHERE borrow_request_id = ? AND equipment_name = ?
");

$updateAvailableStmt = $conn->prepare("
    UPDATE equipment
    SET available = available + ?
    WHERE equipment_name = ?
");

$updateRequestStatusStmt = $conn->prepare("
    UPDATE borrow_requests
    SET status = 'Not Returned'
    WHERE id = ?
");

if (!$updateStmt || !$getQtyStmt || !$updateAvailableStmt || !$updateRequestStatusStmt) {
    echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
    exit;
}

$hasNotReturnedItem = false;

foreach ($data['returned_items'] as $item) {
    $equipmentName = $item['equipment_name'];
    $returnedOn = $item['returned_on'];
    $remarks = $item['remarks'];
    $normalizedRemarks = strtolower(trim((string)$remarks));

    if (in_array($normalizedRemarks, ['lost', 'not working'], true)) {
        $hasNotReturnedItem = true;
    }

    $updateStmt->bind_param("ssssis",
        $returnedOn,
        $returnedOn,
        $remarks,
        $remarks,
        $borrowRequestId,
        $equipmentName
    );
    if (!$updateStmt->execute()) {
        echo json_encode(["success" => false, "message" => "Execute failed: " . $updateStmt->error]);
        exit;
    }

    $getQtyStmt->bind_param("is", $borrowRequestId, $equipmentName);
    $getQtyStmt->execute();
    $result = $getQtyStmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $qtyBorrowed = (int)$row['quantity'];

        $updateAvailableStmt->bind_param("is", $qtyBorrowed, $equipmentName);
        if (!$updateAvailableStmt->execute()) {
            echo json_encode(["success" => false, "message" => "Update available failed: " . $updateAvailableStmt->error]);
            exit;
        }
    }
}

if ($hasNotReturnedItem) {
    $updateRequestStatusStmt->bind_param("i", $borrowRequestId);
    if (!$updateRequestStatusStmt->execute()) {
        echo json_encode(["success" => false, "message" => "Request status update failed: " . $updateRequestStatusStmt->error]);
        exit;
    }
}

echo json_encode([
    "success" => true,
    "message" => "Return info and inventory updated successfully",
    "request_status" => $hasNotReturnedItem ? "Not Returned" : null
]);
