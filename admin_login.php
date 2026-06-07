<?php
session_start(); 
require 'db_pdo.php';

header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM admin_credentials WHERE username = ?");
$stmt->execute([$username]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if ($admin && password_verify($password, $admin['password'])) {
    $_SESSION['user_id'] = $admin['id']; 
    $_SESSION['username'] = $admin['username']; 
    $_SESSION['admin_logged_in'] = true; 

    echo json_encode(["status" => "success"]);
    exit;
} else {
    echo json_encode(["status" => "error", "message" => "Invalid credentials"]);
    exit;
}
