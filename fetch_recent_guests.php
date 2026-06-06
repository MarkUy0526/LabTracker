<?php
include 'db.php'; 

$hasCreatedAt = false;
$columnResult = $conn->query("SHOW COLUMNS FROM borrow_requests LIKE 'created_at'");
if ($columnResult && $columnResult->num_rows > 0) {
  $hasCreatedAt = true;
}

$dateExpr = $hasCreatedAt ? "COALESCE(created_at, date)" : "date";

$sql = "SELECT guest_number, status, $dateExpr AS created_at
        FROM borrow_requests 
        ORDER BY id DESC
        LIMIT 10";

$result = $conn->query($sql);
$data = [];

if ($result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $data[] = $row;
  }
}

$todayCount = 0;
$countSql = "SELECT COUNT(*) AS today_count
             FROM borrow_requests
             WHERE DATE($dateExpr) = CURDATE()";
$countResult = $conn->query($countSql);
if ($countResult && ($countRow = $countResult->fetch_assoc())) {
  $todayCount = (int)$countRow['today_count'];
}

echo json_encode(['success' => true, 'data' => $data, 'today_count' => $todayCount]);
?>
