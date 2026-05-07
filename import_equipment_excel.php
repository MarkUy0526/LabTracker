<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

header("Content-Type: application/json");
include 'db.php';
require 'equipment_condition_helpers.php';

date_default_timezone_set('Asia/Manila');
ensureEquipmentMaintenanceColumn($conn);
ensureEquipmentInventoryControlColumns($conn);

if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["success" => false, "message" => "Upload error."]);
    exit;
}

$preview = isset($_POST['preview']) && $_POST['preview'] === '1';

$spreadsheet = IOFactory::load($_FILES['excelFile']['tmp_name']);
$sheet       = $spreadsheet->getActiveSheet();
$rows        = $sheet->toArray(null, true, true, false);

function normalizeImportHeader($value): string
{
    return preg_replace('/[^a-z0-9]+/', '', strtolower(trim((string) $value)));
}

function scoreImportHeaderRow(array $row): int
{
    $labels = array_map('normalizeImportHeader', $row);
    $set = array_flip(array_filter($labels, fn($label) => $label !== ''));
    $score = 0;

    foreach (['equipmentid', 'id'] as $key) {
        if (isset($set[$key])) { $score++; break; }
    }
    foreach (['equipment', 'equipmentname'] as $key) {
        if (isset($set[$key])) { $score += 2; break; }
    }
    foreach (['newreportt', 'newt', 't', 'total', 'totalqty'] as $key) {
        if (isset($set[$key])) { $score++; break; }
    }
    foreach (['newreportw', 'neww', 'w', 'working', 'workingqty'] as $key) {
        if (isset($set[$key])) { $score++; break; }
    }

    return $score;
}

function findImportColumn(array $headerMap, array $names, ?int $fallback = null): ?int
{
    foreach ($names as $name) {
        $key = normalizeImportHeader($name);
        if (array_key_exists($key, $headerMap)) {
            return $headerMap[$key];
        }
    }

    return $fallback;
}

$headerRowIndex = 0;
$bestHeaderScore = -1;
$scanLimit = min(count($rows), 30);
for ($i = 0; $i < $scanLimit; $i++) {
    $score = scoreImportHeaderRow($rows[$i] ?? []);
    if ($score > $bestHeaderScore) {
        $bestHeaderScore = $score;
        $headerRowIndex = $i;
    }
}

$header = $rows[$headerRowIndex] ?? [];
$conditionMHeader = strtoupper(trim((string)($header[8] ?? '')));
$hasMaintenanceColumn = in_array($conditionMHeader, ['M', 'NEW - M', 'NEW M', 'MAINTENANCE', 'MAINTENANCE QTY'], true);

$headerMap = [];
foreach ($header as $idx => $label) {
    $normalized = normalizeImportHeader($label);
    if ($normalized !== '') {
        $headerMap[$normalized] = $idx;
    }
}

$col = [
    'equipment_id' => findImportColumn($headerMap, ['Equipment ID', 'ID'], 0),
    'equipment_name' => findImportColumn($headerMap, ['Equipment', 'Equipment Name'], 1),
    'serial_number' => findImportColumn($headerMap, ['SN', 'Serial Number'], 2),
    'internal_sn' => findImportColumn($headerMap, ['ISN', 'Internal SN'], 3),
    'account_person' => findImportColumn($headerMap, ['ACC Person', 'Accountable Person'], 4),
    'total_qty' => findImportColumn($headerMap, ['New Report – T', 'New Report - T', 'New - T', 'New T', 'T', 'Total', 'Total Qty'], 5),
    'working_qty' => findImportColumn($headerMap, ['New Report – W', 'New Report - W', 'New - W', 'New W', 'W', 'Working', 'Working Qty'], 6),
    'not_working_qty' => findImportColumn($headerMap, ['New Report – NW', 'New Report - NW', 'New - NW', 'New NW', 'NW', 'Not Working', 'Non-working', 'Not Working Qty'], 7),
    'maintenance_qty' => findImportColumn($headerMap, ['New Report – M', 'New Report - M', 'New - M', 'New M', 'M', 'Maintenance', 'Maintenance Qty'], $hasMaintenanceColumn ? 8 : null),
    'is_borrowable' => findImportColumn($headerMap, ['Borrowing Status', 'Visibility', 'Available for Borrowing', 'Is Borrowable', 'Borrowable'], null),
    'description' => findImportColumn($headerMap, ['Description'], null),
];

$hasBorrowingStatusColumn = $col['is_borrowable'] !== null;

// Skip metadata/header rows, collect data rows.
$dataRows = [];
for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
    $r  = $rows[$i];
    $id = trim((string)($r[$col['equipment_id']] ?? ''));
    if ($id === '') continue;

    $dataRows[] = [
        'equipment_id'   => $id,
        'equipment_name' => trim((string)($r[$col['equipment_name']] ?? '')),
        'serial_number'  => trim((string)($r[$col['serial_number']] ?? '')),
        'internal_sn'    => trim((string)($r[$col['internal_sn']] ?? '')),
        'account_person' => trim((string)($r[$col['account_person']] ?? '')),
        'total_qty'      => (int)($r[$col['total_qty']] ?? 0),
        'working_qty'    => (int)($r[$col['working_qty']] ?? 0),
        'not_working_qty'=> (int)($r[$col['not_working_qty']] ?? 0),
        'maintenance_qty'=> $col['maintenance_qty'] !== null ? (int)($r[$col['maintenance_qty']] ?? 0) : 0,
        'is_borrowable'  => $col['is_borrowable'] !== null ? parseBorrowableFlag($r[$col['is_borrowable']] ?? '1') : 1,
        'description'    => $col['description'] !== null ? trim((string)($r[$col['description']] ?? '')) : '',
    ];
}

if (count($dataRows) === 0) {
    echo json_encode(["success" => false, "message" => "No valid rows found in the file."]);
    exit;
}

// Check for duplicates against the DB
$excelIds     = array_column($dataRows, 'equipment_id');
$placeholders = implode(',', array_fill(0, count($excelIds), '?'));
$types        = str_repeat('s', count($excelIds));

$sql  = "SELECT equipment_id FROM equipment WHERE equipment_id IN ($placeholders)";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$excelIds);
$stmt->execute();
$result = $stmt->get_result();

$existingIds = [];
while ($row = $result->fetch_assoc()) {
    $existingIds[] = $row['equipment_id'];
}

// Mark each row with its status for the preview
foreach ($dataRows as &$row) {
    $row['status'] = in_array($row['equipment_id'], $existingIds) ? 'duplicate' : 'new';
}
unset($row);

// ── PREVIEW MODE — return rows without inserting ──
if ($preview) {
    echo json_encode([
        "success"    => true,
        "rows"       => $dataRows,
        "duplicates" => $existingIds,
        "new_count"  => count(array_filter($dataRows, fn($r) => $r['status'] === 'new')),
        "dup_count"  => count($existingIds),
    ]);
    exit;
}

// ── IMPORT MODE — insert new rows, UPDATE existing ones ──
$inserted = 0;
$updated  = 0;
$importedAt = date('Y-m-d H:i:s');

foreach ($dataRows as $r) {
    $available = $r['working_qty'];

    if (in_array($r['equipment_id'], $existingIds)) {
        // Update existing equipment
        // Only update description if it has a value
        if ($r['description'] !== '' && $hasBorrowingStatusColumn) {
            $updateStmt = $conn->prepare("
                UPDATE equipment SET
                    equipment_name = ?, serial_number = ?, internal_sn = ?,
                    account_person = ?, total_qty = ?, working_qty = ?,
                    not_working_qty = ?, maintenance_qty = ?, available = ?, is_borrowable = ?,
                    description = ?, last_imported_at = ?
                WHERE equipment_id = ?
            ");
            $updateStmt->bind_param(
                "ssssiiiiiisss",
                $r['equipment_name'], $r['serial_number'], $r['internal_sn'],
                $r['account_person'], $r['total_qty'], $r['working_qty'],
                $r['not_working_qty'], $r['maintenance_qty'], $available, $r['is_borrowable'],
                $r['description'], $importedAt, $r['equipment_id']
            );
        } elseif ($r['description'] !== '') {
            $updateStmt = $conn->prepare("
                UPDATE equipment SET
                    equipment_name = ?, serial_number = ?, internal_sn = ?,
                    account_person = ?, total_qty = ?, working_qty = ?,
                    not_working_qty = ?, maintenance_qty = ?, available = ?,
                    description = ?, last_imported_at = ?
                WHERE equipment_id = ?
            ");
            $updateStmt->bind_param(
                "ssssiiiiisss",
                $r['equipment_name'], $r['serial_number'], $r['internal_sn'],
                $r['account_person'], $r['total_qty'], $r['working_qty'],
                $r['not_working_qty'], $r['maintenance_qty'], $available,
                $r['description'], $importedAt, $r['equipment_id']
            );
        } elseif ($hasBorrowingStatusColumn) {
            $updateStmt = $conn->prepare("
                UPDATE equipment SET
                    equipment_name = ?, serial_number = ?, internal_sn = ?,
                    account_person = ?, total_qty = ?, working_qty = ?,
                    not_working_qty = ?, maintenance_qty = ?, available = ?, is_borrowable = ?,
                    last_imported_at = ?
                WHERE equipment_id = ?
            ");
            $updateStmt->bind_param(
                "ssssiiiiiiss",
                $r['equipment_name'], $r['serial_number'], $r['internal_sn'],
                $r['account_person'], $r['total_qty'], $r['working_qty'],
                $r['not_working_qty'], $r['maintenance_qty'], $available, $r['is_borrowable'],
                $importedAt, $r['equipment_id']
            );
        } else {
            $updateStmt = $conn->prepare("
                UPDATE equipment SET
                    equipment_name = ?, serial_number = ?, internal_sn = ?,
                    account_person = ?, total_qty = ?, working_qty = ?,
                    not_working_qty = ?, maintenance_qty = ?, available = ?,
                    last_imported_at = ?
                WHERE equipment_id = ?
            ");
            $updateStmt->bind_param(
                "ssssiiiiiss",
                $r['equipment_name'], $r['serial_number'], $r['internal_sn'],
                $r['account_person'], $r['total_qty'], $r['working_qty'],
                $r['not_working_qty'], $r['maintenance_qty'], $available, $importedAt,
                $r['equipment_id']
            );
        }
        if ($updateStmt->execute()) $updated++;
        $updateStmt->close();
    } else {
        // Insert new equipment
        $insertStmt = $conn->prepare("
            INSERT INTO equipment
            (equipment_id, equipment_name, serial_number, internal_sn, account_person,
             total_qty, working_qty, not_working_qty, maintenance_qty, available, is_borrowable,
             description, last_imported_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insertStmt->bind_param(
            "sssssiiiiiiss",
            $r['equipment_id'], $r['equipment_name'], $r['serial_number'], $r['internal_sn'],
            $r['account_person'], $r['total_qty'], $r['working_qty'], $r['not_working_qty'],
            $r['maintenance_qty'], $available, $r['is_borrowable'], $r['description'], $importedAt
        );
        if ($insertStmt->execute()) $inserted++;
        $insertStmt->close();
    }
}

$totalProcessed = $inserted + $updated;
if ($totalProcessed > 0) {
    setInventoryMetadata($conn, 'last_imported_at', $importedAt);
    setInventoryMetadata($conn, 'last_edited_at', $importedAt);
}

$message = "Processed $totalProcessed item(s).";
if ($inserted > 0) $message .= " Inserted: $inserted.";
if ($updated > 0) $message .= " Updated: $updated.";

echo json_encode([
    "success"      => true,
    "inserted"     => $inserted,
    "updated"      => $updated,
    "message"      => $message,
]);
?>
