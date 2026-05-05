<?php
require_once __DIR__ . '/../includes/session.php';

session_unset();
if (ini_get("session.use_cookies")) {
    setcookie(session_name(), '', time() - 3600, '/');
}
session_destroy();

header('Location: ' . BASE_URL . 'auth/login.php');
exit;
