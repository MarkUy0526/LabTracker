<?php
header('Content-Type: application/json');
require 'db.php';

// ── PH Time for all timestamps ──
date_default_timezone_set('Asia/Manila');

$equipmentID       = $conn->real_escape_string($_POST['equipmentID']       ?? '');
$equipmentName     = $conn->real_escape_string($_POST['equipmentName']     ?? '');
$serialNumber      = $conn->real_escape_string($_POST['serialNumber']      ?? '');
$internalSN        = $conn->real_escape_string($_POST['internalSN']        ?? '');
$totalQty          = (int) ($_POST['totalQty']      ?? 0);
$workingQty        = (int) ($_POST['workingQty']    ?? 0);
$notWorkingQty     = (int) ($_POST['notWorkingQty'] ?? 0);
$description       = $conn->real_escape_string($_POST['description']       ?? '');
$accountablePerson = $conn->real_escape_string($_POST['accountablePerson'] ?? '');

if (empty($equipmentID) || empty($equipmentName) || empty($accountablePerson)) {
    echo json_encode(["success" => false, "message" => "Missing required fields."]);
    exit;
}

// ── Fetch current values BEFORE updating (for old vs new comparison) ──
$oldStmt = $conn->prepare(
    "SELECT equipment_name, serial_number, internal_sn, account_person,
            total_qty, working_qty, not_working_qty, description
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

// ── UPDATE equipment ──
$sql = "UPDATE equipment SET
            equipment_name   = '$equipmentName',
            serial_number    = '$serialNumber',
            internal_sn      = '$internalSN',
            account_person   = '$accountablePerson',
            total_qty        = $totalQty,
            working_qty      = $workingQty,
            not_working_qty  = $notWorkingQty,
            available        = $workingQty,
            description      = '$description'
        WHERE equipment_id   = '$equipmentID'";

if ($conn->query($sql) !== true) {
    echo json_encode(["success" => false, "message" => "Error: " . $conn->error]);
    $conn->close();
    exit;
}

// ── LOG each changed field ──
// Map DB column names to human-readable labels
$fieldLabels = [
    'equipment_name'  => 'Equipment Name',
    'serial_number'   => 'Serial Number',
    'internal_sn'     => 'Internal SN',
    'account_person'  => 'Accountable Person',
    'total_qty'       => 'Total Qty',
    'working_qty'     => 'Working Qty',
    'not_working_qty' => 'Not Working Qty',
    'description'     => 'Description',
];

// New values keyed the same way as oldData
$newData = [
    'equipment_name'  => $equipmentName,
    'serial_number'   => $serialNumber,
    'internal_sn'     => $internalSN,
    'account_person'  => $accountablePerson,
    'total_qty'       => (string) $totalQty,
    'working_qty'     => (string) $workingQty,
    'not_working_qty' => (string) $notWorkingQty,
    'description'     => $description,
];

$now    = date('Y-m-d H:i:s');   // Asia/Manila — set above
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

        // Only log fields that actually changed
        if ($oldVal !== $newVal) {
            $logStmt->bind_param(
                "ssssss",
                $equipmentID, $action, $label, $oldVal, $newVal, $now
            );
            $logStmt->execute();
        }
    }
    $logStmt->close();
}

// ── HANDLE IMAGE UPLOAD ──
if (!empty($_FILES['equipment_image']['name'])) {
    $imageDir = __DIR__ . '/equipment_images';

    // Create directory if it doesn't exist
    if (!is_dir($imageDir)) {
        mkdir($imageDir, 0755, true);
    }

    $file = $_FILES['equipment_image'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    // Validate file
    if (!in_array($file['type'], $allowedTypes)) {
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

    // Get file extension
    $ext = match($file['type']) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default => 'jpg'
    };

    // Delete old images with any extension
    foreach (['jpg', 'png', 'webp'] as $oldExt) {
        $oldPath = $imageDir . '/' . $equipmentID . '.' . $oldExt;
        if (file_exists($oldPath)) {
            unlink($oldPath);
        }
    }

    // Save new image
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
