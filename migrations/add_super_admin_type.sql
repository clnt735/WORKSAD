-- Migration: Add super_admin user type
-- Date: January 10, 2026
-- Description: Adds user_type_id = 4 for SUPER ADMIN role

-- Insert super_admin user type (only if it doesn't exist)
INSERT INTO `user_type` (`user_type_id`, `user_type_name`, `user_type_created_at`, `user_type_updated_at`) 
SELECT 4, 'super_admin', CURDATE(), CURDATE()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `user_type` WHERE `user_type_id` = 4);

-- Verify the insertion
SELECT * FROM `user_type` ORDER BY `user_type_id`;
