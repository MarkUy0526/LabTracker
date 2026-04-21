<?php
session_start();
header('Content-Type: application/json');

require 'db_pdo.php'; 

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}

if (!isset($_POST['username']) || !isset($_POST['password'])) {
    echo json_encode(['success' => false, 'message' => 'Username or password missing.']);
    exit;
}

$newUsername = trim($_POST['username']);
$newPassword = trim($_POST['password']);
$userId = $_SESSION['user_id'];

if (empty($newUsername) || empty($newPassword)) {
    echo json_encode(['success' => false, 'message' => 'Username and password cannot be empty.']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM admin_credentials WHERE username = ? AND id != ?");
$stmt->execute([$newUsername, $userId]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Username already taken.']);
    exit;
}

$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE admin_credentials SET username = ?, password = ? WHERE id = ?");
$updated = $stmt->execute([$newUsername, $hashedPassword, $userId]);

if ($updated) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update credentials.']);
}
