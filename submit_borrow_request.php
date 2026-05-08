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
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
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

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("INSERT INTO borrow_requests
            (guest_number, date, borrower_name, instructor_name, student_id, subject_code, usage_date, department, room)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $guestNumber, $date, $borrowerName, $instructorName, $studentID, $subjectCode, $usageDate, $department, $room);

        if (!$stmt->execute()) {
            throw new Exception('Failed to insert borrower data: ' . $stmt->error);
        }

        $borrowRequestID = $stmt->insert_id;

        $insertEquipmentStmt = $conn->prepare("INSERT INTO borrowed_equipment 
            (borrow_request_id, equipment_name, quantity, available) 
            VALUES (?, ?, ?, ?)");

        $updateEquipmentStmt = $conn->prepare("UPDATE equipment 
            SET available = available - ? 
            WHERE equipment_name = ? AND is_borrowable = 1 AND available >= ?");

        foreach ($equipmentList as $item) {
            $equipmentName = $item['equipmentName'];
            $quantity = (int)$item['quantity'];
            $available = (int)$item['available'];

            $insertEquipmentStmt->bind_param("isis", $borrowRequestID, $equipmentName, $quantity, $available);
            if (!$insertEquipmentStmt->execute()) {
                throw new Exception('Failed to insert equipment: ' . $insertEquipmentStmt->error);
            }

            $updateEquipmentStmt->bind_param("isi", $quantity, $equipmentName, $quantity);
            if (!$updateEquipmentStmt->execute()) {
                throw new Exception('Failed to update equipment quantity: ' . $updateEquipmentStmt->error);
            }

            if ($updateEquipmentStmt->affected_rows === 0) {
                throw new Exception("Not enough stock available for equipment: $equipmentName");
            }
        }

        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Borrow request submitted successfully.']);
    } catch (Exception $e) {
        $conn->rollback();
        error_log('Borrow request error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}
