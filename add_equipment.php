<?php
require 'db.php';

header('Content-Type: application/json');

// ── PH Time for all timestamps ──
date_default_timezone_set('Asia/Manila');

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

$equipmentID      = trim($_POST['equipmentID']      ?? '');
$equipmentName    = trim($_POST['equipmentName']    ?? '');
$serialNumber     = trim($_POST['serialNumber']     ?? '');
$internalSN       = trim($_POST['internalSN']       ?? '');
$totalQty         = trim($_POST['totalQty']         ?? '');
$workingQty       = trim($_POST['workingQty']       ?? '');
$notWorkingQty    = trim($_POST['notWorkingQty']    ?? '');
$description      = trim($_POST['description']      ?? '');
$accountablePerson= trim($_POST['accountablePerson']?? '');

if ($equipmentID === '' || $equipmentName === '' || $totalQty === '' ||
    $workingQty === '' || $notWorkingQty === '' || $accountablePerson === '') {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit();
}

if (!ctype_digit($totalQty) || !ctype_digit($workingQty) || !ctype_digit($notWorkingQty)) {
    echo json_encode(['success' => false, 'message' => 'Total, Working, and Not Working must be whole numbers.']);
    exit();
}

$totalQty      = (int) $totalQty;
$workingQty    = (int) $workingQty;
$notWorkingQty = (int) $notWorkingQty;
$available     = $workingQty;

if ($workingQty + $notWorkingQty !== $totalQty) {
    echo json_encode(['success' => false, 'message' => 'Working plus Not Working must equal Total Qty.']);
    exit();
}

if ($workingQty === 0 && $notWorkingQty === 0) {
    echo json_encode(['success' => false, 'message' => 'Select at least one condition count.']);
    exit();
}

// ── INSERT equipment ──
$stmt = $conn->prepare(
    "INSERT INTO equipment
     (equipment_id, equipment_name, serial_number, internal_sn,
      total_qty, working_qty, not_working_qty, description, account_person, available)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'SQL prepare failed: ' . $conn->error]);
    exit();
}

$stmt->bind_param(
    "ssssiiissi",
    $equipmentID, $equipmentName, $serialNumber, $internalSN,
    $totalQty, $workingQty, $notWorkingQty, $description, $accountablePerson, $available
);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $stmt->error]);
    $stmt->close();
    $conn->close();
    exit();
}
$stmt->close();

// ── LOG the ADD action ──
// Store a full JSON snapshot of the new equipment as new_value
$snapshot = json_encode([
    'equipment_name'  => $equipmentName,
    'serial_number'   => $serialNumber,
    'internal_sn'     => $internalSN,
    'account_person'  => $accountablePerson,
    'total_qty'       => $totalQty,
    'working_qty'     => $workingQty,
    'not_working_qty' => $notWorkingQty,
    'description'     => $description,
], JSON_UNESCAPED_UNICODE);

$now     = date('Y-m-d H:i:s');   // Asia/Manila — set above
$action  = 'Added';

$logStmt = $conn->prepare(
    "INSERT INTO equipment_history
     (equipment_id, action, changed_field, old_value, new_value, performed_at)
     VALUES (?, ?, NULL, NULL, ?, ?)"
);
if ($logStmt) {
    $logStmt->bind_param("ssss", $equipmentID, $action, $snapshot, $now);
    $logStmt->execute();
    $logStmt->close();
}

$conn->close();
echo json_encode(['success' => true, 'message' => 'Equipment added successfully.']);
?>
