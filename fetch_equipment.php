<?php

session_start();
include 'db.php';
require 'equipment_condition_helpers.php';

ensureEquipmentInventoryControlColumns($conn);

$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$query = "SELECT equipment_id, equipment_name, account_person, total_qty, working_qty, not_working_qty, available, is_borrowable FROM equipment";
if (!$isAdmin) {
    $query .= " WHERE is_borrowable = 1";
}
$result = mysqli_query($conn, $query);

$equipment = [];

while ($row = mysqli_fetch_assoc($result)) {
    // Resolve photo_url: check if file exists in equipment_images/{id}.jpg
    // If not found, set null so the JS falls back to the placeholder SVG
    $id       = $row['equipment_id'];
    $jpgPath  = __DIR__ . '/equipment_images/' . $id . '.jpg';
    $pngPath  = __DIR__ . '/equipment_images/' . $id . '.png';
    $webpPath = __DIR__ . '/equipment_images/' . $id . '.webp';

    if (file_exists($jpgPath)) {
        $row['photo_url'] = 'equipment_images/' . $id . '.jpg';
    } elseif (file_exists($pngPath)) {
        $row['photo_url'] = 'equipment_images/' . $id . '.png';
    } elseif (file_exists($webpPath)) {
        $row['photo_url'] = 'equipment_images/' . $id . '.webp';
    } else {
        $row['photo_url'] = null;
    }

    $equipment[] = $row;
}

header('Content-Type: application/json');
echo json_encode($equipment);
?>
