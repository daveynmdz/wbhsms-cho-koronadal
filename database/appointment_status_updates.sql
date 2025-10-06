-- SQL Updates for Enhanced Check-in System
-- File: appointment_status_updates.sql
-- Purpose: Add 'checked_in' status to appointments table for enhanced check-in process

-- Add 'checked_in' status to appointments table enum
ALTER TABLE `appointments` 
MODIFY COLUMN `status` ENUM('confirmed','completed','cancelled','checked_in') DEFAULT 'confirmed';

-- Add indexes for better performance on appointment lookups
ALTER TABLE `appointments` 
ADD INDEX `idx_appointments_status_date` (`status`, `scheduled_date`),
ADD INDEX `idx_appointments_facility_date` (`facility_id`, `scheduled_date`);

-- Ensure patient_flags table has proper indexes if not already added
ALTER TABLE `patient_flags`
ADD INDEX IF NOT EXISTS `idx_patient_flags_appointment_date` (`appointment_id`, `created_at`),
ADD INDEX IF NOT EXISTS `idx_patient_flags_flag_type` (`flag_type`, `is_resolved`);

-- Add index to appointment_logs for better performance
ALTER TABLE `appointment_logs`
ADD INDEX IF NOT EXISTS `idx_appointment_logs_created_by` (`created_by_type`, `created_by_id`, `created_at`);

-- Update any existing 'confirmed' appointments from today that might be checked in
-- (This is for data consistency if there were any manual check-ins before this update)
-- Note: Only run this if you want to preserve existing check-in states
-- UPDATE appointments 
-- SET status = 'checked_in' 
-- WHERE status = 'confirmed' 
-- AND scheduled_date = CURDATE() 
-- AND appointment_id IN (
--     SELECT DISTINCT appointment_id 
--     FROM queue_entries 
--     WHERE created_at >= CURDATE()
-- );

-- Verify the changes
SELECT 
    COLUMN_TYPE 
FROM 
    INFORMATION_SCHEMA.COLUMNS 
WHERE 
    TABLE_SCHEMA = 'wbhsms_database' 
    AND TABLE_NAME = 'appointments' 
    AND COLUMN_NAME = 'status';

-- Show indexes on appointments table
SHOW INDEX FROM appointments;

COMMIT;