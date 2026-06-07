<?php

include 'db.php';

$stmt = $pdo->query("SELECT room_number FROM rooms");
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rooms);
?>
