-- QR Code Database Schema Changes
-- Execute these queries in order to convert qr_code_path to BLOB storage

-- 1. Clear all existing QR code path values
UPDATE appointments SET qr_code_path = NULL WHERE qr_code_path IS NOT NULL;

-- 2. Alter the qr_code_path column to LONGBLOB for binary image storage
-- Note: This will change the column from VARCHAR to LONGBLOB
ALTER TABLE appointments MODIFY COLUMN qr_code_path LONGBLOB NULL 
COMMENT 'QR code image data stored as binary BLOB';

-- 3. Optional: Add an index for faster QR lookups (if needed)
-- CREATE INDEX idx_appointments_qr ON appointments(appointment_id) WHERE qr_code_path IS NOT NULL;

-- 4. Verify the changes
DESCRIBE appointments;

-- 5. Check that all qr_code_path values are now NULL
SELECT COUNT(*) as total_appointments, 
       COUNT(qr_code_path) as appointments_with_qr 
FROM appointments;