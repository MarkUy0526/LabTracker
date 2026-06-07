<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
  die('Unauthorized');
}

require 'db.php';

try {
  // Read and execute migration
  $migration = file_get_contents('add_audit_tables.sql');

  // Split by semicolon and execute each statement
  $statements = array_filter(array_map('trim', explode(';', $migration)));

  foreach ($statements as $statement) {
    if (!empty($statement)) {
      if (!$conn->query($statement)) {
        throw new Exception($conn->error);
      }
    }
  }

  echo json_encode([
    'success' => true,
    'message' => 'Audit tables created successfully'
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Error: ' . $e->getMessage()
  ]);
}
?>
