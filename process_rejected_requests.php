<?php
require 'db.php';

$response = ['success' => false, 'updated_items' => 0, 'messages' => []];

$rejectedQuery = $conn->query("SELECT id FROM borrow_requests WHERE status = 'Rejected' AND processed = 0");
$rejectedIDs = [];

while ($row = $rejectedQuery->fetch_assoc()) {
    $rejectedIDs[] = $row['id'];
}
$response['messages'][] = "Rejected request IDs found: " . implode(", ", $rejectedIDs);

foreach ($rejectedIDs as $borrowID) {
    $stmt = $conn->prepare("
        SELECT equipment_name, quantity 
        FROM borrowed_equipment 
        WHERE borrow_request_id = ?
    ");
    $stmt->bind_param("i", $borrowID);
    $stmt->execute();
    $result = $stmt->get_result();

    $processedAny = false;

    while ($item = $result->fetch_assoc()) {
        $equipmentName = trim($item['equipment_name']);
        $qty = (int)$item['quantity'];

        if ($qty > 0) {
            $update = $conn->prepare("
                UPDATE equipment 
                SET available = available + ? 
                WHERE LOWER(equipment_name) = LOWER(?)
            ");
            $update->bind_param("is", $qty, $equipmentName);
            $update->execute();

            if ($update->affected_rows > 0) {
                $response['updated_items']++;
                $response['messages'][] = "Updated: $equipmentName (+$qty)";
                $processedAny = true;
            } else {
                $response['messages'][] = "⚠️ No match for equipment name: $equipmentName";
            }
        } else {
            $response['messages'][] = "⚠️ Quantity is 0 for $equipmentName in request $borrowID";
        }
    }

    if ($processedAny) {
        $markProcessed = $conn->prepare("UPDATE borrow_requests SET processed = 1 WHERE id = ?");
        $markProcessed->bind_param("i", $borrowID);
        $markProcessed->execute();
    }
}

$response['success'] = true;
echo json_encode($response);
?>
