<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
  http_response_code(401);
  echo 'Unauthorized';
  exit;
}

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

require 'db.php';
require 'equipment_condition_helpers.php';
require 'audit_snapshot_helpers.php';

try {
  ensureAuditItemConditionColumns($conn);
  ensureInventoryAuditSnapshotTables($conn);

  $audit_id = isset($_GET['audit_id']) ? (int)$_GET['audit_id'] : null;
  if (!$audit_id) {
    throw new Exception('Missing audit_id parameter');
  }

  $audit_stmt = $conn->prepare("
    SELECT id, audit_date, admin_name, status, total_items, complete_count, missing_count, damaged_count
    FROM inventory_audits
    WHERE id = ?
  ");
  $audit_stmt->bind_param("i", $audit_id);
  $audit_stmt->execute();
  $audit_result = $audit_stmt->get_result();

  if ($audit_result->num_rows === 0) {
    throw new Exception('Audit not found');
  }

  $audit = $audit_result->fetch_assoc();
  $snapshot = getAuditSnapshotByAuditId($conn, $audit_id);

  $items = [];

  if ($snapshot) {
    $currentItems = getAuditSnapshotItems($conn, (int)$snapshot['id']);
    $previousItems = !empty($snapshot['previous_snapshot_id'])
      ? getAuditSnapshotItems($conn, (int)$snapshot['previous_snapshot_id'])
      : [];
    $equipmentIds = array_unique(array_merge(array_keys($currentItems), array_keys($previousItems)));

    foreach ($equipmentIds as $equipmentId) {
      $current = $currentItems[$equipmentId] ?? null;
      $previous = $previousItems[$equipmentId] ?? null;
      $base = $current ?: $previous;

      $items[] = [
        'equipment_name' => $base['equipment_name'] ?? 'Unknown',
        'serial_number' => $base['serial_number'] ?? '',
        'internal_sn' => $base['internal_sn'] ?? '',
        'account_person' => $base['account_person'] ?? $current['account_person'] ?? $previous['account_person'] ?? '',
        'actual_qty' => $current ? (int)$current['total_qty'] : 0,
        'actual_working_qty' => $current ? (int)$current['working_qty'] : 0,
        'actual_not_working_qty' => $current ? (int)$current['not_working_qty'] : 0,
        'actual_maintenance_qty' => $current ? (int)$current['maintenance_qty'] : 0,
        'status' => $current['status'] ?? 'Missing',
        'damage_notes' => $current['notes'] ?? '',
      ];
    }
  } else {
    // Legacy audits without snapshots
    $items_stmt = $conn->prepare("
      SELECT
        ai.equipment_id,
        ai.equipment_name,
        ai.actual_qty,
        ai.actual_working_qty,
        ai.actual_not_working_qty,
        ai.actual_maintenance_qty,
        ai.status,
        ai.damage_notes,
        e.serial_number,
        e.internal_sn,
        e.account_person
      FROM audit_items ai
      LEFT JOIN equipment e ON ai.equipment_id = e.equipment_id
      WHERE ai.audit_id = ?
      ORDER BY ai.equipment_name ASC
    ");
    $items_stmt->bind_param("i", $audit_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();

    while ($row = $items_result->fetch_assoc()) {
      $actualQty = (int)$row['actual_qty'];
      $actualWorking = (int)$row['actual_working_qty'];
      $actualNotWorking = (int)$row['actual_not_working_qty'];
      $actualMaintenance = (int)$row['actual_maintenance_qty'];

      $items[] = [
        'equipment_name' => $row['equipment_name'],
        'serial_number' => $row['serial_number'] ?? '',
        'internal_sn' => $row['internal_sn'] ?? '',
        'account_person' => $row['account_person'] ?? '',
        'actual_qty' => $actualQty,
        'actual_working_qty' => $actualWorking,
        'actual_not_working_qty' => $actualNotWorking,
        'actual_maintenance_qty' => $actualMaintenance,
        'status' => $row['status'],
        'damage_notes' => $row['damage_notes'],
      ];
    }
  }

  // Sort items by equipment name
  usort($items, fn($a, $b) => strcmp($a['equipment_name'], $b['equipment_name']));

  $spreadsheet = new Spreadsheet();
  $sheet = $spreadsheet->getActiveSheet();
  $sheet->setTitle('Audit Report');

  // Metadata section
  $sheet->setCellValue('A1', 'Inventory Audit Report');
  $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
  $sheet->mergeCells('A1:J1');

  $row = 3;
  $metadataStyle = ['font' => ['bold' => true, 'size' => 11], 'alignment' => ['horizontal' => 'left']];

  $sheet->setCellValue("A$row", 'Audit ID');
  $sheet->setCellValue("B$row", $audit['id']);
  $sheet->getStyle("A$row")->applyFromArray($metadataStyle);
  $row++;

  $sheet->setCellValue("A$row", 'Audit Date');
  $sheet->setCellValue("B$row", $audit['audit_date']);
  $sheet->getStyle("A$row")->applyFromArray($metadataStyle);
  $row++;

  $sheet->setCellValue("A$row", 'Timestamp (PST)');
  $sheet->setCellValue("B$row", date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' -8 hours')));
  $sheet->getStyle("A$row")->applyFromArray($metadataStyle);
  $row++;

  $sheet->setCellValue("A$row", 'Admin');
  $sheet->setCellValue("B$row", $audit['admin_name']);
  $sheet->getStyle("A$row")->applyFromArray($metadataStyle);
  $row++;

  $sheet->setCellValue("A$row", 'Status');
  $sheet->setCellValue("B$row", $audit['status']);
  $sheet->getStyle("A$row")->applyFromArray($metadataStyle);
  $row++;

  $row++;

  $sheet->setCellValue("A$row", 'Summary');
  $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(11);
  $row++;

  $sheet->setCellValue("A$row", 'Total Items');
  $sheet->setCellValue("B$row", $audit['total_items']);
  $row++;

  $sheet->setCellValue("A$row", 'Complete');
  $sheet->setCellValue("B$row", $audit['complete_count']);
  $row++;

  $sheet->setCellValue("A$row", 'Missing');
  $sheet->setCellValue("B$row", $audit['missing_count']);
  $row++;

  $sheet->setCellValue("A$row", 'Damaged');
  $sheet->setCellValue("B$row", $audit['damaged_count']);
  $row++;

  // Items table
  $row += 2;
  $headerRow = $row;

  $headers = [
    'Equipment Name',
    'SN',
    'ISN',
    'Accountable Person',
    'New Report – T',
    'New Report – W',
    'New Report – NW',
    'New Report – M',
    'Status',
    'Notes'
  ];

  $sheet->fromArray($headers, null, "A$row");

  $headerStyle = [
    'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF28A745']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
    'alignment' => ['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true],
  ];

  $headerRange = "A$headerRow:J$headerRow";
  $sheet->getStyle($headerRange)->applyFromArray($headerStyle);

  // Data rows
  $row++;
  $rowStart = $row;

  foreach ($items as $item) {
    $sheet->setCellValue("A$row", $item['equipment_name']);
    $sheet->setCellValue("B$row", $item['serial_number']);
    $sheet->setCellValue("C$row", $item['internal_sn']);
    $sheet->setCellValue("D$row", $item['account_person']);
    $sheet->setCellValue("E$row", $item['actual_qty']);
    $sheet->setCellValue("F$row", $item['actual_working_qty']);
    $sheet->setCellValue("G$row", $item['actual_not_working_qty']);
    $sheet->setCellValue("H$row", $item['actual_maintenance_qty']);
    $sheet->setCellValue("I$row", $item['status']);
    $sheet->setCellValue("J$row", $item['damage_notes']);

    // Center align quantity columns
    for ($col = 5; $col <= 8; $col++) {
      $sheet->getStyle(chr(64 + $col) . $row)->getAlignment()->setHorizontal('center');
    }

    $row++;
  }

  // Apply borders to data rows
  if ($row > $rowStart) {
    $dataRange = "A$rowStart:J" . ($row - 1);
    $sheet->getStyle($dataRange)->applyFromArray([
      'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']]]
    ]);
  }

  // Column widths
  $sheet->getColumnDimension('A')->setWidth(25);
  $sheet->getColumnDimension('B')->setWidth(15);
  $sheet->getColumnDimension('C')->setWidth(15);
  $sheet->getColumnDimension('D')->setWidth(18);
  $sheet->getColumnDimension('E')->setWidth(12);
  $sheet->getColumnDimension('F')->setWidth(12);
  $sheet->getColumnDimension('G')->setWidth(12);
  $sheet->getColumnDimension('H')->setWidth(12);
  $sheet->getColumnDimension('I')->setWidth(12);
  $sheet->getColumnDimension('J')->setWidth(20);

  // Signatories section
  $row += 3;
  $sheet->setCellValue("A$row", 'Signatories');
  $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(11);
  $row += 2;

  $signatories = ['Mr. Lester D. Bernardino', 'Mr. Hiromi Rivas'];
  $colIndex = 1;
  foreach ($signatories as $signatory) {
    $colLetter = chr(64 + $colIndex);
    $sheet->setCellValue("$colLetter$row", '_____________________');
    $sheet->getStyle("$colLetter$row")->getAlignment()->setHorizontal('center');
    $row++;
    $sheet->setCellValue("$colLetter$row", $signatory);
    $sheet->getStyle("$colLetter$row")->getAlignment()->setHorizontal('center');
    $row++;
    $sheet->setCellValue("$colLetter$row", $signatory === 'Mr. Hiromi Rivas' ? 'Applied Physics Professor' : 'Chairperson');
    $sheet->getStyle("$colLetter$row")->getAlignment()->setHorizontal('center');
    $row = $row - 2;
    $colIndex++;
  }

  $writer = new Xlsx($spreadsheet);
  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment;filename="inventory-audit-' . $audit['audit_date'] . '.xlsx"');
  header('Cache-Control: max-age=0');
  $writer->save('php://output');
  exit;

} catch (Exception $e) {
  http_response_code(400);
  echo 'Error: ' . $e->getMessage();
}
?>
