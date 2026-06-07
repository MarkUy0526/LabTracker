<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'db.php'; 
require 'equipment_condition_helpers.php';

header('Content-Type: application/json');
ensureEquipmentInventoryControlColumns($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode($_POST['data'], true);

    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Borrow request data could not be read. Please review the form and submit again.']);
        exit;
    }

    $guestNumber = $data['guestNumber'] ?? '';
    $date = $data['date'] ?? '';
    $borrowerName = $data['borrowerName'] ?? '';
    $instructorName = $data['instructorName'] ?? '';
    $studentID = $data['studentID'] ?? '';
    $subjectCode = $data['subjectCode'] ?? '';
    $usageDate = $data['usageDate'] ?? '';
    $department = $data['department'] ?? '';
    $room = $data['room'] ?? '';
    $equipmentList = $data['equipmentList'] ?? [];

    if ($guestNumber === '') {
        echo json_encode(['success' => false, 'message' => 'Login ID is missing. Please start a borrower session again.']);
        exit;
    }
    if ($borrowerName === '') {
        echo json_encode(['success' => false, 'message' => 'Borrower name is required.']);
        exit;
    }
    if (!is_array($equipmentList) || count($equipmentList) === 0) {
        echo json_encode(['success' => false, 'message' => 'Select at least one equipment item before submitting the request.']);
        exit;
    }

    $conn->begin_transaction();

    try {
        $status = 'Pending';
        $stmt = $conn->prepare("INSERT INTO borrow_requests
            (guest_number, date, borrower_name, instructor_name, student_id, subject_code, usage_date, department, room, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception('Unable to create the borrow request because the database insert could not be prepared. Please contact the administrator.');
        }
        $stmt->bind_param("ssssssssss", $guestNumber, $date, $borrowerName, $instructorName, $studentID, $subjectCode, $usageDate, $department, $room, $status);

        if (!$stmt->execute()) {
            throw new Exception('Unable to create the borrow request record: ' . $stmt->error);
        }

        $borrowRequestID = $stmt->insert_id;

        $insertEquipmentStmt = $conn->prepare("INSERT INTO borrowed_equipment 
            (borrow_request_id, equipment_name, quantity, available) 
            VALUES (?, ?, ?, ?)");

        $updateEquipmentStmt = $conn->prepare("UPDATE equipment 
            SET available = available - ? 
            WHERE equipment_name = ? AND is_borrowable = 1 AND available >= ?");

        if (!$insertEquipmentStmt || !$updateEquipmentStmt) {
            throw new Exception('Unable to reserve the selected equipment because the database update could not be prepared. Please contact the administrator.');
        }

        foreach ($equipmentList as $item) {
            $equipmentName = trim($item['equipmentName'] ?? '');
            $quantity = (int)$item['quantity'];
            $available = (int)$item['available'];

            if ($equipmentName === '') {
                throw new Exception('One selected equipment item is missing a name. Please refresh the page and select equipment again.');
            }
            if ($quantity <= 0) {
                throw new Exception("Requested quantity for $equipmentName must be greater than zero.");
            }
            if ($available < 0) {
                throw new Exception("Available quantity for $equipmentName cannot be less than zero.");
            }

            $insertEquipmentStmt->bind_param("isis", $borrowRequestID, $equipmentName, $quantity, $available);
            if (!$insertEquipmentStmt->execute()) {
                throw new Exception("Unable to attach '$equipmentName' to the borrow request: " . $insertEquipmentStmt->error);
            }

            $updateEquipmentStmt->bind_param("isi", $quantity, $equipmentName, $quantity);
            if (!$updateEquipmentStmt->execute()) {
                throw new Exception("Unable to reserve quantity for '$equipmentName': " . $updateEquipmentStmt->error);
            }

            if ($updateEquipmentStmt->affected_rows === 0) {
                $stockStmt = $conn->prepare("SELECT available, is_borrowable FROM equipment WHERE equipment_name = ? LIMIT 1");
                $stockStmt->bind_param("s", $equipmentName);
                $stockStmt->execute();
                $stock = $stockStmt->get_result()->fetch_assoc();
                $stockStmt->close();

                if (!$stock) {
                    throw new Exception("The selected equipment '$equipmentName' no longer exists in the inventory.");
                }
                if ((int)$stock['is_borrowable'] !== 1) {
                    throw new Exception("The selected equipment '$equipmentName' is currently unavailable for borrowing.");
                }
                throw new Exception("Only {$stock['available']} item(s) of '$equipmentName' are available. Requested quantity: $quantity.");
            }
        }

        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Borrow request submitted successfully.']);
    } catch (Exception $e) {
        $conn->rollback();
        error_log('Borrow request error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Borrow request must be submitted using the borrower form. Please reload the page and try again.']);
    exit;
}
