-- WTRS Database Seeder
USE `wtrs_db`;

-- Clear existing data (if not already handled by schema reset)
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE `notifications`;
TRUNCATE TABLE `activity_logs`;
TRUNCATE TABLE `thesis_versions`;
TRUNCATE TABLE `theses`;
TRUNCATE TABLE `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- Insert Test Adviser (Password: Password123!)
INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `password`, `role`, `college`, `status`) VALUES
(1, 'Professor', 'Test', 'advisertest@wmsu.edu.ph', '$2y$10$cyrdHgo6/w61HbW.G6DCy.hnhj1h8O/D8YD4Cm9r7I67OJwgWvt0q', 'adviser', 'College of Computing Studies', 'active');

-- Insert Test Student (Password: Password123!)
INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `password`, `role`, `college`, `status`) VALUES
(2, 'Student', 'Test', 'teststudent@wmsu.edu.ph', '$2y$10$cyrdHgo6/w61HbW.G6DCy.hnhj1h8O/D8YD4Cm9r7I67OJwgWvt0q', 'student', 'College of Computing Studies', 'active');

-- Insert a sample thesis for the student
INSERT INTO `theses` (`thesis_code`, `title`, `abstract`, `author_id`, `adviser_id`, `status`) VALUES
('THS-2026-SEED', 'Automated Testing of WTRS Workflow', 'This is a sample thesis created via the seeder for testing the new Adviser Review Workflow.', 2, 1, 'pending_review');

-- Insert a version for the sample thesis
INSERT INTO `thesis_versions` (`thesis_id`, `version_number`, `file_path`, `file_size`, `status`) VALUES
(1, '1.0', 'thesis_seed_sample.pdf', 102400, 'pending');
