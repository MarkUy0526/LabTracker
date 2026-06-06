<?php
header('Content-Type: application/json');
require 'db.php';

$equipmentName = isset($_GET['equipment_name']) ? trim($_GET['equipment_name']) : '';

if (!$equipmentName) {
    echo json_encode(['success' => false, 'message' => 'Equipment name is required']);
    exit;
}

try {
    $query = "
    SELECT
        br.id,
        br.borrower_name,
        br.student_id,
        be.quantity,
        br.date AS date_borrowed,
        br.usage_date AS expected_return,
        be.returned_on AS actual_return,
        br.status,
        be.remarks,
        br.instructor_name,
        br.room,
        br.department
    FROM borrowed_equipment be
    JOIN borrow_requests br ON be.borrow_request_id = br.id
    WHERE be.equipment_name = ?
    ORDER BY br.date DESC
    ";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param('s', $equipmentName);
    $stmt->execute();
    $result = $stmt->get_result();

    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = [
            'id' => $row['id'],
            'borrower_name' => $row['borrower_name'],
            'student_id' => $row['student_id'],
            'quantity' => $row['quantity'],
            'date_borrowed' => $row['date_borrowed'],
            'expected_return' => $row['expected_return'],
            'actual_return' => $row['actual_return'],
            'status' => $row['status'],
            'remarks' => $row['remarks'],
            'instructor_name' => $row['instructor_name'],
            'room' => $row['room'],
            'department' => $row['department']
        ];
    }

    echo json_encode([
        'success' => true,
        'equipment_name' => $equipmentName,
        'data' => $history,
        'total' => count($history)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
