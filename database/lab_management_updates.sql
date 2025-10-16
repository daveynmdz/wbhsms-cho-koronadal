-- Database updates for Laboratory Management Module
-- Run this SQL to add necessary columns to work with existing schema

-- Add overall_status column to lab_orders table
ALTER TABLE `lab_orders` 
ADD COLUMN `overall_status` enum('pending','in_progress','completed','cancelled','partial') 
COLLATE utf8mb4_unicode_ci DEFAULT 'pending' AFTER `status`;

-- Add missing columns to lab_order_items table
ALTER TABLE `lab_order_items`
ADD COLUMN `result` text COLLATE utf8mb4_unicode_ci AFTER `status`,
ADD COLUMN `uploaded_by_employee_id` int UNSIGNED DEFAULT NULL AFTER `result_date`,
ADD COLUMN `special_instructions` text COLLATE utf8mb4_unicode_ci AFTER `uploaded_by_employee_id`;

-- Add foreign key constraint for uploaded_by_employee_id
ALTER TABLE `lab_order_items`
ADD CONSTRAINT `fk_lab_order_items_uploaded_by` FOREIGN KEY (`uploaded_by_employee_id`) REFERENCES `employees` (`employee_id`);

-- Update existing lab_orders to have overall_status same as status
UPDATE `lab_orders` SET `overall_status` = `status` WHERE `overall_status` IS NULL;

-- Create sample lab order and items for testing (optional)
-- INSERT INTO `lab_orders` (`appointment_id`, `patient_id`, `ordered_by_employee_id`, `status`, `overall_status`, `remarks`) 
-- VALUES (1, 7, 1, 'pending', 'pending', 'Sample lab order for testing');

-- INSERT INTO `lab_order_items` (`lab_order_id`, `test_type`, `status`, `special_instructions`) VALUES
-- (LAST_INSERT_ID(), 'Complete Blood Count (CBC)', 'pending', 'Fasting not required'),
-- (LAST_INSERT_ID(), 'Urinalysis', 'pending', 'Mid-stream clean catch specimen required');