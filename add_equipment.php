<?php
require 'db.php';
require 'equipment_condition_helpers.php';

header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

ensureEquipmentMaintenanceColumn($conn);
ensureEquipmentInventoryControlColumns($conn);

$equipmentID       = trim($_POST['equipmentID']       ?? '');
$equipmentName     = trim($_POST['equipmentName']     ?? '');
$serialNumber      = trim($_POST['serialNumber']      ?? '');
$internalSN        = trim($_POST['internalSN']        ?? '');
$totalQty          = trim($_POST['totalQty']          ?? '');
$workingQty        = trim($_POST['workingQty']        ?? '');
$notWorkingQty     = trim($_POST['notWorkingQty']     ?? '');
$maintenanceQty    = trim($_POST['maintenanceQty']    ?? '0');
$description       = trim($_POST['description']       ?? '');
$accountablePerson = trim($_POST['accountablePerson'] ?? '');
$isBorrowable      = parseBorrowableFlag($_POST['isBorrowable'] ?? '1');

if (
    $equipmentID === '' || $equipmentName === '' || $totalQty === '' ||
    $workingQty === '' || $notWorkingQty === '' || $maintenanceQty === '' ||
    $accountablePerson === ''
) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit();
}

if (
    !ctype_digit($totalQty) || !ctype_digit($workingQty) ||
    !ctype_digit($notWorkingQty) || !ctype_digit($maintenanceQty)
) {
    echo json_encode(['success' => false, 'message' => 'Total, Working, Non-working, and Maintenance must be whole numbers.']);
    exit();
}

$totalQty       = (int) $totalQty;
$workingQty     = (int) $workingQty;
$notWorkingQty  = (int) $notWorkingQty;
$maintenanceQty = (int) $maintenanceQty;
$available      = $workingQty;

if ($workingQty + $notWorkingQty + $maintenanceQty !== $totalQty) {
    echo json_encode(['success' => false, 'message' => 'Working + Non-working + Maintenance must equal Total Qty.']);
    exit();
}

if ($workingQty === 0 && $notWorkingQty === 0 && $maintenanceQty === 0) {
    echo json_encode(['success' => false, 'message' => 'Select at least one condition count.']);
    exit();
}

$stmt = $conn->prepare(
    "INSERT INTO equipment
     (equipment_id, equipment_name, serial_number, internal_sn,
      total_qty, working_qty, not_working_qty, maintenance_qty,
      description, account_person, available, is_borrowable)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'SQL prepare failed: ' . $conn->error]);
    exit();
}

$stmt->bind_param(
    "ssssiiiissii",
    $equipmentID,
    $equipmentName,
    $serialNumber,
    $internalSN,
    $totalQty,
    $workingQty,
    $notWorkingQty,
    $maintenanceQty,
    $description,
    $accountablePerson,
    $available,
    $isBorrowable
);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $stmt->error]);
    $stmt->close();
    $conn->close();
    exit();
}
$stmt->close();

$snapshot = json_encode([
    'equipment_name'  => $equipmentName,
    'serial_number'   => $serialNumber,
    'internal_sn'     => $internalSN,
    'account_person'  => $accountablePerson,
    'total_qty'       => $totalQty,
    'working_qty'     => $workingQty,
    'not_working_qty' => $notWorkingQty,
    'maintenance_qty' => $maintenanceQty,
    'description'     => $description,
    'borrow_visibility' => $isBorrowable ? 'Available for Borrowing' : 'Restricted / Hidden from Borrower Side',
], JSON_UNESCAPED_UNICODE);

$now    = date('Y-m-d H:i:s');
setInventoryMetadata($conn, 'last_edited_at', $now);
$action = 'Added';

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
