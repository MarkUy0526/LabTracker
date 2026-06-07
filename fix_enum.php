<?php
session_start();
require 'db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die('Access denied. Admin login required.');
}

echo "<h2>Fix Database ENUM Values</h2>";
echo "<p>Updating status ENUM from ('Pending','Accepted','Rejected') to ('Pending','Approved','Denied')</p>";
echo "<hr>";

// Alter the table to change the ENUM values
$alterSQL = "ALTER TABLE borrow_requests MODIFY status ENUM('Pending','Approved','Denied','Released','Returned','Not Returned') NOT NULL DEFAULT 'Pending'";

if ($conn->query($alterSQL)) {
    echo "<p style='color: green;'><strong>✓ SUCCESS!</strong> ENUM values updated!</p>";
    echo "<p>The status column now supports: Pending, Approved, Denied, Released, Returned, Not Returned</p>";
} else {
    echo "<p style='color: red;'><strong>✗ ERROR:</strong> " . $conn->error . "</p>";
}

echo "<hr>";
echo "<p><a href='admin.php'>Back to Admin Dashboard</a></p>";
?>
