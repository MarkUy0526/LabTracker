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
