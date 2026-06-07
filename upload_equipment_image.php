<?php
ob_start();
session_start();
require 'db.php';
ob_end_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$equipment_id = trim($_POST['equipment_id'] ?? '');
if (!$equipment_id) {
    echo json_encode(['success' => false, 'message' => 'Missing equipment_id']);
    exit;
}

if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    $err = $_FILES['photo']['error'] ?? 'No file';
    echo json_encode(['success' => false, 'message' => 'Upload error: ' . $err]);
    exit;
}

// Validate file type
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($_FILES['photo']['tmp_name']);
$allowed  = ['image/jpeg', 'image/png', 'image/webp'];

if (!in_array($mimeType, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, and WebP images are allowed.']);
    exit;
}

// Max 5MB
if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File must be under 5MB.']);
    exit;
}

// Save to equipment_images/ folder
$uploadDir = __DIR__ . '/equipment_images/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Extension based on mime
$extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$ext    = $extMap[$mimeType];

// Use equipment_id as filename so it's easy to look up
// Remove any path traversal characters from equipment_id
$safeId   = preg_replace('/[^A-Za-z0-9_\-]/', '_', $equipment_id);
$filename = $safeId . '.' . $ext;
$destPath = $uploadDir . $filename;

// Remove old images for this equipment (any extension)
foreach (['jpg','png','webp'] as $oldExt) {
    $old = $uploadDir . $safeId . '.' . $oldExt;
    if (file_exists($old)) unlink($old);
}

if (!move_uploaded_file($_FILES['photo']['tmp_name'], $destPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file.']);
    exit;
}

$url = 'equipment_images/' . $filename;
echo json_encode(['success' => true, 'url' => $url, 'filename' => $filename]);
?>
