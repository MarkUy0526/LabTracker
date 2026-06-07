<?php
require 'db.php';
header('Content-Type: application/json');

$result = $conn->query("SELECT room_number FROM rooms ORDER BY room_number");

$rooms = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rooms[] = $row;
    }
}

echo json_encode($rooms);

$conn->close();
?>
