<?php
header('Content-Type: application/json');

$baseName = 'hiromi_rivas_esignature';
$uploadDir = __DIR__ . '/images/';
$webDir = 'images/';

foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
    $path = $uploadDir . $baseName . '.' . $ext;
    if (is_file($path)) {
        echo json_encode([
            'success' => true,
            'url' => $webDir . $baseName . '.' . $ext
        ]);
        exit;
    }
}

echo json_encode(['success' => true, 'url' => '']);
?>
