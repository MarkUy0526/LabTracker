<?php
header('Content-Type: application/json');
require 'db_pdo.php';

try {
    // Fetch all borrow requests with their borrowed equipment
    $stmt = $pdo->prepare("SELECT * FROM borrow_requests ORDER BY id DESC");
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response = [];

    foreach ($requests as $req) {
        $borrowRequestId = (int)$req['id'];

        $eqStmt = $pdo->prepare("SELECT * FROM borrowed_equipment WHERE borrow_request_id = ?");
        $eqStmt->execute([$borrowRequestId]);
        $items = $eqStmt->fetchAll(PDO::FETCH_ASSOC);

        $equipmentList = array_map(function ($item) {
            return [
                'equipment_name' => $item['equipment_name'],
                'quantity'       => $item['quantity'],
                'available'      => $item['available'],
                'returned_on'    => $item['returned_on'] ?? null,
                'remarks'        => $item['remarks'] ?? null,
            ];
        }, $items);

        $allReturned = count($equipmentList) > 0 && array_reduce($equipmentList, function($carry, $item) {
            return $carry && !empty($item['returned_on']);
        }, true);

        $response[$borrowRequestId] = [
            'id'              => $borrowRequestId,
            'guest_number'    => $req['guest_number'],
            'borrower_name'   => $req['borrower_name'],
            'student_id'      => $req['student_id'],
            'instructor_name' => $req['instructor_name'],
            'subject_code'    => $req['subject_code'],
            'date'            => $req['date'],
            'room'            => $req['room'],
            'usage_date'      => $req['usage_date'],
            'status'          => $req['status'],
            'is_returned'     => $allReturned,
            'equipment'       => $equipmentList,
        ];
    }

    echo json_encode($response);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}