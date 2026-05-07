<?php

function ensureInventoryAuditSnapshotTables(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS audit_snapshots (
            id INT PRIMARY KEY AUTO_INCREMENT,
            audit_id INT NOT NULL,
            snapshot_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by VARCHAR(100) NOT NULL,
            exported_by VARCHAR(100) NULL,
            exported_at DATETIME NULL,
            previous_snapshot_id INT NULL,
            item_count INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_audit_snapshot_audit (audit_id),
            KEY idx_snapshot_at (snapshot_at),
            KEY idx_previous_snapshot_id (previous_snapshot_id),
            CONSTRAINT fk_audit_snapshots_audit FOREIGN KEY (audit_id) REFERENCES inventory_audits(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    foreach ([
        'exported_by' => "ALTER TABLE audit_snapshots ADD COLUMN exported_by VARCHAR(100) NULL AFTER created_by",
        'exported_at' => "ALTER TABLE audit_snapshots ADD COLUMN exported_at DATETIME NULL AFTER exported_by",
    ] as $column => $sql) {
        $escapedColumn = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM audit_snapshots LIKE '{$escapedColumn}'");
        if (!$result || $result->num_rows === 0) {
            $conn->query($sql);
        }
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS audit_snapshot_items (
            id INT PRIMARY KEY AUTO_INCREMENT,
            snapshot_id INT NOT NULL,
            audit_id INT NOT NULL,
            equipment_id VARCHAR(100) NOT NULL,
            equipment_name VARCHAR(255) NOT NULL,
            serial_number VARCHAR(255) NULL,
            internal_sn VARCHAR(255) NULL,
            account_person VARCHAR(255) NULL,
            total_qty INT NOT NULL DEFAULT 0,
            working_qty INT NOT NULL DEFAULT 0,
            not_working_qty INT NOT NULL DEFAULT 0,
            maintenance_qty INT NOT NULL DEFAULT 0,
            available INT NOT NULL DEFAULT 0,
            is_borrowable TINYINT(1) NOT NULL DEFAULT 1,
            description TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'Complete',
            notes VARCHAR(500) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_snapshot_equipment (snapshot_id, equipment_id),
            KEY idx_audit_id (audit_id),
            KEY idx_equipment_id (equipment_id),
            CONSTRAINT fk_audit_snapshot_items_snapshot FOREIGN KEY (snapshot_id) REFERENCES audit_snapshots(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function getLatestAuditSnapshot(mysqli $conn, ?int $excludeAuditId = null): ?array
{
    ensureInventoryAuditSnapshotTables($conn);

    if ($excludeAuditId) {
        $stmt = $conn->prepare(
            "SELECT id, audit_id, snapshot_at, created_by, exported_by, exported_at, previous_snapshot_id, item_count
             FROM audit_snapshots
             WHERE audit_id <> ?
             ORDER BY snapshot_at DESC, id DESC
             LIMIT 1"
        );
        $stmt->bind_param('i', $excludeAuditId);
    } else {
        $stmt = $conn->prepare(
            "SELECT id, audit_id, snapshot_at, created_by, exported_by, exported_at, previous_snapshot_id, item_count
             FROM audit_snapshots
             ORDER BY snapshot_at DESC, id DESC
             LIMIT 1"
        );
    }

    if (!$stmt) {
        return null;
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $snapshot = $result->fetch_assoc();
    $stmt->close();

    return $snapshot ?: null;
}

function getAuditSnapshotByAuditId(mysqli $conn, int $auditId): ?array
{
    ensureInventoryAuditSnapshotTables($conn);

    $stmt = $conn->prepare(
        "SELECT id, audit_id, snapshot_at, created_by, exported_by, exported_at, previous_snapshot_id, item_count
         FROM audit_snapshots
         WHERE audit_id = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $auditId);
    $stmt->execute();
    $result = $stmt->get_result();
    $snapshot = $result->fetch_assoc();
    $stmt->close();

    return $snapshot ?: null;
}

function getAuditSnapshotItems(mysqli $conn, int $snapshotId): array
{
    ensureInventoryAuditSnapshotTables($conn);

    $stmt = $conn->prepare(
        "SELECT
            equipment_id,
            equipment_name,
            serial_number,
            internal_sn,
            account_person,
            total_qty,
            working_qty,
            not_working_qty,
            maintenance_qty,
            available,
            is_borrowable,
            description,
            status,
            notes
         FROM audit_snapshot_items
         WHERE snapshot_id = ?
         ORDER BY equipment_name ASC"
    );
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $snapshotId);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[$row['equipment_id']] = normalizeAuditSnapshotItem($row);
    }
    $stmt->close();

    return $items;
}

function normalizeAuditSnapshotItem(array $row): array
{
    return [
        'equipment_id' => $row['equipment_id'],
        'equipment_name' => $row['equipment_name'],
        'serial_number' => $row['serial_number'] ?? '',
        'internal_sn' => $row['internal_sn'] ?? '',
        'account_person' => $row['account_person'] ?? '',
        'total_qty' => (int)($row['total_qty'] ?? 0),
        'working_qty' => (int)($row['working_qty'] ?? 0),
        'not_working_qty' => (int)($row['not_working_qty'] ?? 0),
        'maintenance_qty' => (int)($row['maintenance_qty'] ?? 0),
        'available' => (int)($row['available'] ?? 0),
        'is_borrowable' => (int)($row['is_borrowable'] ?? 1),
        'description' => $row['description'] ?? '',
        'status' => $row['status'] ?? 'Complete',
        'notes' => $row['notes'] ?? '',
    ];
}

function captureAuditSnapshot(mysqli $conn, int $auditId, string $createdBy): int
{
    ensureInventoryAuditSnapshotTables($conn);

    $existing = getAuditSnapshotByAuditId($conn, $auditId);
    if ($existing) {
        return (int)$existing['id'];
    }

    $previous = getLatestAuditSnapshot($conn, $auditId);
    $previousSnapshotId = $previous ? (int)$previous['id'] : null;

    $snapshotStmt = $conn->prepare(
        "INSERT INTO audit_snapshots (audit_id, snapshot_at, created_by, previous_snapshot_id)
         VALUES (?, NOW(), ?, ?)"
    );
    if (!$snapshotStmt) {
        throw new Exception('Could not prepare audit snapshot insert: ' . $conn->error);
    }

    $snapshotStmt->bind_param('isi', $auditId, $createdBy, $previousSnapshotId);
    $snapshotStmt->execute();
    $snapshotId = (int)$snapshotStmt->insert_id;
    $snapshotStmt->close();

    $itemsStmt = $conn->prepare(
        "SELECT
            ai.equipment_id,
            ai.equipment_name,
            ai.actual_qty,
            ai.actual_working_qty,
            ai.actual_not_working_qty,
            ai.actual_maintenance_qty,
            ai.status,
            ai.damage_notes,
            e.serial_number,
            e.internal_sn,
            e.account_person,
            e.available,
            e.is_borrowable,
            e.description
         FROM audit_items ai
         LEFT JOIN equipment e ON e.equipment_id = ai.equipment_id
         WHERE ai.audit_id = ?
         ORDER BY ai.equipment_name ASC"
    );
    if (!$itemsStmt) {
        throw new Exception('Could not prepare audit snapshot item select: ' . $conn->error);
    }

    $itemsStmt->bind_param('i', $auditId);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();

    $insertItemStmt = $conn->prepare(
        "INSERT INTO audit_snapshot_items (
            snapshot_id, audit_id, equipment_id, equipment_name,
            serial_number, internal_sn, account_person,
            total_qty, working_qty, not_working_qty, maintenance_qty,
            available, is_borrowable, description, status, notes
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$insertItemStmt) {
        throw new Exception('Could not prepare audit snapshot item insert: ' . $conn->error);
    }

    $itemCount = 0;
    while ($row = $itemsResult->fetch_assoc()) {
        $equipmentId = $row['equipment_id'];
        $equipmentName = $row['equipment_name'];
        $serialNumber = $row['serial_number'] ?? '';
        $internalSn = $row['internal_sn'] ?? '';
        $accountPerson = $row['account_person'] ?? '';
        $totalQty = max(0, (int)$row['actual_qty']);
        $workingQty = max(0, (int)$row['actual_working_qty']);
        $notWorkingQty = max(0, (int)$row['actual_not_working_qty']);
        $maintenanceQty = max(0, (int)$row['actual_maintenance_qty']);
        $available = max(0, (int)($row['available'] ?? $workingQty));
        $isBorrowable = (int)($row['is_borrowable'] ?? 1);
        $description = $row['description'] ?? '';
        $status = $row['status'] ?? 'Complete';
        $notes = $row['damage_notes'] ?? '';

        $insertItemStmt->bind_param(
            'iisssssiiiiiisss',
            $snapshotId,
            $auditId,
            $equipmentId,
            $equipmentName,
            $serialNumber,
            $internalSn,
            $accountPerson,
            $totalQty,
            $workingQty,
            $notWorkingQty,
            $maintenanceQty,
            $available,
            $isBorrowable,
            $description,
            $status,
            $notes
        );
        $insertItemStmt->execute();
        $itemCount++;
    }

    $insertItemStmt->close();
    $itemsStmt->close();

    $countStmt = $conn->prepare("UPDATE audit_snapshots SET item_count = ? WHERE id = ?");
    if ($countStmt) {
        $countStmt->bind_param('ii', $itemCount, $snapshotId);
        $countStmt->execute();
        $countStmt->close();
    }

    return $snapshotId;
}

function markAuditSnapshotExported(mysqli $conn, int $auditId, string $exportedBy): void
{
    ensureInventoryAuditSnapshotTables($conn);

    $stmt = $conn->prepare(
        "UPDATE audit_snapshots
         SET exported_by = ?, exported_at = NOW()
         WHERE audit_id = ?"
    );
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('si', $exportedBy, $auditId);
    $stmt->execute();
    $stmt->close();
}

?>
