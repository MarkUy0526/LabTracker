<?php
require 'db.php';

$sql = "SELECT * FROM borrow_requests WHERE status IN ('Accepted', 'Rejected') ORDER BY id DESC";
$result = $conn->query($sql);
$data = [];

while ($row = $result->fetch_assoc()) {
    $req_id = (int)$row['id'];
    
    $equipments = [];
    $eq_sql = "SELECT * FROM borrowed_equipment WHERE borrow_request_id = $req_id";
    $eq_result = $conn->query($eq_sql);

    while ($eq = $eq_result->fetch_assoc()) {
        $equipments[] = $eq;
    }

    $data[] = [
        'borrowRequest' => $row,
        'equipmentList' => $equipments
    ];
}

echo json_encode([
    'success' => true,
    'data' => $data
]);
