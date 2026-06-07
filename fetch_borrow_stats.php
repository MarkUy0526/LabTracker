<?php
require 'db.php';

header('Content-Type: application/json');

date_default_timezone_set('Asia/Manila');

if (!isset($_GET['date'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Date is missing'
    ]);
    exit;
}

$date = $_GET['date'];

// borrow_requests has no created_at — use the 'date' column
$sql = "SELECT status, COUNT(*) AS count
        FROM borrow_requests
        WHERE DATE(date) = ?
        GROUP BY status";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();

$stats = [
    "total"    => 0,
    "approved" => 0,
    "denied"   => 0,
    "pending"  => 0
];

while ($row = $result->fetch_assoc()) {
    $count = (int)$row['count'];
    $stats["total"] += $count;
    $status = strtolower(trim($row['status']));

    if ($status === 'accepted') {
        $status = 'approved';
    } elseif ($status === 'rejected') {
        $status = 'denied';
    }

    if (array_key_exists($status, $stats)) {
        $stats[$status] += $count;
    }
}

echo json_encode([
    "success" => true,
    "stats"   => $stats
]);
?>
