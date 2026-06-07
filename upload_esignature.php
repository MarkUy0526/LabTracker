<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_FILES['signature']) || $_FILES['signature']['error'] !== UPLOAD_ERR_OK) {
    $err = $_FILES['signature']['error'] ?? 'No file';
    echo json_encode(['success' => false, 'message' => 'Upload error: ' . $err]);
    exit;
}

$maxBytes = 3 * 1024 * 1024;
if ($_FILES['signature']['size'] > $maxBytes) {
    echo json_encode(['success' => false, 'message' => 'Signature image must be under 3MB.']);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($_FILES['signature']['tmp_name']);
$extMap = [
    'image/png' => 'png',
    'image/jpeg' => 'jpg',
    'image/webp' => 'webp'
];

if (!isset($extMap[$mimeType])) {
    echo json_encode(['success' => false, 'message' => 'Only PNG, JPG, and WebP images are allowed.']);
    exit;
}

$uploadDir = __DIR__ . '/images/';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    echo json_encode(['success' => false, 'message' => 'Unable to create image folder.']);
    exit;
}

$baseName = 'hiromi_rivas_esignature';
$ext = $extMap[$mimeType];
$destPath = $uploadDir . $baseName . '.' . $ext;

foreach (['png', 'jpg', 'jpeg', 'webp'] as $oldExt) {
    $oldPath = $uploadDir . $baseName . '.' . $oldExt;
    if (is_file($oldPath)) {
        unlink($oldPath);
    }
}

if (!move_uploaded_file($_FILES['signature']['tmp_name'], $destPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save signature image.']);
    exit;
}

echo json_encode([
    'success' => true,
    'url' => 'images/' . $baseName . '.' . $ext
]);
?>
