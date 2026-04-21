<?php
require 'db.php';

$sql = "SELECT 
    equipment_id, 
    equipment_name, 
    serial_number, 
    internal_sn, 
    account_person, 
    total_qty, 
    working_qty, 
    not_working_qty, 
    available,
    description 
    FROM equipment";

$result = $conn->query($sql);

$equipment = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $equipment[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($equipment);
?>