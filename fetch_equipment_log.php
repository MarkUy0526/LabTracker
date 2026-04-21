<?php
require 'db.php';
header('Content-Type: application/json');

date_default_timezone_set('Asia/Manila');

// Optional filter: ?equipment_id=E001 scopes to one item
$equipmentID = trim($_GET['equipment_id'] ?? '');

// Optional: ?limit=N (default 100, max 500)
$limit = min(500, max(1, (int) ($_GET['limit'] ?? 100)));

if ($equipmentID !== '') {
    $stmt = $conn->prepare(
        "SELECT
            h.id,
            h.equipment_id,
            e.equipment_name,
            h.action,
            h.changed_field,
            h.old_value,
            h.new_value,
            DATE_FORMAT(h.performed_at, '%M %d, %Y')  AS date_label,
            DATE_FORMAT(h.performed_at, '%h:%i %p')    AS time_label,
            h.performed_at
         FROM equipment_history h
         LEFT JOIN equipment e ON e.equipment_id = h.equipment_id
         WHERE h.equipment_id = ?
         ORDER BY h.performed_at DESC
         LIMIT ?"
    );
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => $conn->error]);
        exit;
    }
    $stmt->bind_param("si", $equipmentID, $limit);
} else {
    // Global — all equipment
    $stmt = $conn->prepare(
        "SELECT
            h.id,
            h.equipment_id,
            e.equipment_name,
            h.action,
            h.changed_field,
            h.old_value,
            h.new_value,
            DATE_FORMAT(h.performed_at, '%M %d, %Y')  AS date_label,
            DATE_FORMAT(h.performed_at, '%h:%i %p')    AS time_label,
            h.performed_at
         FROM equipment_history h
         LEFT JOIN equipment e ON e.equipment_id = h.equipment_id
         ORDER BY h.performed_at DESC
         LIMIT ?"
    );
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => $conn->error]);
        exit;
    }
    $stmt->bind_param("i", $limit);
}

$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    // For Added rows, decode the JSON snapshot for the frontend
    if ($row['action'] === 'Added' && !empty($row['new_value'])) {
        $decoded = json_decode($row['new_value'], true);
        $row['snapshot'] = is_array($decoded) ? $decoded : [];
    }
    $rows[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'data' => $rows]);
?>
