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
    echo json_encode(['success' => false, 'message' => 'Please complete all required equipment fields.']);
    exit();
}

foreach ([
    'Total quantity' => $totalQty,
    'Working quantity' => $workingQty,
    'Non-working quantity' => $notWorkingQty,
    'Maintenance quantity' => $maintenanceQty,
] as $label => $value) {
    $valueString = trim((string)$value);
    if ($valueString !== '' && $valueString[0] === '-') {
        echo json_encode(['success' => false, 'message' => "$label cannot be less than zero."]);
        exit();
    }
}

if (
    !ctype_digit($totalQty) || !ctype_digit($workingQty) ||
    !ctype_digit($notWorkingQty) || !ctype_digit($maintenanceQty)
) {
    echo json_encode(['success' => false, 'message' => 'Equipment quantities must be whole numbers with no decimals.']);
    exit();
}

$totalQty       = (int) $totalQty;
$workingQty     = (int) $workingQty;
$notWorkingQty  = (int) $notWorkingQty;
$maintenanceQty = (int) $maintenanceQty;
$available      = $workingQty;

if ($totalQty <= 0) {
    echo json_encode(['success' => false, 'message' => 'Total quantity must be greater than zero.']);
    exit();
}

if ($workingQty + $notWorkingQty + $maintenanceQty !== $totalQty) {
    echo json_encode(['success' => false, 'message' => 'Working, Non-working, and Maintenance quantities must add up exactly to Total Qty.']);
    exit();
}

if ($workingQty === 0 && $notWorkingQty === 0 && $maintenanceQty === 0) {
    echo json_encode(['success' => false, 'message' => 'Select at least one condition count.']);
    exit();
}

if (!empty($_FILES['equipment_image']['name'])) {
    $file = $_FILES['equipment_image'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Equipment image upload failed. Upload error code: ' . $file['error']]);
        exit();
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid equipment image type. Only JPG, PNG, and WebP are allowed.']);
        exit();
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Equipment image must be under 5MB.']);
        exit();
    }
}

$duplicateStmt = $conn->prepare("SELECT equipment_id FROM equipment WHERE equipment_id = ? LIMIT 1");
if ($duplicateStmt) {
    $duplicateStmt->bind_param("s", $equipmentID);
    $duplicateStmt->execute();
    $duplicateResult = $duplicateStmt->get_result();
    if ($duplicateResult && $duplicateResult->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => "Equipment ID $equipmentID already exists. Choose another category or refresh the generated ID."]);
        $duplicateStmt->close();
        exit();
    }
    $duplicateStmt->close();
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
    echo json_encode(['success' => false, 'message' => 'Unable to add equipment: ' . $stmt->error]);
    $stmt->close();
    $conn->close();
    exit();
}
$stmt->close();

if (!empty($_FILES['equipment_image']['name'])) {
    $file = $_FILES['equipment_image'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $maxSize = 5 * 1024 * 1024;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Equipment was added, but image upload failed. Upload error code: ' . $file['error']]);
        $conn->close();
        exit();
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, $allowedTypes, true)) {
        echo json_encode(['success' => false, 'message' => 'Equipment was added, but image type is invalid. Only JPG, PNG, and WebP are allowed.']);
        $conn->close();
        exit();
    }

    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'Equipment was added, but image size must be under 5MB.']);
        $conn->close();
        exit();
    }

    $imageDir = __DIR__ . '/equipment_images';
    if (!is_dir($imageDir)) {
        mkdir($imageDir, 0755, true);
    }

    $safeId = preg_replace('/[^A-Za-z0-9_\-]/', '_', $equipmentID);
    $ext = match ($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => 'jpg'
    };

    foreach (['jpg', 'png', 'webp'] as $oldExt) {
        $oldPath = $imageDir . '/' . $safeId . '.' . $oldExt;
        if (file_exists($oldPath)) {
            unlink($oldPath);
        }
    }

    if (!move_uploaded_file($file['tmp_name'], $imageDir . '/' . $safeId . '.' . $ext)) {
        echo json_encode(['success' => false, 'message' => 'Equipment was added, but the image could not be saved.']);
        $conn->close();
        exit();
    }
}

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
