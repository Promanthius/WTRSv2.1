# WMSU Thesis Repository - Deployment Guide

This guide details the steps required to deploy the **WMSU Thesis Repository (WTRS)** from a local development environment (like XAMPP) to a production server (cPanel/Shared Hosting, VPS, or dedicated server).

## 1. System Requirements

*   **Web Server:** Apache or Nginx
*   **PHP:** Version 8.0 or higher (`pdo_mysql` extension required)
*   **Database:** MySQL 8.0+ or MariaDB 10.4+
*   **SSL Certificate:** Highly recommended (Let's Encrypt or similar) for HTTPS.

## 2. Preparing the Files

1.  Zip your entire `wtrs` project directory.
2.  Upload the zip file to your server (e.g., via cPanel File Manager or FTP/SFTP) into your `public_html` directory (or `/var/www/html` for VPS).
3.  Extract the zip file. Let's assume you extract it so the path is `yourdomain.com/wtrs/`. 

## 3. Database Migration

1.  Export your local database:
    *   Open `phpMyAdmin` localized on your XAMPP server.
    *   Select the `wtrs_db` database.
    *   Click the **Export** tab and export it as an `.sql` file.
2.  Create a production database:
    *   In your hosting panel (e.g., cPanel MySQL Databases), create a new database (e.g., `prod_wtrs_db`).
    *   Create a new user with a strong password (e.g., `prod_wtrs_user`) and assign it all privileges to the new database.
3.  Import the database:
    *   Open `phpMyAdmin` on your production server.
    *   Select the newly created database.
    *   Click the **Import** tab and upload the `.sql` file you exported in step 1.

## 4. Configuration Updates

You must update the core configuration files to point to your new production settings.

### Update `includes/db.php`
Open `includes/db.php` on your production server and update the credential variables:

```php
// --- Database Credentials ---
$host = 'localhost'; // Usually 'localhost', but sometimes an IP provided by your host
$db   = 'prod_wtrs_db';   // Your production database name
$user = 'prod_wtrs_user'; // Your production database username
$pass = 'your_secure_password'; // Your production database password
```

### Update `includes/config.php`
Open `includes/config.php` and set the environment and base URL:

```php
// Switch to production mode to hide sensitive errors and log them instead
define('ENVIRONMENT', 'production');

// If deploying to a subdirectory like yourdomain.com/wtrs/:
define('BASE_URL', '/wtrs/');

// If deploying to the root domain like yourdomain.com:
// define('BASE_URL', '/');
```

## 5. Directory Permissions

Ensure the web server has permission to read the files and write to specific directories:

*   **General Files:** `644`
*   **Directories:** `755`
*   **Uploads Directory (`public/uploads`):** The web server *must* have write permissions here.
    *   Via SSH: `chmod -R 755 public/uploads` (or `775` depending on owner/group setup).
*   **Error Logs:** If you specify a custom error log in `config.php`, make sure the server can write to that file.

## 6. Security Hardening Complete

The following hardening measures are already built-in and will activate in production:

*   **Secure Sessions:** If you access the site via HTTPS, session cookies will automatically be marked `Secure` and `HttpOnly`.
*   **Uploads Protection:** A `.htaccess` file in `public/uploads` prevents the execution of PHP scripts (or any other executables) even if they are somehow uploaded.
*   **Error Suppression:** With `ENVIRONMENT` set to `production`, detailed PHP errors will be logged instead of displayed on the screen, preventing information leakage.

## 7. Final Checklist

- [ ] Files uploaded and extracted.
- [ ] Database imported.
- [ ] `includes/db.php` updated with production credentials.
- [ ] `includes/config.php` set to `ENVIRONMENT = 'production'`.
- [ ] `includes/config.php` set with correct `BASE_URL`.
- [ ] Upload directory permissions checked.
- [ ] SSL certificate installed and site accessed via HTTPS.
- [ ] Tested logging in as Admin/Faculty/Student.
- [ ] Tested uploading a PDF.
- [ ] Tested downloading a PDF.
