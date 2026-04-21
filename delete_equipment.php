<?php

header("Content-Type: application/json");

require 'db.php';

if (isset($_POST['equipmentID'])) {
    $equipmentID = $_POST['equipmentID'];

    $sql = "DELETE FROM equipment WHERE equipment_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $equipmentID);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting equipment']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'SQL error']);
    }

    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'No equipment ID provided']);
}
?>

