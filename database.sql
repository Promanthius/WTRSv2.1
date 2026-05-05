-- WTRS Database Schema & Sample Data
-- For use with XAMPP (MySQL/MariaDB)

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','adviser','student') NOT NULL DEFAULT 'student',
  `college` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `adviser_id` int(11) DEFAULT NULL,
  `max_advisees` int(11) DEFAULT 10,
  `bio` text DEFAULT NULL,
  `research_interests` text DEFAULT NULL,
  `experience` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users` (Sample Data)
--
-- All passwords are 'password123'
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `password`, `role`, `college`, `bio`, `research_interests`, `experience`) VALUES
-- 1 Admin
(1, 'Admin', 'User', 'admin@wmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'University Administration', 'System administrator for the WMSU Repository.', 'Repository Management', '10+ years in IT'),
-- 10 Faculty (Advisers)
(2, 'Juan', 'Dela Cruz', 'juan.delacruz@wmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'adviser', 'College of Computing Studies', 'Expert in Software Engineering and AI.', 'Artificial Intelligence, Software Architecture', 'Assistant Professor at CCS'),
(3, 'Maria', 'Santos', 'maria.santos@wmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'adviser', 'College of Computing Studies', 'Focusing on Cyber Security and Network Protocols.', 'Cyber Security, Networking', 'CISCO Certified Instructor'),
(4, 'Jose', 'Rizal', 'jose.rizal@wmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'adviser', 'College of Liberal Arts', 'Passionate about history and social sciences.', 'Philippine History, Sociology', 'Doctorate in Humanities'),
(5, 'Corazon', 'Aquino', 'corazon.aquino@wmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'adviser', 'College of Science and Mathematics', 'Specializing in Mathematics and Statistics.', 'Data Science, Statistics', 'Senior Math Faculty'),
(6, 'Ferdinand', 'Marcos', 'ferdinand.marcos@wmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'adviser', 'College of Law', 'Expert in Constitutional Law.', 'Legal Ethics, Constitution', 'Practicing Attorney'),
(7, 'Andres', 'Bonifacio', 'andres.bonifacio@wmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'adviser', 'College of Engineering', 'Mechanical Engineer with a focus on thermodynamics.', 'Thermodynamics, Robotics', 'PRC Licensed Engineer'),
(8, 'Melchora', 'Aquino', 'melchora.aquino@wmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'adviser', 'College of Nursing', 'Nursing professional with community health focus.', 'Public Health, Nursing Education', 'Registered Nurse'),
(9, 'Emilio', 'Aguinaldo', 'emilio.aguinaldo@wmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'adviser', 'College of Agriculture', 'Researcher in sustainable farming.', 'Sustainable Agriculture, Agronomy', 'Agri-consultant'),
(10, 'Apolinario', 'Mabini', 'apolinario.mabini@wmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'adviser', 'College of Education', 'Dedicated to inclusive education.', 'Pedagogy, Special Education', 'Master Teacher'),
(11, 'Gabriela', 'Silang', 'gabriela.silang@wmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'adviser', 'College of Computing Studies', 'Data Scientist and Database Expert.', 'Data Analytics, SQL Optimization', 'Industry Consultant'),
-- 10 Students
(12, 'Student', 'One', 'student1@wmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'College of Computing Studies', 'Aspiring software developer.', 'Web Development', 'Junior year student'),
(13, 'Rico', 'Blanco', 'rico.blanco@wmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'College of Computing Studies', 'Interested in Game Development.', 'Unity, C#', 'Sophomore'),
(14, 'Lea', 'Salonga', 'lea.salonga@wmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'College of Liberal Arts', 'History buff.', 'History', 'Senior'),
(15, 'Manny', 'Pacquiao', 'manny.pacquiao@wmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'College of Education', 'Future educator.', 'Physical Education', 'Junior'),
(16, 'Catriona', 'Gray', 'catriona.gray@wmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'College of Science and Mathematics', 'Mathematics major.', 'Calculus', 'Freshman'),
(17, 'Pia', 'Wurtzbach', 'pia.wurtzbach@wmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'College of Nursing', 'Nursing student.', 'Clinical Care', 'Senior'),
(18, 'Bamboo', 'Manalac', 'bamboo.manalac@wmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'College of Engineering', 'Engineering student.', 'Civil Engineering', 'Junior'),
(19, 'Sarah', 'Geronimo', 'sarah.geronimo@wmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'College of Computing Studies', 'UI/UX enthusiast.', 'Design Systems', 'Sophomore'),
(20, 'Vic', 'Sotto', 'vic.sotto@wmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'College of Computing Studies', 'Entrepreneurship minor.', 'Business Tech', 'Senior'),
(21, 'Jose', 'Manalo', 'jose.manalo@wmsu.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'College of Computing Studies', 'Blockchain explorer.', 'Cryptography', 'Junior');

-- --------------------------------------------------------

--
-- Table structure for table `theses`
--

CREATE TABLE IF NOT EXISTS `theses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `thesis_code` varchar(20) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `abstract` text DEFAULT NULL,
  `author_id` int(11) NOT NULL,
  `adviser_id` int(11) DEFAULT NULL,
  `status` enum('draft','pending_review','revision_requested','approved','archived') DEFAULT 'draft',
  `year` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_theses_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_theses_adviser` FOREIGN KEY (`adviser_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `thesis_versions`
--

CREATE TABLE IF NOT EXISTS `thesis_versions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `thesis_id` int(11) NOT NULL,
  `version_number` varchar(10) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `status` enum('pending','rejected','approved') DEFAULT 'pending',
  `feedback` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_versions_thesis` FOREIGN KEY (`thesis_id`) REFERENCES `theses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `thesis_authors`
--

CREATE TABLE IF NOT EXISTS `thesis_authors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `thesis_id` int(11) NOT NULL,
  `author_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_authors_thesis` FOREIGN KEY (`thesis_id`) REFERENCES `theses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_authors_user` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient_user_id` int(11) NOT NULL,
  `sender_user_id` int(11) DEFAULT NULL,
  `thesis_id` int(11) DEFAULT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'thesis_request',
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_notifications_recipient` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifications_sender` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `adviser_requests`
--

CREATE TABLE IF NOT EXISTS `adviser_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `adviser_id` int(11) NOT NULL,
  `status` enum('pending','accepted','rejected','cancelled') DEFAULT 'pending',
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_req_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_req_adviser` FOREIGN KEY (`adviser_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action_type` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;
