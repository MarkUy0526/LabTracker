<?php
require 'db.php';
header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

$from   = $_GET['from']   ?? '';
$to     = $_GET['to']     ?? '';
$status = $_GET['status'] ?? 'All';

if (!$from || !$to) {
    echo json_encode(['success' => false, 'message' => 'from and to are required']);
    exit;
}

$fromDt = DateTime::createFromFormat('Y-m-d', $from);
$toDt   = DateTime::createFromFormat('Y-m-d', $to);
if (!$fromDt || !$toDt) {
    echo json_encode(['success' => false, 'message' => 'from and to must be YYYY-MM-DD']);
    exit;
}

if ($fromDt > $toDt) {
    echo json_encode(['success' => false, 'message' => '"from" must not be after "to"']);
    exit;
}

$allowed = ['All', 'Accepted', 'Pending', 'Rejected'];
if (!in_array($status, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

if ($status === 'All') {
    $sql = "SELECT DATE(date) AS day, COUNT(*) AS count
            FROM borrow_requests
            WHERE DATE(date) BETWEEN ? AND ?
            GROUP BY day
            ORDER BY day";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Query preparation failed']);
        exit;
    }
    $stmt->bind_param('ss', $from, $to);
} else {
    $sql = "SELECT DATE(date) AS day, COUNT(*) AS count
            FROM borrow_requests
            WHERE DATE(date) BETWEEN ? AND ?
              AND status = ?
            GROUP BY day
            ORDER BY day";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Query preparation failed']);
        exit;
    }
    $stmt->bind_param('sss', $from, $to, $status);
}

$stmt->execute();
$result = $stmt->get_result();

$dbData = [];
while ($row = $result->fetch_assoc()) {
    $dbData[$row['day']] = (int) $row['count'];
}

$labels = [];
$counts = [];

$current = $fromDt;
$end     = $toDt;

while ($current <= $end) {
    $dateKey  = $current->format('Y-m-d');
    $labels[] = $current->format('M j');   // e.g. "Apr 1"
    $counts[] = $dbData[$dateKey] ?? 0;
    $current->modify('+1 day');
}

echo json_encode([
    'success' => true,
    'labels'  => $labels,
    'counts'  => $counts,
]);
