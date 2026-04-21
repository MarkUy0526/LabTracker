<?php
include 'db.php'; 

$sql = "SELECT guest_number, status, created_at 
        FROM borrow_requests 
        WHERE status IN ('accepted', 'rejected', 'pending') 
        ORDER BY created_at DESC 
        LIMIT 10";

$result = $conn->query($sql);
$data = [];

if ($result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $data[] = $row;
  }
}

echo json_encode(['success' => true, 'data' => $data]);
?>
