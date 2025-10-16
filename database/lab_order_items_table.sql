-- SQL to create lab_order_items table for detailed lab test tracking
-- This table stores individual lab test items within a consolidated lab order

CREATE TABLE `lab_order_items` (
  `lab_order_item_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `lab_order_id` int UNSIGNED NOT NULL,
  `test_type` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','in_progress','completed','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `result` text COLLATE utf8mb4_unicode_ci,
  `result_file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Path to uploaded PDF result file',
  `result_date` datetime DEFAULT NULL,
  `uploaded_by_employee_id` int UNSIGNED DEFAULT NULL,
  `special_instructions` text COLLATE utf8mb4_unicode_ci,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`lab_order_item_id`),
  KEY `fk_lab_order_items_lab_order` (`lab_order_id`),
  KEY `fk_lab_order_items_uploaded_by` (`uploaded_by_employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key constraints
ALTER TABLE `lab_order_items`
  ADD CONSTRAINT `fk_lab_order_items_lab_order` FOREIGN KEY (`lab_order_id`) REFERENCES `lab_orders` (`lab_order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lab_order_items_uploaded_by` FOREIGN KEY (`uploaded_by_employee_id`) REFERENCES `employees` (`employee_id`);

-- Update lab_orders table to remove redundant columns now handled by lab_order_items
ALTER TABLE `lab_orders` 
  DROP COLUMN `test_type`,
  DROP COLUMN `result`,
  DROP COLUMN `result_date`;

-- Add overall_status to lab_orders for consolidated status tracking
ALTER TABLE `lab_orders` 
  ADD COLUMN `overall_status` enum('pending','in_progress','completed','cancelled','partial') COLLATE utf8mb4_unicode_ci DEFAULT 'pending' AFTER `status`;

-- Insert sample predefined lab tests
INSERT INTO `lab_order_items` (`lab_order_id`, `test_type`, `status`, `special_instructions`) VALUES
-- Add sample lab test types that can be selected during order creation
-- These will serve as templates for common lab tests
(1, 'Complete Blood Count (CBC)', 'pending', 'Fasting not required'),
(1, 'Urinalysis', 'pending', 'Mid-stream clean catch specimen required'),
(1, 'Fasting Blood Sugar (FBS)', 'pending', '8-12 hour fasting required'),
(1, 'Lipid Profile', 'pending', '12-hour fasting required'),
(1, 'Hepatitis B Surface Antigen', 'pending', 'No special preparation needed'),
(1, 'Pregnancy Test (HCG)', 'pending', 'First morning urine preferred'),
(1, 'Stool Examination', 'pending', 'Fresh specimen within 2 hours'),
(1, 'Chest X-ray', 'pending', 'Remove metallic objects'),
(1, 'Electrocardiogram (ECG)', 'pending', 'Rest for 5 minutes before test'),
(1, 'Blood Typing & Rh Factor', 'pending', 'No special preparation needed');