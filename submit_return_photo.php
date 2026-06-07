<?php
require 'db.php';
require 'return_photo_helpers.php';

header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    ensureReturnPhotoColumns($conn);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
        exit;
    }

    $borrowRequestId = (int)($_POST['borrow_request_id'] ?? 0);
    $guestNumber = trim($_POST['guest_number'] ?? '');

    if ($borrowRequestId <= 0 || $guestNumber === '') {
        echo json_encode(['success' => false, 'message' => 'Missing return request details.']);
        exit;
    }

    if (!isset($_FILES['return_photo']) || $_FILES['return_photo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Please upload a clear return photo.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, status FROM borrow_requests WHERE id = ? AND guest_number = ?");
    $stmt->bind_param("is", $borrowRequestId, $guestNumber);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();

    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Borrow request not found for this Login ID.']);
        exit;
    }

    if ($request['status'] !== 'Approved') {
        echo json_encode(['success' => false, 'message' => 'Only approved borrowed equipment can be submitted for return verification.']);
        exit;
    }

    $file = $_FILES['return_photo'];
    if ($file['size'] > 8 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Photo must be 8MB or smaller.']);
        exit;
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if (!$imageInfo || empty($imageInfo['mime'])) {
        echo json_encode(['success' => false, 'message' => 'Uploaded file must be a valid image.']);
        exit;
    }

    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($extensions[$imageInfo['mime']])) {
        echo json_encode(['success' => false, 'message' => 'Photo must be JPG, PNG, WEBP, or GIF.']);
        exit;
    }

    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'return_photos';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Unable to create return photo storage.']);
        exit;
    }

    $fileName = 'return_' . $borrowRequestId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extensions[$imageInfo['mime']];
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
    $relativePath = 'return_photos/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        echo json_encode(['success' => false, 'message' => 'Unable to save uploaded photo.']);
        exit;
    }

    $status = 'Pending Verification';
    $update = $conn->prepare("
        UPDATE borrow_requests
        SET return_photo_path = ?,
            return_submitted_at = NOW(),
            return_verification_status = ?,
            return_verified_at = NULL
        WHERE id = ? AND guest_number = ?
    ");
    $update->bind_param("ssis", $relativePath, $status, $borrowRequestId, $guestNumber);
    $update->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Return photo submitted. Your return is pending verification.',
        'photo_path' => $relativePath,
        'verification_status' => $status
    ]);
} catch (Throwable $e) {
    error_log('Return photo upload error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error submitting return photo.']);
}

