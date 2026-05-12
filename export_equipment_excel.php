<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;

include 'db.php';
require 'equipment_condition_helpers.php';

ensureEquipmentMaintenanceColumn($conn);
ensureEquipmentInventoryControlColumns($conn);
$sql = "SELECT * FROM equipment"; 
$result = $conn->query($sql);

if (!$result) {
    die("Query failed: " . $conn->error);
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$sheet->fromArray([
    'Equipment ID', 'Equipment', 'SN', 'ISN', 'ACC Person', 'T', 'W', 'NW', 'M',
    'Borrowing Status', 'Description', 'Last Imported', 'Last Edited'
], NULL, 'A1');

$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['argb' => Color::COLOR_WHITE],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FF28A745'], 
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => 'FF000000'],
        ],
    ],
];

$sheet->getStyle('A1:M1')->applyFromArray($headerStyle);

$rowNum = 2;
while ($row = $result->fetch_assoc()) {
    $sheet->fromArray([
        $row['equipment_id'],
        $row['equipment_name'],
        $row['serial_number'],
        $row['internal_sn'],
        $row['account_person'],
        $row['total_qty'],
        $row['working_qty'],
        $row['not_working_qty'],
        $row['maintenance_qty'],
        ((int) ($row['is_borrowable'] ?? 1) === 1) ? 'Available for Borrowing' : 'Restricted / Hidden from Borrower Side',
        $row['description'],
        $row['last_imported_at'],
        $row['last_edited_at']
    ], NULL, "A$rowNum");
    $rowNum++;
}

$writer = new Xlsx($spreadsheet);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="equipment_export.xlsx"');
header('Cache-Control: max-age=0');
$writer->save('php://output');
exit;
?>
