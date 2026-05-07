<?php
session_start();
require 'db.php';
require 'equipment_condition_helpers.php';

header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

ensureEquipmentMaintenanceColumn($conn);
ensureEquipmentInventoryControlColumns($conn);

$equipmentID = trim($_POST['equipmentID'] ?? '');
$fieldKey = trim($_POST['field'] ?? '');
$quantityRaw = trim($_POST['quantity'] ?? '');
$field = getInventoryConditionField($fieldKey);

if ($equipmentID === '' || $field === null || !ctype_digit($quantityRaw)) {
    echo json_encode(['success' => false, 'message' => 'Invalid equipment ID, condition field, or quantity.']);
    exit;
}

$quantity = (int) $quantityRaw;
$stmt = $conn->prepare(
    "SELECT total_qty, working_qty, not_working_qty, maintenance_qty, available
     FROM equipment WHERE equipment_id = ? LIMIT 1"
);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param('s', $equipmentID);
$stmt->execute();
$result = $stmt->get_result();
$current = $result->fetch_assoc();
$stmt->close();

if (!$current) {
    echo json_encode(['success' => false, 'message' => 'Equipment not found.']);
    exit;
}

$next = [
    'total_qty' => (int) $current['total_qty'],
    'working_qty' => (int) $current['working_qty'],
    'not_working_qty' => (int) $current['not_working_qty'],
    'maintenance_qty' => (int) $current['maintenance_qty'],
];

$column = $field['column'];
$oldValue = $next[$column];
$next[$column] = $quantity;

$conditionSum = $next['working_qty'] + $next['not_working_qty'] + $next['maintenance_qty'];
if ($conditionSum > $next['total_qty']) {
    echo json_encode([
        'success' => false,
        'message' => 'Working + Non-working + Maintenance cannot exceed Total.'
    ]);
    exit;
}

$borrowedQty = max(0, (int) $current['working_qty'] - (int) $current['available']);
if ($column === 'working_qty' && $quantity < $borrowedQty) {
    echo json_encode([
        'success' => false,
        'message' => 'Working quantity cannot be lower than currently borrowed quantity.'
    ]);
    exit;
}

if ($oldValue === $quantity) {
    echo json_encode(['success' => true, 'message' => 'Condition quantity unchanged.', 'quantity' => $quantity]);
    exit;
}

$sql = "UPDATE equipment SET $column = ?, last_edited_at = ?";
$available = null;
if ($column === 'working_qty') {
    $sql = "UPDATE equipment SET $column = ?, available = ?, last_edited_at = ?";
}
$sql .= " WHERE equipment_id = ?";

$updateStmt = $conn->prepare($sql);
if (!$updateStmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$editedAt = date('Y-m-d H:i:s');
if ($column === 'working_qty') {
    $available = $quantity - $borrowedQty;
    $updateStmt->bind_param('iiss', $quantity, $available, $editedAt, $equipmentID);
} else {
    $updateStmt->bind_param('iss', $quantity, $editedAt, $equipmentID);
}

if (!$updateStmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Update failed: ' . $updateStmt->error]);
    $updateStmt->close();
    exit;
}
$updateStmt->close();
setInventoryMetadata($conn, 'last_edited_at', $editedAt);

$logStmt = $conn->prepare(
    "INSERT INTO equipment_history
     (equipment_id, action, changed_field, old_value, new_value, performed_at)
     VALUES (?, 'Edited', ?, ?, ?, ?)"
);

if ($logStmt) {
    $label = $field['label'] . ' Qty';
    $oldValueString = (string) $oldValue;
    $newValueString = (string) $quantity;
    $now = date('Y-m-d H:i:s');
    $logStmt->bind_param('sssss', $equipmentID, $label, $oldValueString, $newValueString, $now);
    $logStmt->execute();
    $logStmt->close();
}

$conn->close();
echo json_encode([
    'success' => true,
    'field' => $fieldKey,
    'quantity' => $quantity,
    'available' => $available,
    'totals' => $next,
]);
