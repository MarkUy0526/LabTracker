<?php

function ensureEquipmentMaintenanceColumn(mysqli $conn): void
{
    $result = $conn->query("SHOW COLUMNS FROM equipment LIKE 'maintenance_qty'");
    if ($result && $result->num_rows > 0) {
        return;
    }

    $conn->query(
        "ALTER TABLE equipment
         ADD COLUMN maintenance_qty INT NOT NULL DEFAULT 0
         AFTER not_working_qty"
    );
}

function ensureEquipmentInventoryControlColumns(mysqli $conn): void
{
    $columns = [
        'is_borrowable' => "ALTER TABLE equipment ADD COLUMN is_borrowable TINYINT(1) NOT NULL DEFAULT 1 AFTER available",
        'last_imported_at' => "ALTER TABLE equipment ADD COLUMN last_imported_at DATETIME NULL DEFAULT NULL AFTER is_borrowable",
        'last_edited_at' => "ALTER TABLE equipment ADD COLUMN last_edited_at DATETIME NULL DEFAULT NULL AFTER last_imported_at",
    ];

    foreach ($columns as $column => $sql) {
        $escapedColumn = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM equipment LIKE '{$escapedColumn}'");
        if (!$result || $result->num_rows === 0) {
            $conn->query($sql);
        }
    }
}

function ensureInventoryMetadataTable(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS inventory_metadata (
            meta_key VARCHAR(64) NOT NULL PRIMARY KEY,
            meta_value DATETIME NULL DEFAULT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    );
}

function setInventoryMetadata(mysqli $conn, string $key, ?string $value = null): void
{
    ensureInventoryMetadataTable($conn);
    $timestamp = $value ?: date('Y-m-d H:i:s');
    $stmt = $conn->prepare(
        "INSERT INTO inventory_metadata (meta_key, meta_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)"
    );

    if (!$stmt) {
        return;
    }

    $stmt->bind_param('ss', $key, $timestamp);
    $stmt->execute();
    $stmt->close();
}

function getInventoryMetadata(mysqli $conn): array
{
    ensureEquipmentInventoryControlColumns($conn);
    ensureInventoryMetadataTable($conn);

    $metadata = [
        'last_imported_at' => null,
        'last_edited_at' => null,
    ];

    $result = $conn->query(
        "SELECT meta_key, meta_value
         FROM inventory_metadata
         WHERE meta_key IN ('last_imported_at', 'last_edited_at')"
    );
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (array_key_exists($row['meta_key'], $metadata)) {
                $metadata[$row['meta_key']] = $row['meta_value'];
            }
        }
    }

    $fallback = $conn->query(
        "SELECT MAX(last_imported_at) AS last_imported_at,
                MAX(last_edited_at) AS last_edited_at
         FROM equipment"
    );
    if ($fallback) {
        $row = $fallback->fetch_assoc();
        foreach ($metadata as $key => $value) {
            if (!$value && !empty($row[$key])) {
                $metadata[$key] = $row[$key];
            }
        }
    }

    return $metadata;
}

function parseBorrowableFlag($value): int
{
    $text = strtolower(trim((string) $value));

    if ($text === '') {
        return 1;
    }

    $restrictedValues = [
        '0',
        'false',
        'no',
        'n',
        'restricted',
        'hidden',
        'restricted / hidden from borrower side',
        'not borrowable',
        'unavailable',
    ];

    return in_array($text, $restrictedValues, true) ? 0 : 1;
}

function ensureAuditItemConditionColumns(mysqli $conn): void
{
    $columns = [
        'expected_working_qty' => "ALTER TABLE audit_items ADD COLUMN expected_working_qty INT NOT NULL DEFAULT 0 AFTER expected_qty",
        'expected_not_working_qty' => "ALTER TABLE audit_items ADD COLUMN expected_not_working_qty INT NOT NULL DEFAULT 0 AFTER expected_working_qty",
        'expected_maintenance_qty' => "ALTER TABLE audit_items ADD COLUMN expected_maintenance_qty INT NOT NULL DEFAULT 0 AFTER expected_not_working_qty",
        'actual_working_qty' => "ALTER TABLE audit_items ADD COLUMN actual_working_qty INT NOT NULL DEFAULT 0 AFTER actual_qty",
        'actual_not_working_qty' => "ALTER TABLE audit_items ADD COLUMN actual_not_working_qty INT NOT NULL DEFAULT 0 AFTER actual_working_qty",
        'actual_maintenance_qty' => "ALTER TABLE audit_items ADD COLUMN actual_maintenance_qty INT NOT NULL DEFAULT 0 AFTER actual_not_working_qty",
    ];

    foreach ($columns as $column => $sql) {
        $escapedColumn = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM audit_items LIKE '{$escapedColumn}'");
        if (!$result || $result->num_rows === 0) {
            $conn->query($sql);
        }
    }
}

function allocateConditionQuantities(int $total, int $working, int $notWorking, int $maintenance): array
{
    $total = max(0, $total);
    $working = max(0, $working);
    $notWorking = max(0, $notWorking);
    $maintenance = max(0, $maintenance);
    $basisTotal = $working + $notWorking + $maintenance;

    if ($total === 0) {
        return [0, 0, 0];
    }

    if ($basisTotal === 0) {
        return [$total, 0, 0];
    }

    $nextWorking = (int) round($total * ($working / $basisTotal));
    $nextNotWorking = (int) round($total * ($notWorking / $basisTotal));
    $nextMaintenance = (int) round($total * ($maintenance / $basisTotal));

    $diff = $total - ($nextWorking + $nextNotWorking + $nextMaintenance);
    if ($diff > 0) {
        $nextWorking += $diff;
    } elseif ($diff < 0) {
        $remaining = abs($diff);
        foreach (['nextWorking', 'nextNotWorking', 'nextMaintenance'] as $varName) {
            $take = min($$varName, $remaining);
            $$varName -= $take;
            $remaining -= $take;
            if ($remaining === 0) {
                break;
            }
        }
    }

    return [$nextWorking, $nextNotWorking, $nextMaintenance];
}

function inventoryConditionFields(): array
{
    return [
        'total' => ['column' => 'total_qty', 'label' => 'Total'],
        'working' => ['column' => 'working_qty', 'label' => 'Working'],
        'notWorking' => ['column' => 'not_working_qty', 'label' => 'Non-working'],
        'maintenance' => ['column' => 'maintenance_qty', 'label' => 'Maintenance'],
    ];
}

function getInventoryConditionField(?string $field): ?array
{
    $fields = inventoryConditionFields();
    return $fields[$field ?? ''] ?? null;
}
