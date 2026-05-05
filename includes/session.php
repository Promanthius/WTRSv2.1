<?php
// Central session and auth helper
require_once __DIR__ . '/config.php';
date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    // Session Security Hardening
    // Adjust cookie lifetime as needed (e.g., 86400 for 1 day)
    $cookieLifetime = 86400; 
    
    // Check if HTTPS is used; if so, enforce secure cookies.
    // XAMPP locally is generally HTTP, so we make it conditional for deployment.
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
    
    session_set_cookie_params([
        'lifetime' => $cookieLifetime,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => $isSecure,     // Only transmit over HTTPS if available
        'httponly' => true,        // Prevent JavaScript access to session cookie (mitigates XSS)
        'samesite' => 'Lax'        // Mitigate CSRF while allowing standard navigation
    ]);

    session_start();
}

require_once __DIR__ . '/db.php';

// Runtime compatibility bootstrap for existing local databases.
$pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_user_id INT NOT NULL,
    sender_user_id INT DEFAULT NULL,
    thesis_id INT DEFAULT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'thesis_request',
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_recipient (recipient_user_id, is_read, created_at),
    CONSTRAINT fk_notifications_recipient FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_notifications_thesis FOREIGN KEY (thesis_id) REFERENCES theses(id) ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action_type VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS adviser_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    adviser_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected', 'cancelled') DEFAULT 'pending',
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_adv_req_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_adv_req_adviser FOREIGN KEY (adviser_id) REFERENCES users(id) ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS release_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    adviser_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_release_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_release_adviser FOREIGN KEY (adviser_id) REFERENCES users(id) ON DELETE CASCADE
)");

// Safety migration for missing message column
try {
    $pdo->exec("ALTER TABLE adviser_requests ADD COLUMN message TEXT AFTER status");
} catch (PDOException $e) {}

// Safety migration for profile fields in users table
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN bio TEXT AFTER email");
    $pdo->exec("ALTER TABLE users ADD COLUMN research_interests TEXT AFTER bio");
    $pdo->exec("ALTER TABLE users ADD COLUMN experience TEXT AFTER research_interests");
    $pdo->exec("ALTER TABLE users ADD COLUMN profile_pic VARCHAR(255) AFTER experience");
} catch (PDOException $e) {}

// Safety migration for co_authors in theses table
try {
    $pdo->exec("ALTER TABLE theses ADD COLUMN co_authors TEXT AFTER title");
} catch (PDOException $e) {}

function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

function current_user() {
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

function require_login($allowedRoles = []) {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }


    if (!empty($allowedRoles) && !in_array($_SESSION['user']['role'], $allowedRoles, true)) {
        header('HTTP/1.1 403 Forbidden');
        echo '<h1>403 Forbidden</h1><p>You do not have access to this page.</p>';
        exit;
    }
}

function time_ago($timestamp) {
    if (!$timestamp) return 'n/a';
    $time = is_numeric($timestamp) ? $timestamp : strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Just now';
    $intervals = [
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute'
    ];
    
    foreach ($intervals as $secs => $label) {
        $div = $diff / $secs;
        if ($div >= 1) {
            $n = round($div);
            return $n . ' ' . $label . ($n > 1 ? 's' : '') . ' ago';
        }
    }
    return 'Just now';
}

function format_size($bytes) {
    if ($bytes <= 0) return '0 B';
    $base = log($bytes, 1024);
    $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];
    return round(pow(1024, $base - floor($base)), 2) . ' ' . $suffixes[floor($base)];
}
