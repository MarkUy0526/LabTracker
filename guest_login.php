<?php
session_start();
require 'db.php';

$date   = new DateTime();
$month  = $date->format("m"); // 2-digit month
$day    = $date->format("d"); // 2-digit day
$year   = $date->format("y"); // 2-digit year
$prefix = $month . $day . $year; // e.g. "041326" for April 13, 2026

// Get the most recent borrower login number for today's prefix
$sql  = "SELECT guest_number FROM guests WHERE guest_number LIKE ? ORDER BY guest_number DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$like = $prefix . "%";
$stmt->bind_param("s", $like);
$stmt->execute();
$result          = $stmt->get_result();
$lastBorrowerNumber = $result->fetch_assoc()['guest_number'] ?? null;
$stmt->close();

if ($lastBorrowerNumber) {
    // Check if this last borrower login number has ever submitted a borrow request.
    // If NOT, reuse it — do not generate a new number and do not insert a new row.
    $check = $conn->prepare("SELECT id FROM borrow_requests WHERE guest_number = ? LIMIT 1");
    $check->bind_param("s", $lastBorrowerNumber);
    $check->execute();
    $check->store_result();
    $hasBorrow = $check->num_rows > 0;
    $check->close();

    if (!$hasBorrow) {
        // Reuse the existing borrower number — no INSERT, no increment
        $_SESSION['guest_id'] = $lastBorrowerNumber;
        echo json_encode(["status" => "success", "guest_id" => $lastBorrowerNumber]);
        exit;
    }

    // Last borrower did borrow — generate the next sequential number for today
    // Sequence is the last 2 characters (positions 6-7), zero-padded to 2 digits
    $lastSeq = (int) substr($lastBorrowerNumber, 6); // e.g. "04132601" → 1
    $newSeq  = str_pad($lastSeq + 1, 2, '0', STR_PAD_LEFT);
} else {
    $newSeq = "01";
}

$borrowerNumber = $prefix . $newSeq; // e.g. "04132601"

$sql  = "INSERT INTO guests (guest_number) VALUES (?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $borrowerNumber);

if ($stmt->execute()) {
    $_SESSION['guest_id'] = $borrowerNumber;
    echo json_encode(["status" => "success", "guest_id" => $borrowerNumber]);
} else {
    echo json_encode(["status" => "error", "message" => "Error generating borrower login number."]);
}

$stmt->close();
?>
