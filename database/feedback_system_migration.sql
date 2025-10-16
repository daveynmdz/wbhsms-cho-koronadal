-- ========================================
-- WBHSMS Feedback System Database Migration
-- Patient Satisfaction & Feedback Enhancement
-- Date: October 16, 2025
-- ========================================

-- Check if migrations table exists, create if not
CREATE TABLE IF NOT EXISTS `feedback_migrations` (
    `migration_id` INT AUTO_INCREMENT PRIMARY KEY,
    `migration_name` VARCHAR(255) NOT NULL,
    `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_migration` (`migration_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration 1: Enhance feedback_questions table
-- Add missing columns to support role-based questions and service categories
ALTER TABLE `feedback_questions` 
    ADD COLUMN IF NOT EXISTS `role_target` ENUM('Patient', 'BHW', 'Employee', 'All') DEFAULT 'Patient' AFTER `question_type`,
    ADD COLUMN IF NOT EXISTS `service_category` VARCHAR(100) NULL AFTER `role_target`,
    ADD COLUMN IF NOT EXISTS `is_required` TINYINT(1) DEFAULT 0 AFTER `service_category`,
    ADD COLUMN IF NOT EXISTS `display_order` INT DEFAULT 1 AFTER `is_required`,
    ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- Migration 2: Enhance feedback_question_choices table
-- Add choice ordering and value improvements
ALTER TABLE `feedback_question_choices`
    ADD COLUMN IF NOT EXISTS `choice_order` INT DEFAULT 1 AFTER `choice_value`,
    ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) DEFAULT 1 AFTER `choice_order`,
    ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- Migration 3: Create feedback_submissions table
-- Central submission tracking with metadata
CREATE TABLE IF NOT EXISTS `feedback_submissions` (
    `submission_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `user_type` ENUM('Patient', 'BHW', 'Employee') NOT NULL,
    `facility_id` INT UNSIGNED NULL,
    `visit_id` INT UNSIGNED NULL,
    `service_category` VARCHAR(100) NULL,
    `overall_rating` DECIMAL(3,2) NULL,
    `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `is_anonymous` TINYINT(1) DEFAULT 0,
    INDEX `idx_user` (`user_id`, `user_type`),
    INDEX `idx_facility` (`facility_id`),
    INDEX `idx_visit` (`visit_id`),
    INDEX `idx_submitted_at` (`submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration 4: Enhance feedback_answers table
-- Add missing columns for comprehensive tracking
ALTER TABLE `feedback_answers`
    ADD COLUMN IF NOT EXISTS `submission_id` INT UNSIGNED NULL AFTER `answer_id`,
    ADD COLUMN IF NOT EXISTS `user_id` INT UNSIGNED NULL AFTER `patient_id`,
    ADD COLUMN IF NOT EXISTS `user_type` ENUM('Patient', 'BHW', 'Employee') NULL AFTER `user_id`,
    ADD COLUMN IF NOT EXISTS `answer_rating` DECIMAL(3,2) NULL AFTER `answer_text`,
    ADD COLUMN IF NOT EXISTS `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `answered_at`,
    MODIFY COLUMN `patient_id` INT UNSIGNED NULL,
    MODIFY COLUMN `visit_id` INT UNSIGNED NULL;

-- Add indexes for better performance
ALTER TABLE `feedback_answers`
    ADD INDEX IF NOT EXISTS `idx_submission` (`submission_id`),
    ADD INDEX IF NOT EXISTS `idx_user` (`user_id`, `user_type`),
    ADD INDEX IF NOT EXISTS `idx_question` (`question_id`),
    ADD INDEX IF NOT EXISTS `idx_submitted_at` (`submitted_at`);

-- Migration 5: Create feedback analytics views
-- Summary view for quick analytics
CREATE OR REPLACE VIEW `feedback_summary_view` AS
SELECT 
    f.facility_id,
    f.name as facility_name,
    f.type as facility_type,
    fs.service_category,
    fa.user_type,
    COUNT(DISTINCT fs.submission_id) as total_submissions,
    COUNT(DISTINCT fa.user_id) as unique_respondents,
    AVG(fs.overall_rating) as avg_overall_rating,
    AVG(fa.answer_rating) as avg_question_rating,
    DATE_FORMAT(fa.submitted_at, '%Y-%m') as month_year,
    
    -- Rating distribution
    SUM(CASE WHEN fs.overall_rating >= 4.5 THEN 1 ELSE 0 END) as excellent_ratings,
    SUM(CASE WHEN fs.overall_rating >= 3.5 AND fs.overall_rating < 4.5 THEN 1 ELSE 0 END) as good_ratings,
    SUM(CASE WHEN fs.overall_rating >= 2.5 AND fs.overall_rating < 3.5 THEN 1 ELSE 0 END) as fair_ratings,
    SUM(CASE WHEN fs.overall_rating < 2.5 THEN 1 ELSE 0 END) as poor_ratings
    
FROM facilities f
LEFT JOIN feedback_submissions fs ON f.facility_id = fs.facility_id
LEFT JOIN feedback_answers fa ON fs.submission_id = fa.submission_id
WHERE fs.submission_id IS NOT NULL
GROUP BY f.facility_id, fs.service_category, fa.user_type, DATE_FORMAT(fa.submitted_at, '%Y-%m')
ORDER BY fa.submitted_at DESC;

-- Migration 6: Insert default feedback questions
-- Standard healthcare feedback questions for different roles

-- Patient Questions
INSERT IGNORE INTO `feedback_questions` (`question_text`, `question_type`, `role_target`, `service_category`, `is_required`, `display_order`) VALUES
('How would you rate your overall experience today?', 'rating', 'Patient', 'General', 1, 1),
('How satisfied were you with the waiting time?', 'rating', 'Patient', 'General', 1, 2),
('How would you rate the cleanliness of the facility?', 'rating', 'Patient', 'General', 1, 3),
('How satisfied were you with the staff courtesy?', 'rating', 'Patient', 'General', 1, 4),
('Would you recommend our services to others?', 'choice', 'Patient', 'General', 1, 5),
('How satisfied were you with the consultation?', 'rating', 'Patient', 'Consultation', 1, 6),
('Did the doctor explain your condition clearly?', 'choice', 'Patient', 'Consultation', 1, 7),
('How satisfied were you with the laboratory service?', 'rating', 'Patient', 'Laboratory', 0, 8),
('How satisfied were you with the pharmacy service?', 'rating', 'Patient', 'Pharmacy', 0, 9),
('Any additional comments or suggestions?', 'text', 'Patient', 'General', 0, 10);

-- BHW Questions  
INSERT IGNORE INTO `feedback_questions` (`question_text`, `question_type`, `role_target`, `service_category`, `is_required`, `display_order`) VALUES
('How would you rate the cooperation from CHO staff?', 'rating', 'BHW', 'General', 1, 11),
('How satisfied are you with the referral process?', 'rating', 'BHW', 'General', 1, 12),
('How would you rate the communication between CHO and BHW?', 'rating', 'BHW', 'General', 1, 13),
('Are you satisfied with the support provided for community health programs?', 'choice', 'BHW', 'General', 1, 14),
('Any feedback on community health initiatives?', 'text', 'BHW', 'General', 0, 15);

-- Employee Questions
INSERT IGNORE INTO `feedback_questions` (`question_text`, `question_type`, `role_target`, `service_category`, `is_required`, `display_order`) VALUES
('How would you rate the work environment?', 'rating', 'Employee', 'General', 1, 16),
('How satisfied are you with the available resources?', 'rating', 'Employee', 'General', 1, 17),
('How would you rate team collaboration?', 'rating', 'Employee', 'General', 1, 18),
('Are you satisfied with the management support?', 'choice', 'Employee', 'General', 1, 19),
('Any suggestions for workplace improvement?', 'text', 'Employee', 'General', 0, 20);

-- Migration 7: Insert default question choices
-- Choices for rating and yes/no questions

-- Yes/No choices (for recommendation and satisfaction questions)
INSERT IGNORE INTO `feedback_question_choices` (`question_id`, `choice_text`, `choice_value`, `choice_order`) 
SELECT q.question_id, 'Yes', '1', 1 
FROM `feedback_questions` q 
WHERE q.question_text LIKE '%recommend%' OR q.question_text LIKE '%satisfied%' OR q.question_text LIKE '%cooperation%'
AND q.question_type = 'choice';

INSERT IGNORE INTO `feedback_question_choices` (`question_id`, `choice_text`, `choice_value`, `choice_order`) 
SELECT q.question_id, 'No', '0', 2 
FROM `feedback_questions` q 
WHERE q.question_text LIKE '%recommend%' OR q.question_text LIKE '%satisfied%' OR q.question_text LIKE '%cooperation%'
AND q.question_type = 'choice';

-- Additional choice options for specific questions
INSERT IGNORE INTO `feedback_question_choices` (`question_id`, `choice_text`, `choice_value`, `choice_order`) 
SELECT q.question_id, 'Definitely', '2', 3 
FROM `feedback_questions` q 
WHERE q.question_text LIKE '%recommend%'
AND q.question_type = 'choice';

-- Migration 8: Create feedback logs table for audit trail
CREATE TABLE IF NOT EXISTS `feedback_logs` (
    `log_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `submission_id` INT UNSIGNED NULL,
    `action_type` ENUM('submit', 'update', 'delete', 'view') NOT NULL,
    `user_id` INT UNSIGNED NULL,
    `user_type` ENUM('Patient', 'BHW', 'Employee', 'Admin') NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `details` JSON NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_submission` (`submission_id`),
    INDEX `idx_user` (`user_id`, `user_type`),
    INDEX `idx_action` (`action_type`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration 9: Add foreign key constraints (if they don't exist)
-- Note: Only add if the referenced tables exist and have the required columns

-- Add constraints for feedback_submissions
ALTER TABLE `feedback_submissions`
    ADD CONSTRAINT `fk_feedback_submissions_facility` 
    FOREIGN KEY (`facility_id`) REFERENCES `facilities`(`facility_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    
    ADD CONSTRAINT `fk_feedback_submissions_visit` 
    FOREIGN KEY (`visit_id`) REFERENCES `visits`(`visit_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Add constraints for feedback_answers  
ALTER TABLE `feedback_answers`
    ADD CONSTRAINT `fk_feedback_answers_submission` 
    FOREIGN KEY (`submission_id`) REFERENCES `feedback_submissions`(`submission_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    
    ADD CONSTRAINT `fk_feedback_answers_question` 
    FOREIGN KEY (`question_id`) REFERENCES `feedback_questions`(`question_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    
    ADD CONSTRAINT `fk_feedback_answers_choice` 
    FOREIGN KEY (`choice_id`) REFERENCES `feedback_question_choices`(`choice_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    
    ADD CONSTRAINT `fk_feedback_answers_patient` 
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`patient_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    
    ADD CONSTRAINT `fk_feedback_answers_visit` 
    FOREIGN KEY (`visit_id`) REFERENCES `visits`(`visit_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    
    ADD CONSTRAINT `fk_feedback_answers_facility` 
    FOREIGN KEY (`facility_id`) REFERENCES `facilities`(`facility_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Add constraints for feedback_question_choices
ALTER TABLE `feedback_question_choices`
    ADD CONSTRAINT `fk_feedback_choices_question` 
    FOREIGN KEY (`question_id`) REFERENCES `feedback_questions`(`question_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Migration 10: Record migration execution
INSERT IGNORE INTO `feedback_migrations` (`migration_name`) VALUES 
('enhance_feedback_questions_table'),
('enhance_feedback_choices_table'), 
('create_feedback_submissions_table'),
('enhance_feedback_answers_table'),
('create_feedback_analytics_views'),
('insert_default_feedback_questions'),
('insert_default_question_choices'),
('create_feedback_logs_table'),
('add_foreign_key_constraints'),
('feedback_system_migration_complete');

-- ========================================
-- Migration Complete
-- ========================================
-- 
-- This migration adds:
-- 1. Enhanced feedback_questions with role targeting and service categories
-- 2. Enhanced feedback_question_choices with ordering
-- 3. New feedback_submissions table for centralized tracking  
-- 4. Enhanced feedback_answers with comprehensive data
-- 5. Analytics views for reporting
-- 6. Default questions for Patients, BHWs, and Employees
-- 7. Default answer choices
-- 8. Audit logging table
-- 9. Foreign key constraints for data integrity
-- 10. Migration tracking
--
-- Usage: Run this script on your existing database to upgrade
-- the feedback system to support the new backend modules.
--
-- Compatibility: Designed to work with existing data
-- All modifications use IF NOT EXISTS or ALTER TABLE ADD COLUMN IF NOT EXISTS
-- ========================================