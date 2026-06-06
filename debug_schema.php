<?php
session_start();
require 'db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die('Access denied. Admin login required.');
}

echo "<h2>Database Schema Debug</h2>";
echo "<hr>";

// Check the structure of borrow_requests table
echo "<h3>borrow_requests Table Structure:</h3>";
$result = $conn->query("DESCRIBE borrow_requests");

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Key'] ?? '') . "</td>";
    echo "<td>" . htmlspecialchars($row['Default'] ?? 'None') . "</td>";
    echo "<td>" . htmlspecialchars($row['Extra'] ?? '') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<p><a href='admin.php'>Back to Admin Dashboard</a></p>";
?>
