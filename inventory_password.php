<?php
session_start();
require 'db_pdo.php';

header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

$password = $data['password'] ?? '';

if (!isset($_SESSION['username'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$username = $_SESSION['username'];
$stmt = $pdo->prepare("SELECT * FROM admin_credentials WHERE username = ?");
$stmt->execute([$username]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if ($admin && password_verify($password, $admin['password'])) {
    echo json_encode(["status" => "success"]);
    exit;
} else {
    echo json_encode(["status" => "error", "message" => "Incorrect password"]);
    exit;
}
?>
