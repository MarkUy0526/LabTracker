<?php
require 'db.php';
require 'equipment_condition_helpers.php';
header('Content-Type: application/json');

ensureEquipmentInventoryControlColumns($conn);

if (!isset($_GET['equipmentID'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing equipment ID'
    ]);
    exit;
}

$equipmentID = $_GET['equipmentID'];

$query = "SELECT working_qty, total_qty, not_working_qty FROM equipment WHERE equipment_id = ? AND is_borrowable = 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $equipmentID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Equipment not found'
    ]);
    exit;
}

$row = $result->fetch_assoc();

$working = (int) $row['working_qty'];
$available = (int) $row['total_qty'] - (int) $row['not_working_qty'];

echo json_encode([
    'success' => true,
    'working' => $working,
    'available' => $available
]);
