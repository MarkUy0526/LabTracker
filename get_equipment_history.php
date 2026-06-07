<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$equipment_id = isset($_GET['equipment_id']) ? trim($_GET['equipment_id']) : null;
$from         = isset($_GET['from'])         ? trim($_GET['from'])         : null;
$to           = isset($_GET['to'])           ? trim($_GET['to'])           : null;
$all          = isset($_GET['all'])          && $_GET['all'] === '1';

$conditions = [];
$params     = [];
$types      = '';

if ($equipment_id && !$all) {
    $conditions[] = 'equipment_id = ?';
    $params[]     = $equipment_id;
    $types       .= 's';
}

if ($from) {
    $conditions[] = "DATE(added_at_ph) >= ?";
    $params[]     = $from;
    $types       .= 's';
}

if ($to) {
    $conditions[] = "DATE(added_at_ph) <= ?";
    $params[]     = $to;
    $types       .= 's';
}

$where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

$sql  = "SELECT * FROM equipment_log $where ORDER BY added_at_ph DESC";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Query failed: ' . $conn->error]);
    exit;
}

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$rows   = [];

while ($row = $result->fetch_assoc()) {
    // Build human-readable date/time labels (PH time — stored as Asia/Manila in DB)
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $row['added_at_ph']);

    if ($dt) {
        $row['date_label'] = $dt->format('F j, Y');                    // e.g. April 13, 2026
        $row['time_label'] = $dt->format('g:i:s A') . ' PST (UTC+8)'; // e.g. 2:34:15 PM PST (UTC+8)
    } else {
        $row['date_label'] = $row['added_at_ph'];
        $row['time_label'] = '';
    }

    // Decode snapshot if stored as JSON (for Add entries)
    if (isset($row['snapshot']) && $row['snapshot']) {
        $decoded = json_decode($row['snapshot'], true);
        $row['snapshot'] = is_array($decoded) ? $decoded : null;
    } else {
        // Build snapshot from row columns for Add entries
        if ($row['action'] === 'Added' || $row['action'] === 'Edited') {
            $row['snapshot'] = [
                'equipment_name'  => $row['equipment_name'],
                'total_qty'       => $row['total_qty'],
                'working_qty'     => $row['working_qty'],
                'not_working_qty' => $row['not_working_qty'],
                'account_person'  => $row['account_person'],
            ];
        }
    }

    $rows[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'data' => $rows]);
?>