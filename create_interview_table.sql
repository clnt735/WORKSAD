-- Create interview_schedule table
-- Run this SQL in phpMyAdmin or MySQL command line

CREATE TABLE IF NOT EXISTS `interview_schedule` (
  `interview_id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `job_post_id` int(11) NOT NULL,
  `interview_date` date NOT NULL,
  `interview_month` int(11) NOT NULL,
  `interview_day` int(11) NOT NULL,
  `status` enum('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`interview_id`),
  KEY `application_id` (`application_id`),
  KEY `employer_id` (`employer_id`),
  KEY `applicant_id` (`applicant_id`),
  KEY `job_post_id` (`job_post_id`),
  CONSTRAINT `fk_interview_application` FOREIGN KEY (`application_id`) REFERENCES `application` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
