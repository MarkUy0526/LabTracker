<?php
session_start();
require 'db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die('Access denied. Admin login required.');
}

echo "<h2>Database Status Debug</h2>";
echo "<hr>";

// Check all status values in borrow_requests
$result = $conn->query("SELECT status, COUNT(*) as count FROM borrow_requests GROUP BY status ORDER BY status");

echo "<h3>Status Distribution:</h3>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Status</th><th>Count</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr><td>" . htmlspecialchars($row['status'] ?? 'NULL') . "</td><td>" . $row['count'] . "</td></tr>";
}
echo "</table>";

echo "<hr>";

// Show recent requests
echo "<h3>Recent Requests (Last 10):</h3>";
$recentResult = $conn->query("SELECT id, guest_number, borrower_name, status, date FROM borrow_requests ORDER BY id DESC LIMIT 10");

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Guest #</th><th>Borrower</th><th>Status</th><th>Date</th></tr>";
while ($row = $recentResult->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['guest_number']) . "</td>";
    echo "<td>" . htmlspecialchars($row['borrower_name'] ?? 'N/A') . "</td>";
    echo "<td><strong>" . htmlspecialchars($row['status'] ?? 'NULL') . "</strong></td>";
    echo "<td>" . htmlspecialchars($row['date']) . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<p><a href='admin.php'>Back to Admin Dashboard</a></p>";
?>
