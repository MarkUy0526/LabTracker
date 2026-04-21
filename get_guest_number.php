<?php
session_start();

if (isset($_SESSION['guest_id'])) {
    echo json_encode(['guest_number' => $_SESSION['guest_id']]);
} else {
    echo json_encode(['guest_number' => null]);
}
?>