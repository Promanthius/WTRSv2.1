-- Migration: Add group thesis support (2026-05-05)
-- Adds fields for submission year, thesis type, and co-authors table

-- Add new columns to theses table
ALTER TABLE `theses` ADD COLUMN `submission_year` int(11) NOT NULL DEFAULT YEAR(CURDATE()) AFTER `adviser_id`;
ALTER TABLE `theses` ADD COLUMN `thesis_type` enum('solo','group') NOT NULL DEFAULT 'solo' AFTER `submission_year`;

-- Create thesis_authors table for co-authors (group theses)
CREATE TABLE IF NOT EXISTS `thesis_authors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `thesis_id` int(11) NOT NULL,
  `author_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_thesis_author` (`thesis_id`, `author_id`),
  FOREIGN KEY (`thesis_id`) REFERENCES `theses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
