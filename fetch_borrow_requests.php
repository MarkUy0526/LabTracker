<?php

require 'db.php';

$startDate = isset($_GET['startDate']) ? $_GET['startDate'] : null;
$endDate = isset($_GET['endDate']) ? $_GET['endDate'] : null;

$query = "SELECT * FROM borrow_requests WHERE status = 'Pending'";

if ($startDate && $endDate) {
    $query .= " AND DATE(usage_date) BETWEEN '$startDate' AND '$endDate'";
} elseif ($startDate) {
    $query .= " AND DATE(usage_date) >= '$startDate'";
} elseif ($endDate) {
    $query .= " AND DATE(usage_date) <= '$endDate'";
}

$result = $conn->query($query);

$borrowRequests = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $borrowRequestID = $row['id'];

        $equipmentQuery = "SELECT * FROM borrowed_equipment WHERE borrow_request_id = $borrowRequestID";
        $equipmentResult = $conn->query($equipmentQuery);

        $equipmentList = [];
        if ($equipmentResult && $equipmentResult->num_rows > 0) {
            while ($equipmentRow = $equipmentResult->fetch_assoc()) {
                $equipmentList[] = $equipmentRow;
            }
        }

        $borrowRequests[] = [
            'borrowRequest' => $row,
            'equipmentList' => $equipmentList
        ];
    }
}

echo json_encode(['success' => true, 'data' => $borrowRequests]);
?>
