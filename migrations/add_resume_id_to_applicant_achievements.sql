-- Migration: Add user_id column to applicant_achievements table
-- Date: December 14, 2025
-- Purpose: Link achievements to user profile (not resume-specific)

-- Add user_id column if it doesn't exist
ALTER TABLE `applicant_achievements` 
ADD COLUMN IF NOT EXISTS `user_id` INT(11) NOT NULL AFTER `achievement_id`,
ADD INDEX IF NOT EXISTS `idx_user_id` (`user_id`);

-- Add foreign key constraint
ALTER TABLE `applicant_achievements`
ADD CONSTRAINT IF NOT EXISTS `fk_achievement_user` 
FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) 
ON DELETE CASCADE;

-- Remove resume_id column if it exists (achievements are profile-level, not resume-specific)
-- ALTER TABLE `applicant_achievements` DROP COLUMN IF EXISTS `resume_id`;
