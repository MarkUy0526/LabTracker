<?php
header('Content-Type: application/json');
require 'db.php';

date_default_timezone_set('Asia/Manila');

$equipmentID       = trim($_POST['equipmentID']       ?? '');
$equipmentName     = trim($_POST['equipmentName']     ?? '');
$serialNumber      = trim($_POST['serialNumber']      ?? '');
$internalSN        = trim($_POST['internalSN']        ?? '');
$description       = trim($_POST['description']       ?? '');
$accountablePerson = trim($_POST['accountablePerson'] ?? '');

if ($equipmentID === '' || $equipmentName === '' || $accountablePerson === '') {
    echo json_encode(["success" => false, "message" => "Missing required fields."]);
    exit;
}

$oldStmt = $conn->prepare(
    "SELECT equipment_name, serial_number, internal_sn, account_person, description
     FROM equipment WHERE equipment_id = ? LIMIT 1"
);
$oldData = [];
if ($oldStmt) {
    $oldStmt->bind_param("s", $equipmentID);
    $oldStmt->execute();
    $result  = $oldStmt->get_result();
    $oldData = $result->fetch_assoc() ?? [];
    $oldStmt->close();
}

$stmt = $conn->prepare(
    "UPDATE equipment SET
        equipment_name = ?,
        serial_number = ?,
        internal_sn = ?,
        account_person = ?,
        description = ?
     WHERE equipment_id = ?"
);

if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
    $conn->close();
    exit;
}

$stmt->bind_param(
    "ssssss",
    $equipmentName,
    $serialNumber,
    $internalSN,
    $accountablePerson,
    $description,
    $equipmentID
);

if (!$stmt->execute()) {
    echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

$fieldLabels = [
    'equipment_name' => 'Equipment Name',
    'serial_number'  => 'Serial Number',
    'internal_sn'    => 'Internal SN',
    'account_person' => 'Accountable Person',
    'description'    => 'Description',
];

$newData = [
    'equipment_name' => $equipmentName,
    'serial_number'  => $serialNumber,
    'internal_sn'    => $internalSN,
    'account_person' => $accountablePerson,
    'description'    => $description,
];

$now    = date('Y-m-d H:i:s');
$action = 'Edited';

$logStmt = $conn->prepare(
    "INSERT INTO equipment_history
     (equipment_id, action, changed_field, old_value, new_value, performed_at)
     VALUES (?, ?, ?, ?, ?, ?)"
);

if ($logStmt && !empty($oldData)) {
    foreach ($fieldLabels as $col => $label) {
        $oldVal = (string) ($oldData[$col] ?? '');
        $newVal = $newData[$col] ?? '';

        if ($oldVal !== $newVal) {
            $logStmt->bind_param(
                "ssssss",
                $equipmentID,
                $action,
                $label,
                $oldVal,
                $newVal,
                $now
            );
            $logStmt->execute();
        }
    }
    $logStmt->close();
}

if (!empty($_FILES['equipment_image']['name'])) {
    $imageDir = __DIR__ . '/equipment_images';

    if (!is_dir($imageDir)) {
        mkdir($imageDir, 0755, true);
    }

    $file = $_FILES['equipment_image'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $maxSize = 5 * 1024 * 1024;

    if (!in_array($file['type'], $allowedTypes, true)) {
        echo json_encode(["success" => false, "message" => "Invalid image type. Only JPG, PNG, and WebP are allowed."]);
        $conn->close();
        exit;
    }

    if ($file['size'] > $maxSize) {
        echo json_encode(["success" => false, "message" => "Image size must be less than 5MB."]);
        $conn->close();
        exit;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(["success" => false, "message" => "Upload error: " . $file['error']]);
        $conn->close();
        exit;
    }

    $ext = match ($file['type']) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default => 'jpg'
    };

    foreach (['jpg', 'png', 'webp'] as $oldExt) {
        $oldPath = $imageDir . '/' . $equipmentID . '.' . $oldExt;
        if (file_exists($oldPath)) {
            unlink($oldPath);
        }
    }

    $imagePath = $imageDir . '/' . $equipmentID . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $imagePath)) {
        echo json_encode(["success" => false, "message" => "Failed to save image."]);
        $conn->close();
        exit;
    }
}

$conn->close();
echo json_encode(["success" => true]);
?>
