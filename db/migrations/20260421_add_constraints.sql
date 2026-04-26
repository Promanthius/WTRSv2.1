/* Migration: 20260421_add_constraints.sql */
-- Add a UNIQUE constraint to prevent duplicate thesis submissions by the same author at the same timestamp.
ALTER TABLE `theses`
  ADD CONSTRAINT `uq_theses_author_created`
    UNIQUE (`author_id`, `created_at`);

-- Ensure adviser_id references existing users (already present as foreign key, but enforce ON DELETE SET NULL if not already).
ALTER TABLE `theses`
  DROP FOREIGN KEY IF EXISTS `theses_ibfk_2`;
ALTER TABLE `theses`
  ADD CONSTRAINT `fk_theses_adviser`
    FOREIGN KEY (`adviser_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- Add a UNIQUE constraint on thesis_versions to prevent duplicate version numbers per thesis.
ALTER TABLE `thesis_versions`
  ADD CONSTRAINT `uq_thesis_versions_thesis_version`
    UNIQUE (`thesis_id`, `version_number`);

-- Add foreign key constraints for activity_logs and notifications if missing.
ALTER TABLE `activity_logs`
  DROP FOREIGN KEY IF EXISTS `activity_logs_ibfk_1`;
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_activity_logs_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;

ALTER TABLE `notifications`
  DROP FOREIGN KEY IF EXISTS `notifications_ibfk_1`,
  DROP FOREIGN KEY IF EXISTS `notifications_ibfk_2`,
  DROP FOREIGN KEY IF EXISTS `notifications_ibfk_3`;
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_recipient`
    FOREIGN KEY (`recipient_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notifications_sender`
    FOREIGN KEY (`sender_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_notifications_thesis`
    FOREIGN KEY (`thesis_id`) REFERENCES `theses`(`id`) ON DELETE CASCADE;

-- End of migration
