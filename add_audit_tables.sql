-- ========================================
-- INVENTORY AUDIT TABLES
-- ========================================

-- Main audit records
CREATE TABLE IF NOT EXISTS inventory_audits (
  id INT PRIMARY KEY AUTO_INCREMENT,
  audit_date DATE NOT NULL,
  admin_name VARCHAR(100) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'Draft',
  total_items INT DEFAULT 0,
  complete_count INT DEFAULT 0,
  missing_count INT DEFAULT 0,
  damaged_count INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  submitted_at DATETIME NULL,
  notes TEXT NULL,
  KEY idx_status (status),
  KEY idx_audit_date (audit_date),
  KEY idx_admin_name (admin_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Individual audit items (one audit can have many items)
CREATE TABLE IF NOT EXISTS audit_items (
  id INT PRIMARY KEY AUTO_INCREMENT,
  audit_id INT NOT NULL,
  equipment_id VARCHAR(100) NOT NULL,
  equipment_name VARCHAR(255) NOT NULL,
  expected_qty INT NOT NULL,
  expected_working_qty INT NOT NULL DEFAULT 0,
  expected_not_working_qty INT NOT NULL DEFAULT 0,
  expected_maintenance_qty INT NOT NULL DEFAULT 0,
  actual_qty INT NOT NULL,
  actual_working_qty INT NOT NULL DEFAULT 0,
  actual_not_working_qty INT NOT NULL DEFAULT 0,
  actual_maintenance_qty INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'Complete',
  damage_notes VARCHAR(500) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_audit_id (audit_id),
  KEY idx_equipment_id (equipment_id),
  KEY idx_status (status),
  CONSTRAINT fk_audit_items_audit FOREIGN KEY (audit_id) REFERENCES inventory_audits(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permanent official audit snapshots. Each submitted audit gets one snapshot.
CREATE TABLE IF NOT EXISTS audit_snapshots (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_snapshot_items (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
