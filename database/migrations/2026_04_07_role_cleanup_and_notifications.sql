-- Migration: cleanup legacy admin/invite artifacts and add notifications
-- Safe for existing databases upgraded from older schema revisions.

START TRANSACTION;

-- 1) Ensure notifications table exists for adviser request alerts.
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient_user_id` int(11) NOT NULL,
  `sender_user_id` int(11) DEFAULT NULL,
  `thesis_id` int(11) DEFAULT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'thesis_request',
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_recipient` (`recipient_user_id`,`is_read`,`created_at`),
  CONSTRAINT `fk_notifications_recipient` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifications_sender` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notifications_thesis` FOREIGN KEY (`thesis_id`) REFERENCES `theses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2) Preserve legacy admin users as advisers before enum change.
UPDATE `users` SET `role` = 'adviser' WHERE `role` = 'admin';

-- 3) Remove admin role from enum safely.
ALTER TABLE `users`
  MODIFY COLUMN `role` ENUM('student','adviser') NOT NULL DEFAULT 'student';

-- 4) Ensure abstract is optional.
ALTER TABLE `theses`
  MODIFY COLUMN `abstract` TEXT NULL;

-- 5) Add hardbound timestamp only if missing.
SET @has_hardbound_col := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'theses'
    AND COLUMN_NAME = 'hardbound_received_at'
);
SET @sql_hardbound := IF(@has_hardbound_col = 0,
  'ALTER TABLE `theses` ADD COLUMN `hardbound_received_at` DATETIME NULL',
  'SELECT 1'
);
PREPARE stmt_hardbound FROM @sql_hardbound;
EXECUTE stmt_hardbound;
DEALLOCATE PREPARE stmt_hardbound;

-- 6) Retire invite-only table if still present.
DROP TABLE IF EXISTS `adviser_invites`;

COMMIT;
