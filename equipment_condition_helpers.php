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
