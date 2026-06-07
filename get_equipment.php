<?php
session_start();
require 'db.php';
require 'equipment_condition_helpers.php';

ensureEquipmentMaintenanceColumn($conn);
ensureEquipmentInventoryControlColumns($conn);

$sql = "SELECT 
    equipment_id, 
    equipment_name, 
    serial_number, 
    internal_sn, 
    account_person, 
    total_qty, 
    working_qty, 
    not_working_qty, 
    maintenance_qty,
    available,
    is_borrowable,
    last_imported_at,
    last_edited_at,
    description 
    FROM equipment";

$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
if (!$isAdmin) {
    $sql .= " WHERE is_borrowable = 1";
}

$result = $conn->query($sql);

$equipment = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Resolve photo_url: check if file exists in equipment_images/{id}.jpg, .png, or .webp
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
}

header('Content-Type: application/json');
echo json_encode($equipment);
?>
