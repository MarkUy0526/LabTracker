<?php
header('Content-Type: application/json');
require 'db.php'; 

try {
    $stmt = $conn->prepare("
        SELECT id, borrow_request_id, equipment_name, returned_on, remarks 
        FROM borrowed_equipment 
        WHERE (returned_on IS NOT NULL AND returned_on != '') 
           OR (remarks IS NOT NULL AND remarks != '')
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    $equipments = [];
    while ($row = $result->fetch_assoc()) {
        $row['is_returned'] = true; 
        $equipments[] = $row;
    }

    echo json_encode($equipments);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
