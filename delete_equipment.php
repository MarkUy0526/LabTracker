<?php

header("Content-Type: application/json");

require 'db.php';
require 'equipment_condition_helpers.php';

date_default_timezone_set('Asia/Manila');
ensureInventoryMetadataTable($conn);

if (isset($_POST['equipmentID'])) {
    $equipmentID = $_POST['equipmentID'];

    $sql = "DELETE FROM equipment WHERE equipment_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $equipmentID);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                setInventoryMetadata($conn, 'last_edited_at');
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting equipment']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'SQL error']);
    }

    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'No equipment ID provided']);
}
?>

