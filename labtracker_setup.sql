-- Labtracker consolidated setup/upgrade script.
-- Built from the provided phpMyAdmin dump plus the schema additions used by the current app.
-- Safe to run on an empty database, and safe to rerun for missing columns/tables.

CREATE DATABASE IF NOT EXISTS `labtracker`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `labtracker`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `admin_credentials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `equipment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_id` varchar(50) NOT NULL,
  `equipment_name` varchar(255) NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `internal_sn` varchar(100) DEFAULT NULL,
  `account_person` varchar(255) DEFAULT NULL,
  `total_qty` int(11) NOT NULL DEFAULT 0,
  `working_qty` int(11) NOT NULL DEFAULT 0,
  `not_working_qty` int(11) NOT NULL DEFAULT 0,
  `maintenance_qty` int(11) NOT NULL DEFAULT 0,
  `available` int(11) NOT NULL DEFAULT 0,
  `is_borrowable` tinyint(1) NOT NULL DEFAULT 1,
  `last_imported_at` datetime DEFAULT NULL,
  `last_edited_at` datetime DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `equipment_id` (`equipment_id`),
  KEY `idx_equipment_id` (`equipment_id`),
  KEY `idx_equipment_name` (`equipment_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `guests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guest_number` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `guest_number` (`guest_number`),
  KEY `idx_guest_number` (`guest_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `instructors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `instructor_name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_number` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `borrow_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guest_number` varchar(20) NOT NULL,
  `date` date DEFAULT NULL,
  `borrower_name` varchar(255) NOT NULL,
  `instructor_name` varchar(255) DEFAULT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `subject_code` varchar(50) DEFAULT NULL,
  `usage_date` date DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `room` varchar(100) DEFAULT NULL,
  `status` enum('Pending','Approved','Denied','Released','Returned','Not Returned','Accepted','Rejected') NOT NULL DEFAULT 'Pending',
  `processed` tinyint(1) NOT NULL DEFAULT 0,
  `return_photo_path` varchar(255) DEFAULT NULL,
  `return_submitted_at` datetime DEFAULT NULL,
  `return_verification_status` varchar(40) NOT NULL DEFAULT 'Pending Verification',
  `return_verified_at` datetime DEFAULT NULL,
  `return_verification_notes` text DEFAULT NULL,
  `return_inventory_restored` tinyint(1) NOT NULL DEFAULT 0,
  `return_status` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_usage_date` (`usage_date`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_guest_number` (`guest_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `borrowed_equipment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `borrow_request_id` int(11) NOT NULL,
  `equipment_id` varchar(50) DEFAULT NULL,
  `equipment_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `available` varchar(10) DEFAULT 'YES',
  `returned_on` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_borrow_request_id` (`borrow_request_id`),
  KEY `idx_equipment_name` (`equipment_name`),
  CONSTRAINT `fk_borrowed_request`
    FOREIGN KEY (`borrow_request_id`) REFERENCES `borrow_requests` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Upgrade columns for databases created from the older dump.
ALTER TABLE `equipment`
  ADD COLUMN IF NOT EXISTS `maintenance_qty` int(11) NOT NULL DEFAULT 0 AFTER `not_working_qty`,
  ADD COLUMN IF NOT EXISTS `is_borrowable` tinyint(1) NOT NULL DEFAULT 1 AFTER `available`,
  ADD COLUMN IF NOT EXISTS `last_imported_at` datetime DEFAULT NULL AFTER `is_borrowable`,
  ADD COLUMN IF NOT EXISTS `last_edited_at` datetime DEFAULT NULL AFTER `last_imported_at`;

ALTER TABLE `borrow_requests`
  MODIFY COLUMN `status` enum('Pending','Approved','Denied','Released','Returned','Not Returned','Accepted','Rejected') NOT NULL DEFAULT 'Pending',
  ADD COLUMN IF NOT EXISTS `department` varchar(100) DEFAULT NULL AFTER `usage_date`,
  ADD COLUMN IF NOT EXISTS `return_photo_path` varchar(255) DEFAULT NULL AFTER `processed`,
  ADD COLUMN IF NOT EXISTS `return_submitted_at` datetime DEFAULT NULL AFTER `return_photo_path`,
  ADD COLUMN IF NOT EXISTS `return_verification_status` varchar(40) NOT NULL DEFAULT 'Pending Verification' AFTER `return_submitted_at`,
  ADD COLUMN IF NOT EXISTS `return_verified_at` datetime DEFAULT NULL AFTER `return_verification_status`,
  ADD COLUMN IF NOT EXISTS `return_verification_notes` text DEFAULT NULL AFTER `return_verified_at`,
  ADD COLUMN IF NOT EXISTS `return_inventory_restored` tinyint(1) NOT NULL DEFAULT 0 AFTER `return_verification_notes`,
  ADD COLUMN IF NOT EXISTS `return_status` varchar(20) DEFAULT NULL AFTER `return_inventory_restored`;

UPDATE `borrow_requests` SET `status` = 'Approved' WHERE `status` = 'Accepted';
UPDATE `borrow_requests` SET `status` = 'Denied' WHERE `status` = 'Rejected';

ALTER TABLE `borrow_requests`
  MODIFY COLUMN `status` enum('Pending','Approved','Denied','Released','Returned','Not Returned') NOT NULL DEFAULT 'Pending';

CREATE TABLE IF NOT EXISTS `inventory_metadata` (
  `meta_key` varchar(64) NOT NULL,
  `meta_value` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`meta_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `equipment_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_id` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `changed_field` varchar(100) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `performed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_equipment_history_equipment_id` (`equipment_id`),
  KEY `idx_equipment_history_performed_at` (`performed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `equipment_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_id` varchar(50) NOT NULL,
  `equipment_name` varchar(255) NOT NULL,
  `total_qty` int(11) NOT NULL DEFAULT 0,
  `working_qty` int(11) NOT NULL DEFAULT 0,
  `not_working_qty` int(11) NOT NULL DEFAULT 0,
  `account_person` varchar(255) DEFAULT NULL,
  `action` varchar(50) NOT NULL DEFAULT 'Added',
  `added_by` varchar(100) DEFAULT NULL,
  `added_at_ph` datetime NOT NULL,
  `snapshot` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_equipment_log_equipment_id` (`equipment_id`),
  KEY `idx_equipment_log_added_at_ph` (`added_at_ph`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_audits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `audit_date` date NOT NULL,
  `admin_name` varchar(100) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Draft',
  `total_items` int(11) DEFAULT 0,
  `complete_count` int(11) DEFAULT 0,
  `missing_count` int(11) DEFAULT 0,
  `damaged_count` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `submitted_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_audit_date` (`audit_date`),
  KEY `idx_admin_name` (`admin_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `audit_id` int(11) NOT NULL,
  `equipment_id` varchar(100) NOT NULL,
  `equipment_name` varchar(255) NOT NULL,
  `expected_qty` int(11) NOT NULL,
  `expected_working_qty` int(11) NOT NULL DEFAULT 0,
  `expected_not_working_qty` int(11) NOT NULL DEFAULT 0,
  `expected_maintenance_qty` int(11) NOT NULL DEFAULT 0,
  `actual_qty` int(11) NOT NULL,
  `actual_working_qty` int(11) NOT NULL DEFAULT 0,
  `actual_not_working_qty` int(11) NOT NULL DEFAULT 0,
  `actual_maintenance_qty` int(11) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'Complete',
  `damage_notes` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_id` (`audit_id`),
  KEY `idx_equipment_id` (`equipment_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_audit_items_audit`
    FOREIGN KEY (`audit_id`) REFERENCES `inventory_audits` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_snapshots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `audit_id` int(11) NOT NULL,
  `snapshot_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(100) NOT NULL,
  `exported_by` varchar(100) DEFAULT NULL,
  `exported_at` datetime DEFAULT NULL,
  `previous_snapshot_id` int(11) DEFAULT NULL,
  `item_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_audit_snapshot_audit` (`audit_id`),
  KEY `idx_snapshot_at` (`snapshot_at`),
  KEY `idx_previous_snapshot_id` (`previous_snapshot_id`),
  CONSTRAINT `fk_audit_snapshots_audit`
    FOREIGN KEY (`audit_id`) REFERENCES `inventory_audits` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_snapshot_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `snapshot_id` int(11) NOT NULL,
  `audit_id` int(11) NOT NULL,
  `equipment_id` varchar(100) NOT NULL,
  `equipment_name` varchar(255) NOT NULL,
  `serial_number` varchar(255) DEFAULT NULL,
  `internal_sn` varchar(255) DEFAULT NULL,
  `account_person` varchar(255) DEFAULT NULL,
  `total_qty` int(11) NOT NULL DEFAULT 0,
  `working_qty` int(11) NOT NULL DEFAULT 0,
  `not_working_qty` int(11) NOT NULL DEFAULT 0,
  `maintenance_qty` int(11) NOT NULL DEFAULT 0,
  `available` int(11) NOT NULL DEFAULT 0,
  `is_borrowable` tinyint(1) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Complete',
  `notes` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_snapshot_equipment` (`snapshot_id`,`equipment_id`),
  KEY `idx_audit_id` (`audit_id`),
  KEY `idx_equipment_id` (`equipment_id`),
  CONSTRAINT `fk_audit_snapshot_items_snapshot`
    FOREIGN KEY (`snapshot_id`) REFERENCES `audit_snapshots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `admin_credentials` (`id`, `username`, `password`) VALUES
(1, 'Admin', '$2y$10$2VvUGKT0pRc4tRLy7k6nRuMr0ALclsyyQZpKRlRRlvRQezPcgxYvG')
ON DUPLICATE KEY UPDATE
  `username` = VALUES(`username`),
  `password` = VALUES(`password`);

INSERT INTO `equipment`
  (`id`, `equipment_id`, `equipment_name`, `serial_number`, `internal_sn`, `account_person`,
   `total_qty`, `working_qty`, `not_working_qty`, `maintenance_qty`, `available`,
   `is_borrowable`, `description`, `created_at`)
VALUES
  (1, 'E-001', 'Oscilloscope', 'SN-OSC-001', 'ISN-001', 'Juan Dela Cruz', 3, 3, 0, 0, 3, 1, 'Digital oscilloscope 100MHz', '2026-03-20 20:27:37'),
  (2, 'E-002', 'Function Generator', 'SN-FG-002', 'ISN-002', 'Juan Dela Cruz', 2, 1, 1, 0, 1, 1, 'Signal generator 0-20MHz', '2026-03-20 20:27:37'),
  (3, 'M-001', 'Vernier Caliper', 'SN-VC-001', 'ISN-003', 'Maria Santos', 5, 5, 0, 0, 5, 1, 'Stainless steel 150mm', '2026-03-20 20:27:37'),
  (4, 'M-002', 'Micrometer', 'SN-MIC-001', 'ISN-004', 'Maria Santos', 4, 4, 0, 0, 4, 1, 'Outside micrometer 0-25mm', '2026-03-20 20:27:37'),
  (5, 'C-001', 'Hydrochloric Acid', NULL, NULL, 'Dr. Reyes', 10, 10, 0, 0, 10, 1, '1M solution, 500ml bottles', '2026-03-20 20:27:37'),
  (6, 'B-001', 'Physics Lab Manual', NULL, NULL, 'Dr. Reyes', 20, 20, 0, 0, 20, 1, 'EARIST Applied Physics Lab Manual 2023', '2026-03-20 20:27:37')
ON DUPLICATE KEY UPDATE
  `equipment_name` = VALUES(`equipment_name`),
  `serial_number` = VALUES(`serial_number`),
  `internal_sn` = VALUES(`internal_sn`),
  `account_person` = VALUES(`account_person`),
  `total_qty` = VALUES(`total_qty`),
  `working_qty` = VALUES(`working_qty`),
  `not_working_qty` = VALUES(`not_working_qty`),
  `maintenance_qty` = VALUES(`maintenance_qty`),
  `available` = VALUES(`available`),
  `is_borrowable` = VALUES(`is_borrowable`),
  `description` = VALUES(`description`);

INSERT INTO `guests` (`id`, `guest_number`, `created_at`) VALUES
  (1, '0326001', '2026-03-20 20:31:09'),
  (2, '0326002', '2026-03-24 17:34:09'),
  (3, '0326003', '2026-03-24 17:36:49'),
  (4, '0326004', '2026-03-24 17:42:34'),
  (5, '0326005', '2026-03-24 17:47:05'),
  (6, '0326006', '2026-03-24 17:52:04'),
  (7, '0326007', '2026-03-24 17:53:20'),
  (8, '0326008', '2026-03-24 19:01:12'),
  (9, '0326009', '2026-03-29 07:26:02'),
  (10, '0326010', '2026-03-29 07:34:03'),
  (11, '0326011', '2026-03-29 07:36:15'),
  (12, '0326012', '2026-03-29 07:41:39')
ON DUPLICATE KEY UPDATE `guest_number` = VALUES(`guest_number`);

INSERT INTO `instructors` (`id`, `instructor_name`) VALUES
  (1, 'Prof. Hiromi Rivas'),
  (2, 'Prof. Lester Bernardino'),
  (3, 'Mr. Ricardo Milos'),
  (4, 'Dr. Raymund Bolalin')
ON DUPLICATE KEY UPDATE `instructor_name` = VALUES(`instructor_name`);

INSERT INTO `rooms` (`id`, `room_number`) VALUES
  (1, 'Room 407')
ON DUPLICATE KEY UPDATE `room_number` = VALUES(`room_number`);

INSERT INTO `borrow_requests`
  (`id`, `guest_number`, `date`, `borrower_name`, `instructor_name`, `student_id`,
   `subject_code`, `usage_date`, `room`, `status`, `processed`, `created_at`)
VALUES
  (1, '0326009', '2026-03-29', 'Abarro, Kyrie E.', 'Mr. Ricardo Milos', '123123',
   '123', '2026-03-29', 'Room 407', 'Approved', 0, '2026-03-29 07:30:15')
ON DUPLICATE KEY UPDATE
  `guest_number` = VALUES(`guest_number`),
  `date` = VALUES(`date`),
  `borrower_name` = VALUES(`borrower_name`),
  `instructor_name` = VALUES(`instructor_name`),
  `student_id` = VALUES(`student_id`),
  `subject_code` = VALUES(`subject_code`),
  `usage_date` = VALUES(`usage_date`),
  `room` = VALUES(`room`),
  `status` = VALUES(`status`);

INSERT INTO `borrowed_equipment`
  (`id`, `borrow_request_id`, `equipment_id`, `equipment_name`, `quantity`, `available`, `returned_on`, `remarks`)
VALUES
  (1, 1, NULL, 'Micrometer', 1, '0', '2026-03-29', 'Basag boss')
ON DUPLICATE KEY UPDATE
  `borrow_request_id` = VALUES(`borrow_request_id`),
  `equipment_id` = VALUES(`equipment_id`),
  `equipment_name` = VALUES(`equipment_name`),
  `quantity` = VALUES(`quantity`),
  `available` = VALUES(`available`),
  `returned_on` = VALUES(`returned_on`),
  `remarks` = VALUES(`remarks`);

ALTER TABLE `admin_credentials` AUTO_INCREMENT = 3;
ALTER TABLE `equipment` AUTO_INCREMENT = 7;
ALTER TABLE `guests` AUTO_INCREMENT = 13;
ALTER TABLE `instructors` AUTO_INCREMENT = 5;
ALTER TABLE `rooms` AUTO_INCREMENT = 2;
ALTER TABLE `borrow_requests` AUTO_INCREMENT = 2;
ALTER TABLE `borrowed_equipment` AUTO_INCREMENT = 2;

COMMIT;
