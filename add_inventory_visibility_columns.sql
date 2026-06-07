-- Add inventory borrowing visibility and independent import/edit timestamps.
ALTER TABLE equipment
  ADD COLUMN is_borrowable TINYINT(1) NOT NULL DEFAULT 1 AFTER available,
  ADD COLUMN last_imported_at DATETIME NULL DEFAULT NULL AFTER is_borrowable,
  ADD COLUMN last_edited_at DATETIME NULL DEFAULT NULL AFTER last_imported_at;
