<?php
require 'db.php';
header('Content-Type: application/json');

$result = $conn->query("SELECT instructor_name FROM instructors ORDER BY instructor_name");

$instructors = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $instructors[] = $row;
    }
}

echo json_encode($instructors);

$conn->close();
?>
