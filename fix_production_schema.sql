-- Fix for prescribed_medications status column size issue
-- Run this on your production database

-- Check current column definition
SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'prescribed_medications' AND COLUMN_NAME = 'status';

-- Alter the column to accommodate longer status values
ALTER TABLE prescribed_medications 
MODIFY COLUMN status ENUM('not yet dispensed', 'dispensed', 'unavailable') 
DEFAULT 'not yet dispensed';

-- Alternative if you prefer VARCHAR (more flexible):
-- ALTER TABLE prescribed_medications 
-- MODIFY COLUMN status VARCHAR(20) DEFAULT 'not yet dispensed';

-- Also fix prescriptions table if needed
ALTER TABLE prescriptions 
MODIFY COLUMN status ENUM('active', 'issued', 'cancelled', 'dispensed') 
DEFAULT 'active';

-- Verify the changes
SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME IN ('prescribed_medications', 'prescriptions') 
AND COLUMN_NAME = 'status';