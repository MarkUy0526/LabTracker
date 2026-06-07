<?php
require 'db.php';
require 'return_photo_helpers.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

ensureReturnPhotoColumns($conn);

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data) || !isset($data['borrow_request_id'], $data['returned_items']) || !is_array($data['returned_items'])) {
    echo json_encode(["success" => false, "message" => "Borrow request ID and returned item details are required before saving return verification."]);
    exit;
}

if (count($data['returned_items']) === 0) {
    echo json_encode(["success" => false, "message" => "At least one returned equipment item is required before saving return verification."]);
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
    echo json_encode(["success" => false, "message" => "Borrow request #$borrowRequestId was not found. Please refresh the reports page and try again."]);
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
    echo json_encode(["success" => false, "message" => "Unable to save return verification because the database update could not be prepared. Please contact the administrator."]);
    exit;
}

try {
    $submittedReturnItems = [];
    foreach ($data['returned_items'] as $item) {
        $equipmentName = trim((string)($item['equipment_name'] ?? ''));
        $returnedOn = trim((string)($item['returned_on'] ?? ''));
        $remarks = trim((string)($item['remarks'] ?? ''));
        if ($equipmentName === '') {
            throw new Exception("One returned item is missing its equipment name. Please refresh the reports page and try again.");
        }
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
            throw new Exception("Unable to save return details for '$equipmentName': " . $updateStmt->error);
        }

        if ($shouldRestoreInventory) {
            $getQtyStmt->bind_param("is", $borrowRequestId, $equipmentName);
            $getQtyStmt->execute();
            $result = $getQtyStmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $qtyBorrowed = (int)$row['quantity'];

                $updateAvailableStmt->bind_param("is", $qtyBorrowed, $equipmentName);
                if (!$updateAvailableStmt->execute()) {
                    throw new Exception("Unable to restore inventory quantity for '$equipmentName': " . $updateAvailableStmt->error);
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
    if (!$updateRequestStmt) {
        throw new Exception("Unable to update the return verification status for request #$borrowRequestId. Please contact the administrator.");
    }
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
