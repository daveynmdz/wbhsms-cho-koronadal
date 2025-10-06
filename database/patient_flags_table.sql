-- Patient Flags Table Creation
-- Purpose: Track patient verification flags for false information

-- Create patient_flags table
CREATE TABLE `patient_flags` (
  `flag_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `flag_type` enum('false_senior','false_philhealth','false_pwd','false_patient_booked','other') NOT NULL,
  `flag_reason` text NOT NULL,
  `appointment_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Associated appointment if applicable',
  `flagged_by` int(10) UNSIGNED NOT NULL COMMENT 'Employee ID who flagged the patient',
  `flagged_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_resolved` tinyint(1) DEFAULT 0,
  `resolved_by` int(10) UNSIGNED DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`flag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key constraints
ALTER TABLE `patient_flags`
  ADD CONSTRAINT `fk_patient_flags_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_patient_flags_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_patient_flags_flagged_by` FOREIGN KEY (`flagged_by`) REFERENCES `employees` (`employee_id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_patient_flags_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `employees` (`employee_id`) ON DELETE RESTRICT;

-- Create indexes for better performance
CREATE INDEX `idx_patient_flags_patient_id` ON `patient_flags` (`patient_id`);
CREATE INDEX `idx_patient_flags_flag_type` ON `patient_flags` (`flag_type`);
CREATE INDEX `idx_patient_flags_is_resolved` ON `patient_flags` (`is_resolved`);
CREATE INDEX `idx_patient_flags_flagged_at` ON `patient_flags` (`flagged_at`);
CREATE INDEX `idx_patient_flags_appointment_id` ON `patient_flags` (`appointment_id`);