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
