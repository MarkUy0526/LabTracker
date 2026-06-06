<?php
require 'db.php';
require 'return_photo_helpers.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

ensureReturnPhotoColumns($conn);

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['borrow_request_id'], $data['returned_items'])) {
    echo json_encode(["success" => false, "message" => "Invalid input"]);
    exit;
}

$borrowRequestId = $data['borrow_request_id'];
$verificationStatus = normalizeReturnVerificationStatus($data['verification_status'] ?? 'Pending Verification');
$verificationNotes = trim($data['verification_notes'] ?? '');

$requestStmt = $conn->prepare("SELECT return_verification_status, return_inventory_restored, usage_date FROM borrow_requests WHERE id = ?");
$requestStmt->bind_param("i", $borrowRequestId);
$requestStmt->execute();
$request = $requestStmt->get_result()->fetch_assoc();

if (!$request) {
    echo json_encode(["success" => false, "message" => "Borrow request not found"]);
    exit;
}

$inventoryAlreadyRestored = (int)($request['return_inventory_restored'] ?? 0) === 1;
$allSubmittedItemsReturnedCleanly = true;
foreach ($data['returned_items'] as $item) {
    $returnedOn = trim((string)($item['returned_on'] ?? ''));
    $remarks = (string)($item['remarks'] ?? '');
    if ($returnedOn === '' || remarksIndicateReturnIssue($remarks)) {
        $allSubmittedItemsReturnedCleanly = false;
        break;
    }
}
$shouldRestoreInventory = $verificationStatus === 'Verified' && !$inventoryAlreadyRestored && $allSubmittedItemsReturnedCleanly;

$conn->begin_transaction();

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

if (!$updateStmt || !$getQtyStmt || !$updateAvailableStmt) {
    echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
    exit;
}

try {
    $submittedReturnItems = [];
    foreach ($data['returned_items'] as $item) {
        $equipmentName = $item['equipment_name'];
        $returnedOn = $item['returned_on'];
        $remarks = $item['remarks'];
        $submittedReturnItems[] = [
            'equipment_name' => $equipmentName,
            'returned_on' => $returnedOn,
            'remarks' => $remarks
        ];

        $updateStmt->bind_param("ssssis",
            $returnedOn,
            $returnedOn,
            $remarks,
            $remarks,
            $borrowRequestId,
            $equipmentName
        );
        if (!$updateStmt->execute()) {
            throw new Exception("Execute failed: " . $updateStmt->error);
        }

        if ($shouldRestoreInventory) {
            $getQtyStmt->bind_param("is", $borrowRequestId, $equipmentName);
            $getQtyStmt->execute();
            $result = $getQtyStmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $qtyBorrowed = (int)$row['quantity'];

                $updateAvailableStmt->bind_param("is", $qtyBorrowed, $equipmentName);
                if (!$updateAvailableStmt->execute()) {
                    throw new Exception("Update available failed: " . $updateAvailableStmt->error);
                }
            }
        }
    }

    $returnStatus = determineReturnStatus($request, $submittedReturnItems);
    $verifiedAtSql = $verificationStatus === 'Verified' ? "NOW()" : "NULL";
    $updateRequestSql = "
        UPDATE borrow_requests
        SET return_verification_status = ?,
            return_verification_notes = IF(? = '', NULL, ?),
            return_verified_at = $verifiedAtSql,
            return_inventory_restored = IF(? = 1, 1, return_inventory_restored),
            return_status = IF(? = '', return_status, ?)
        WHERE id = ?
    ";
    $updateRequestStmt = $conn->prepare($updateRequestSql);
    $returnStatusValue = $returnStatus ?? '';
    $restoredFlag = $shouldRestoreInventory ? 1 : 0;
    $updateRequestStmt->bind_param("sssissi", $verificationStatus, $verificationNotes, $verificationNotes, $restoredFlag, $returnStatusValue, $returnStatusValue, $borrowRequestId);
    $updateRequestStmt->execute();

    $conn->commit();
    echo json_encode([
        "success" => true,
        "message" => "Return verification saved successfully",
        "verification_status" => $verificationStatus,
        "return_status" => $returnStatus
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
