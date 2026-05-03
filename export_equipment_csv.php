<?php
include 'db.php';
require 'equipment_condition_helpers.php';

ensureEquipmentMaintenanceColumn($conn);

$sql    = "SELECT equipment_id, equipment_name, serial_number, internal_sn, account_person, total_qty, working_qty, not_working_qty, maintenance_qty, description FROM equipment";
$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    die("Query failed: " . $conn->error);
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="equipment_export.csv"');
header('Cache-Control: max-age=0');

$out = fopen('php://output', 'w');

// Header row — same column order as xlsx export
fputcsv($out, ['Equipment ID', 'Equipment', 'SN', 'ISN', 'ACC Person', 'T', 'W', 'NW', 'M', 'Description']);

while ($row = $result->fetch_assoc()) {
    fputcsv($out, [
        $row['equipment_id'],
        $row['equipment_name'],
        $row['serial_number'],
        $row['internal_sn'],
        $row['account_person'],
        $row['total_qty'],
        $row['working_qty'],
        $row['not_working_qty'],
        $row['maintenance_qty'],
        $row['description'],
    ]);
}

fclose($out);
exit;
