-- Queue Settings Table
-- CHO Koronadal Queue Management System

CREATE TABLE IF NOT EXISTS `queue_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `enabled` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT IGNORE INTO `queue_settings` (`setting_key`, `setting_value`, `enabled`) VALUES
('testing_mode', '0', 1),
('ignore_time_constraints', '0', 1),
('queue_override_mode', '0', 1),
('force_all_stations_open', '0', 1),
('last_updated', NOW(), 1);