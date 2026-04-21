<?php

include 'db.php';

$stmt = $pdo->query("SELECT name FROM instructors");
$instructors = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($instructors);
?>
