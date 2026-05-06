-- Add department column to borrow_requests table
ALTER TABLE borrow_requests ADD COLUMN department VARCHAR(100) DEFAULT NULL;