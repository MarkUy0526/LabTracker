<?php
session_start();
require 'db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die('Access denied. Admin login required.');
}

echo "<h2>Fix NULL Status Records</h2>";
echo "<hr>";

// First, set all NULL status records to 'Pending'
$updateSQL = "UPDATE borrow_requests SET status = 'Pending' WHERE status IS NULL OR status = ''";

if ($conn->query($updateSQL)) {
    $affectedRows = $conn->affected_rows;
    echo "<p style='color: green;'><strong>✓ SUCCESS!</strong> Updated $affectedRows records to 'Pending'</p>";
} else {
    echo "<p style='color: red;'><strong>✗ ERROR:</strong> " . $conn->error . "</p>";
}

// Show current status distribution
echo "<hr>";
echo "<h3>Current Status Distribution:</h3>";
$result = $conn->query("SELECT status, COUNT(*) as count FROM borrow_requests GROUP BY status ORDER BY status");

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Status</th><th>Count</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr><td>" . htmlspecialchars($row['status'] ?? 'NULL') . "</td><td>" . $row['count'] . "</td></tr>";
}
echo "</table>";

echo "<hr>";
echo "<p><strong>Next steps:</strong></p>";
echo "<ol>";
echo "<li>Go to <strong>Borrow Requests</strong> to see the pending requests</li>";
echo "<li>Click <strong>Approved</strong> or <strong>Denied</strong> on some requests</li>";
echo "<li>Go to <strong>Reports</strong> to see them appear</li>";
echo "</ol>";
echo "<p><a href='admin.php'>Back to Admin Dashboard</a></p>";
?>
