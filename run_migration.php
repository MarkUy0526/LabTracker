<?php
require 'db.php';

header('Content-Type: text/html; charset=UTF-8');

echo "<h2>Database Migration - Add Department Column</h2>";

try {
    // Check if column exists
    $result = $conn->query("SHOW COLUMNS FROM borrow_requests LIKE 'department'");

    if ($result && $result->num_rows > 0) {
        echo "<p style='color: green;'><strong>✓ Department column already exists!</strong></p>";
    } else {
        // Column doesn't exist, add it
        $sql = "ALTER TABLE borrow_requests ADD COLUMN department VARCHAR(100) DEFAULT NULL";

        if ($conn->query($sql) === TRUE) {
            echo "<p style='color: green;'><strong>✓ Success!</strong> Department column has been added to borrow_requests table.</p>";
        } else {
            echo "<p style='color: red;'><strong>✗ Error:</strong> " . $conn->error . "</p>";
        }
    }

    echo "<p><a href='guest.php'>&larr; Back to Borrower Form</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
}

$conn->close();
?>
