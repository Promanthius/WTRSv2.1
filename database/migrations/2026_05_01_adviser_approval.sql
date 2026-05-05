-- Database Migration: Adviser Approval Workflow

-- 1. Add new columns to `users` table for adviser management
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `adviser_id` INT(11) DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `max_advisees` INT(11) NOT NULL DEFAULT 10;

-- Drop foreign key if exists (safe approach)
-- ALTER TABLE `users` DROP FOREIGN KEY `fk_user_adviser`;
ALTER TABLE `users` ADD CONSTRAINT `fk_user_adviser` FOREIGN KEY (`adviser_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- 2. Create `adviser_requests` table
CREATE TABLE IF NOT EXISTS `adviser_requests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL,
  `adviser_id` INT(11) NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_adviser_requests_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_adviser_requests_adviser` FOREIGN KEY (`adviser_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
