-- Fix description column type from INT to VARCHAR
ALTER TABLE equipment MODIFY COLUMN description VARCHAR(500);
