<?php
include 'db.php'; 

$sql = "SELECT instructor_name FROM instructors"; 
$result = $conn->query($sql);

$instructors = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $instructors[] = $row['instructor_name'];
    }
}
echo json_encode($instructors);

$conn->close();
?>