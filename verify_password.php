<?php
session_start();
header('Content-Type: application/json');
require 'db_pdo.php'; 

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}

if (!isset($_POST['password'])) {
    echo json_encode(['success' => false, 'message' => 'Password not provided.']);
    exit;
}

$currentPassword = $_POST['password'];
$adminId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT password FROM admin_credentials WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    echo json_encode(['success' => false, 'message' => 'Admin not found.']);
    exit;
}

if (password_verify($currentPassword, $admin['password'])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Incorrect password.']);
}
