<?php
require 'db_pdo.php';

$defaultUsername = 'Admin';
$defaultPassword = password_hash('Admin', PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE admin_credentials SET password = ? WHERE username = ?");
$stmt->execute([$defaultPassword, $defaultUsername]);

echo "Done! Password has been reset to: Admin";
?>