<?php
/**
 * WMSU Thesis Repository System
 * Database Configuration using Secure PDO Connections
 */

// Include central configuration to access the ENVIRONMENT constant
require_once __DIR__ . '/config.php';

// --- Database Credentials ---
// Update these variables for your production database
$host = '127.0.0.1';
$db   = 'wtrs_db';
$user = 'root'; // Standard XAMPP default, change in production
$pass = '';     // Standard XAMPP default, change in production
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// Set strict PDO options for secure error handling and native fetching
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // We catch exceptions instead of silent failures
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // Important for SQL Injection defense
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
        // Secure production error: Do not expose system/database details
        error_log("Database Connection Error: " . $e->getMessage()); // Log internally
        die("An error occurred while connecting to the database. Please try again later. If the issue persists, contact the system administrator.");
    } else {
        // Development error: Show full trace for debugging
        die("Database Connection Failure. Please check your credentials or if MySQL is running. \nError: " . $e->getMessage());
    }
}
?>
