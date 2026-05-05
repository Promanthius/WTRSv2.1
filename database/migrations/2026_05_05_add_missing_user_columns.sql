-- Migration: Add missing user columns (2026-05-05)
-- Adds adviser_id, max_advisees, course, student_id, year_level

ALTER TABLE `users` ADD COLUMN `adviser_id` int(11) DEFAULT NULL AFTER `status`;
ALTER TABLE `users` ADD COLUMN `max_advisees` int(11) NOT NULL DEFAULT 10 AFTER `adviser_id`;
ALTER TABLE `users` ADD COLUMN `student_id` varchar(50) DEFAULT NULL AFTER `max_advisees`;
ALTER TABLE `users` ADD COLUMN `course` varchar(150) DEFAULT NULL AFTER `student_id`;
ALTER TABLE `users` ADD COLUMN `year_level` varchar(50) DEFAULT NULL AFTER `course`;

-- Add foreign key for adviser_id
ALTER TABLE `users` ADD CONSTRAINT `fk_adviser_id` FOREIGN KEY (`adviser_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;
