-- Migration: Add resume_type column to application table
-- This tracks whether the applicant used their built-in resume or uploaded a file

ALTER TABLE `application` 
ADD COLUMN `resume_type` ENUM('builtin', 'file') DEFAULT 'builtin' AFTER `status`;

-- Also add resume_file_path to store the path when a file is uploaded
ALTER TABLE `application` 
ADD COLUMN `resume_file_path` VARCHAR(255) DEFAULT NULL AFTER `resume_type`;
