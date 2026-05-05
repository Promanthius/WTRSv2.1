<?php
/**
 * WMSU Thesis Repository (WTRS)
 * Central Configuration
 */

// Deployment Environment: 'development' or 'production'
// NOTE: Change this to 'production' before deploying to live server.
define('ENVIRONMENT', 'development');

// Base URL configuration
// If deploying to a subdirectory: '/wtrs/'
// If deploying to a root domain: '/'
define('BASE_URL', '/wtrs/');

// Application Constants
define('APP_NAME', 'WMSU Thesis Repository');
define('INSTITUTION', 'Western Mindanao State University');

// Security & Error Reporting based on Environment
if (ENVIRONMENT === 'production') {
    // Production: Hide errors, log to file
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../error.log'); // Ensure web server has write access to this location
} else {
    // Development: Show all errors
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}
?>
