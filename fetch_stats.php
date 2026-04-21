<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'db.php';

date_default_timezone_set('Asia/Manila');

$today = date('Y-m-d');

$startOfWeek = date('Y-m-d', strtotime('last Sunday', strtotime($today)));
$endOfWeek = date('Y-m-d', strtotime('next Saturday', strtotime($startOfWeek)));

$startOfMonth = date('Y-m-01');
$endOfMonth = date('Y-m-t');

function getStatusCounts($conn, $start, $end) {
    $query = "SELECT status, COUNT(*) AS count FROM borrow_requests 
              WHERE DATE(date) BETWEEN '$start' AND '$end' 
              GROUP BY status";
    $result = $conn->query($query);

    $stats = ['total' => 0, 'accepted' => 0, 'rejected' => 0];
    while ($row = $result->fetch_assoc()) {
        $stats['total'] += $row['count'];
        if ($row['status'] === 'Accepted') {
            $stats['accepted'] += $row['count'];
        } elseif ($row['status'] === 'Rejected') {
            $stats['rejected'] += $row['count'];
        }
    }
    return $stats;
}

function getTopItem($conn, $start, $end) {
    $query = "
        SELECT be.equipment_name, SUM(be.quantity) AS total_qty
        FROM borrow_requests br
        JOIN borrowed_equipment be ON br.id = be.borrow_request_id
        WHERE DATE(br.date) BETWEEN '$start' AND '$end'
        GROUP BY be.equipment_name
        ORDER BY total_qty DESC
        LIMIT 1
    ";
    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        return $row['equipment_name'];
    }
    return "N/A";
}

$weekly = getStatusCounts($conn, $startOfWeek, $endOfWeek);
$weekly['topItem'] = getTopItem($conn, $startOfWeek, $endOfWeek);

$monthly = getStatusCounts($conn, $startOfMonth, $endOfMonth);
$monthly['topItem'] = getTopItem($conn, $startOfMonth, $endOfMonth);

$trendQuery = "
    SELECT 
        be.equipment_name,
        DATE_FORMAT(br.date, '%b') AS month,
        COUNT(*) AS borrow_count
    FROM borrow_requests br
    JOIN borrowed_equipment be ON br.id = be.borrow_request_id
    WHERE br.status = 'Accepted'
    GROUP BY be.equipment_name, month
";

$trendResult = $conn->query($trendQuery);
$equipmentTrend = [];

$months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

while ($row = $trendResult->fetch_assoc()) {
    $item = $row['equipment_name'];
    $month = $row['month'];
    $count = (int)$row['borrow_count'];

    if (!isset($equipmentTrend[$item])) {
        $equipmentTrend[$item] = array_fill_keys($months, 0); 
    }
    $equipmentTrend[$item][$month] = $count;
}

echo json_encode([
    'success' => true,
    'weekly' => $weekly,
    'monthly' => $monthly,
    'equipmentTrend' => $equipmentTrend
]);

?>
