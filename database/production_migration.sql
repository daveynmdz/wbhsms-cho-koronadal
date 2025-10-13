-- Production Database Migration: Add missing columns
-- Run this on your production database to add missing columns

-- Add notes column to referrals table (if it doesn't exist)
ALTER TABLE referrals 
ADD COLUMN IF NOT EXISTS notes TEXT DEFAULT NULL 
COMMENT 'Additional notes for referral tracking';

-- Add qr_verification_code column to appointments table (if it doesn't exist)
ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS qr_verification_code VARCHAR(10) DEFAULT NULL 
COMMENT 'Verification code for QR code security';

-- Verify the columns were added
SHOW COLUMNS FROM referrals WHERE Field = 'notes';
SHOW COLUMNS FROM appointments WHERE Field = 'qr_verification_code';

-- Update any existing appointments to generate verification codes
UPDATE appointments 
SET qr_verification_code = UPPER(RIGHT(MD5(CONCAT(appointment_id, scheduled_date, patient_id)), 8))
WHERE qr_verification_code IS NULL AND qr_code_path IS NOT NULL;

-- Verification query
SELECT 
    'referrals' as table_name, 
    COUNT(*) as has_notes_column 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'referrals' 
    AND COLUMN_NAME = 'notes'
UNION ALL
SELECT 
    'appointments' as table_name, 
    COUNT(*) as has_qr_verification_code 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'appointments' 
    AND COLUMN_NAME = 'qr_verification_code';