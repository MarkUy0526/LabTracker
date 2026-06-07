<?php
require 'db.php';

if (!isset($conn) || !$conn) {
    echo "No connection object available.\n";
    exit;
}

if ($conn->connect_error) {
    echo "Connection failed: " . $conn->connect_error;
} else {
    echo "Connected to database '" . (isset($dbname) ? $dbname : 'unknown') . "' successfully.\n";
}

$conn->close();
?>
