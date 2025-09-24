-- Update referrals table schema to match the application requirements
-- This script adds missing fields and updates existing ones

-- Add missing fields to referrals table
ALTER TABLE `referrals` 
ADD COLUMN `referral_num` varchar(20) UNIQUE AFTER `referral_id`,
ADD COLUMN `destination_type` enum('barangay_center','district_office','city_office','external') DEFAULT 'barangay_center' AFTER `service_id`;

-- Update status enum to include 'active'
ALTER TABLE `referrals` 
MODIFY COLUMN `status` enum('active','pending','accepted','completed','cancelled') DEFAULT 'pending';

-- Ensure referring_facility_id is properly set (should be populated from employee's facility)
ALTER TABLE `referrals` 
MODIFY COLUMN `referring_facility_id` int(10) UNSIGNED NOT NULL;

-- Add index for referral_num for faster lookups
ALTER TABLE `referrals` 
ADD INDEX `idx_referral_num` (`referral_num`);

-- Update roles table to match session role names
UPDATE `roles` SET `role_name` = 'bhw' WHERE `role_name` = 'BHW';
UPDATE `roles` SET `role_name` = 'dho' WHERE `role_name` = 'DHO';
UPDATE `roles` SET `role_name` = 'admin' WHERE `role_name` = 'Admin';
UPDATE `roles` SET `role_name` = 'doctor' WHERE `role_name` = 'Doctor';
UPDATE `roles` SET `role_name` = 'nurse' WHERE `role_name` = 'Nurse';
UPDATE `roles` SET `role_name` = 'pharmacist' WHERE `role_name` = 'Pharmacist';
UPDATE `roles` SET `role_name` = 'records_officer' WHERE `role_name` = 'Records Officer';
UPDATE `roles` SET `role_name` = 'cashier' WHERE `role_name` = 'Cashier';
UPDATE `roles` SET `role_name` = 'laboratory_tech' WHERE `role_name` = 'Laboratory Tech.';