<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

header("Content-Type: application/json");
include 'db.php';
require 'equipment_condition_helpers.php';

ensureEquipmentMaintenanceColumn($conn);

if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["success" => false, "message" => "Upload error."]);
    exit;
}

$preview = isset($_POST['preview']) && $_POST['preview'] === '1';

$spreadsheet = IOFactory::load($_FILES['excelFile']['tmp_name']);
$sheet       = $spreadsheet->getActiveSheet();
$rows        = $sheet->toArray(null, true, true, false);
$header      = $rows[0] ?? [];
$conditionMHeader = strtoupper(trim((string)($header[8] ?? '')));
$hasMaintenanceColumn = in_array($conditionMHeader, ['M', 'MAINTENANCE', 'MAINTENANCE QTY'], true);

// Skip header row (index 0), collect data rows
$dataRows = [];
for ($i = 1; $i < count($rows); $i++) {
    $r  = $rows[$i];
    $id = trim((string)($r[0] ?? ''));
    if ($id === '') continue;

    $dataRows[] = [
        'equipment_id'   => $id,
        'equipment_name' => trim((string)($r[1] ?? '')),
        'serial_number'  => trim((string)($r[2] ?? '')),
        'internal_sn'    => trim((string)($r[3] ?? '')),
        'account_person' => trim((string)($r[4] ?? '')),
        'total_qty'      => (int)($r[5] ?? 0),
        'working_qty'    => (int)($r[6] ?? 0),
        'not_working_qty'=> (int)($r[7] ?? 0),
        'maintenance_qty'=> $hasMaintenanceColumn ? (int)($r[8] ?? 0) : 0,
        'description'    => trim((string)($r[$hasMaintenanceColumn ? 9 : 8] ?? '')),
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

foreach ($dataRows as $r) {
    $available = $r['working_qty'];

    if (in_array($r['equipment_id'], $existingIds)) {
        // Update existing equipment
        // Only update description if it has a value
        if ($r['description'] !== '') {
            $updateStmt = $conn->prepare("
                UPDATE equipment SET
                    equipment_name = ?, serial_number = ?, internal_sn = ?,
                    account_person = ?, total_qty = ?, working_qty = ?,
                    not_working_qty = ?, maintenance_qty = ?, available = ?, description = ?
                WHERE equipment_id = ?
            ");
            $updateStmt->bind_param(
                "ssssiiiiiss",
                $r['equipment_name'], $r['serial_number'], $r['internal_sn'],
                $r['account_person'], $r['total_qty'], $r['working_qty'],
                $r['not_working_qty'], $r['maintenance_qty'], $available, $r['description'], $r['equipment_id']
            );
        } else {
            $updateStmt = $conn->prepare("
                UPDATE equipment SET
                    equipment_name = ?, serial_number = ?, internal_sn = ?,
                    account_person = ?, total_qty = ?, working_qty = ?,
                    not_working_qty = ?, maintenance_qty = ?, available = ?
                WHERE equipment_id = ?
            ");
            $updateStmt->bind_param(
                "ssssiiiiis",
                $r['equipment_name'], $r['serial_number'], $r['internal_sn'],
                $r['account_person'], $r['total_qty'], $r['working_qty'],
                $r['not_working_qty'], $r['maintenance_qty'], $available, $r['equipment_id']
            );
        }
        if ($updateStmt->execute()) $updated++;
        $updateStmt->close();
    } else {
        // Insert new equipment
        $insertStmt = $conn->prepare("
            INSERT INTO equipment
            (equipment_id, equipment_name, serial_number, internal_sn, account_person,
             total_qty, working_qty, not_working_qty, maintenance_qty, available, description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insertStmt->bind_param(
            "sssssiiiiis",
            $r['equipment_id'], $r['equipment_name'], $r['serial_number'], $r['internal_sn'],
            $r['account_person'], $r['total_qty'], $r['working_qty'], $r['not_working_qty'],
            $r['maintenance_qty'], $available, $r['description']
        );
        if ($insertStmt->execute()) $inserted++;
        $insertStmt->close();
    }
}

$totalProcessed = $inserted + $updated;
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
