<?php
session_start();
require 'db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die('Access denied. Admin login required.');
}

echo "<h2>Database Status Migration</h2>";
echo "<p>Migrating old status values to new ones...</p><hr>";

// Migrate old status values to new ones
$updates = [
    'Accepted' => 'Approved',
    'Rejected' => 'Denied'
];

foreach ($updates as $oldStatus => $newStatus) {
    $sql = "UPDATE borrow_requests SET status = ? WHERE status = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $newStatus, $oldStatus);

    if ($stmt->execute()) {
        $count = $stmt->affected_rows;
        echo "<p style='color: green;'>✓ Updated <strong>$count</strong> records: '<strong>$oldStatus</strong>' → '<strong>$newStatus</strong>'</p>";
    } else {
        echo "<p style='color: red;'>✗ Error updating $oldStatus: " . $conn->error . "</p>";
    }
}

echo "<hr><p><strong>Migration complete!</strong></p>";
echo "<p><a href='admin.php'>Back to Admin Dashboard</a> | <a href='login.php'>Back to Login</a></p>";
?>
