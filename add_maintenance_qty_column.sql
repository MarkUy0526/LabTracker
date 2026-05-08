ALTER TABLE equipment
ADD COLUMN maintenance_qty INT NOT NULL DEFAULT 0 AFTER not_working_qty;
