<?php
header('Content-Type: application/json');
require 'db.php';

// Get search term and status filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'All';

try {
    // Base query to get all equipment with borrowing info
    $query = "
    SELECT
        e.equipment_id,
        e.equipment_name,
        e.total_qty,
        e.working_qty,
        e.available,
        COALESCE((
            SELECT SUM(be.quantity)
            FROM borrowed_equipment be
            JOIN borrow_requests br ON be.borrow_request_id = br.id
            WHERE be.equipment_name = e.equipment_name
            AND br.status IN ('Approved', 'Released')
            AND be.returned_on IS NULL
        ), 0) AS borrowed_qty,
        (
            SELECT br.borrower_name
            FROM borrow_requests br
            JOIN borrowed_equipment be ON be.borrow_request_id = br.id
            WHERE be.equipment_name = e.equipment_name
            AND br.status IN ('Approved', 'Released')
            AND be.returned_on IS NULL
            ORDER BY br.date DESC
            LIMIT 1
        ) AS current_borrower,
        (
            SELECT br.date
            FROM borrow_requests br
            JOIN borrowed_equipment be ON be.borrow_request_id = br.id
            WHERE be.equipment_name = e.equipment_name
            AND br.status IN ('Approved', 'Released')
            AND be.returned_on IS NULL
            ORDER BY br.date DESC
            LIMIT 1
        ) AS date_borrowed,
        (
            SELECT br.usage_date
            FROM borrow_requests br
            JOIN borrowed_equipment be ON be.borrow_request_id = br.id
            WHERE be.equipment_name = e.equipment_name
            AND br.status IN ('Approved', 'Released')
            AND be.returned_on IS NULL
            ORDER BY br.date DESC
            LIMIT 1
        ) AS expected_return_date
    FROM equipment e
    WHERE 1=1
    ";

    // Add search filter
    if ($search !== '') {
        $query .= " AND (e.equipment_name LIKE ? OR e.equipment_id LIKE ?)";
    }

    $query .= " ORDER BY e.equipment_name";

    // Prepare statement
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    // Bind search parameters if provided
    if ($search !== '') {
        $searchTerm = '%' . $search . '%';
        $stmt->bind_param('ss', $searchTerm, $searchTerm);
    }

    // Execute
    $stmt->execute();
    $result = $stmt->get_result();

    $equipment = [];
    while ($row = $result->fetch_assoc()) {
        // Calculate status
        $totalQty = (int)$row['total_qty'];
        $borrowedQty = (int)$row['borrowed_qty'];

        if ($borrowedQty === 0) {
            $status = 'Available';
        } elseif ($borrowedQty === $totalQty) {
            $status = 'Fully Borrowed';
        } elseif ($borrowedQty > 0 && $borrowedQty < $totalQty) {
            $status = 'Partially Borrowed';
        } else {
            $status = 'Available';
        }

        // Check for image
        $equipmentId = $row['equipment_id'];
        $photoUrl = null;

        if (file_exists(__DIR__ . '/equipment_images/' . $equipmentId . '.jpg')) {
            $photoUrl = 'equipment_images/' . $equipmentId . '.jpg';
        } elseif (file_exists(__DIR__ . '/equipment_images/' . $equipmentId . '.png')) {
            $photoUrl = 'equipment_images/' . $equipmentId . '.png';
        } elseif (file_exists(__DIR__ . '/equipment_images/' . $equipmentId . '.webp')) {
            $photoUrl = 'equipment_images/' . $equipmentId . '.webp';
        }

        // Apply status filter
        if ($statusFilter !== 'All' && $status !== $statusFilter) {
            continue;
        }

        $equipment[] = [
            'equipment_id' => $row['equipment_id'],
            'equipment_name' => $row['equipment_name'],
            'total_qty' => $totalQty,
            'available_qty' => $totalQty - $borrowedQty,
            'borrowed_qty' => $borrowedQty,
            'status' => $status,
            'current_borrower' => $row['current_borrower'],
            'date_borrowed' => $row['date_borrowed'],
            'expected_return_date' => $row['expected_return_date'],
            'photo_url' => $photoUrl
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $equipment,
        'total' => count($equipment)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
