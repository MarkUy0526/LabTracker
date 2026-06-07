<?php

function ensureReturnPhotoColumns(mysqli $conn): void
{
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM borrow_requests");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = true;
        }
    }

    $alterStatements = [];
    $addedInventoryRestoredColumn = false;

    if (!isset($columns['return_photo_path'])) {
        $alterStatements[] = "ADD COLUMN return_photo_path VARCHAR(255) NULL DEFAULT NULL";
    }
    if (!isset($columns['return_submitted_at'])) {
        $alterStatements[] = "ADD COLUMN return_submitted_at DATETIME NULL DEFAULT NULL";
    }
    if (!isset($columns['return_verification_status'])) {
        $alterStatements[] = "ADD COLUMN return_verification_status VARCHAR(40) NOT NULL DEFAULT 'Pending Verification'";
    }
    if (!isset($columns['return_verified_at'])) {
        $alterStatements[] = "ADD COLUMN return_verified_at DATETIME NULL DEFAULT NULL";
    }
    if (!isset($columns['return_verification_notes'])) {
        $alterStatements[] = "ADD COLUMN return_verification_notes TEXT NULL";
    }
    if (!isset($columns['return_inventory_restored'])) {
        $alterStatements[] = "ADD COLUMN return_inventory_restored TINYINT(1) NOT NULL DEFAULT 0";
        $addedInventoryRestoredColumn = true;
    }
    if (!isset($columns['return_status'])) {
        $alterStatements[] = "ADD COLUMN return_status VARCHAR(20) NULL DEFAULT NULL";
    }

    if ($alterStatements) {
        $conn->query("ALTER TABLE borrow_requests " . implode(", ", $alterStatements));
    }

    if ($addedInventoryRestoredColumn) {
        $conn->query("
            UPDATE borrow_requests br
            SET br.return_inventory_restored = 1
            WHERE EXISTS (
                SELECT 1 FROM borrowed_equipment be
                WHERE be.borrow_request_id = br.id
            )
            AND NOT EXISTS (
                SELECT 1 FROM borrowed_equipment be2
                WHERE be2.borrow_request_id = br.id
                  AND (be2.returned_on IS NULL OR be2.returned_on = '')
            )
        ");
    }
}

function normalizeReturnVerificationStatus(?string $status): string
{
    $status = trim((string)$status);
    $allowed = ['Pending Verification', 'Verified', 'Return Issue Detected'];
    return in_array($status, $allowed, true) ? $status : 'Pending Verification';
}

function hasReturnPhoto(array $request): bool
{
    return !empty($request['return_photo_path']);
}

function normalizeReturnStatus(?string $status): ?string
{
    $status = trim((string)$status);
    $allowed = ['Returned', 'Not Returned'];
    return in_array($status, $allowed, true) ? $status : null;
}

function remarksIndicateReturnIssue(?string $remarks): bool
{
    $value = strtolower(trim((string)$remarks));
    if ($value === '') {
        return false;
    }

    foreach (['lost', 'not working', 'incomplete', 'issue'] as $needle) {
        if (strpos($value, $needle) !== false) {
            return true;
        }
    }

    return false;
}

function determineReturnStatus(array $request, array $returnedItems): ?string
{
    if (!$returnedItems) {
        return null;
    }

    $allReturned = true;
    foreach ($returnedItems as $item) {
        $returnedOn = trim((string)($item['returned_on'] ?? ''));
        $remarks = (string)($item['remarks'] ?? '');

        if ($returnedOn === '' || remarksIndicateReturnIssue($remarks)) {
            $allReturned = false;
        }
    }

    if ($allReturned) {
        return 'Returned';
    }

    $usageDate = trim((string)($request['usage_date'] ?? ''));
    if ($usageDate !== '' && substr($usageDate, 0, 10) < date('Y-m-d')) {
        return 'Not Returned';
    }

    foreach ($returnedItems as $item) {
        if (remarksIndicateReturnIssue($item['remarks'] ?? '')) {
            return 'Not Returned';
        }
    }

    return null;
}

function updateOverdueReturnStatuses(mysqli $conn): void
{
    $conn->query("
        UPDATE borrow_requests br
        SET br.return_status = 'Not Returned'
        WHERE br.status = 'Approved'
          AND br.usage_date IS NOT NULL
          AND DATE(br.usage_date) < CURDATE()
          AND (br.return_status IS NULL OR br.return_status = '')
          AND EXISTS (
              SELECT 1 FROM borrowed_equipment be
              WHERE be.borrow_request_id = br.id
                AND (be.returned_on IS NULL OR be.returned_on = '')
          )
    ");
}
