<?php
/**
 * Admin Logout
 * 
 * Clears session and remember me token
 */

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/database.inc.php';
require_once __DIR__ . '/../inc/admin_auth.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 3600,
        'path' => '/setup/',
        'domain' => '',
        'secure' => ($_ENV['APP_ENV'] ?? 'production') === 'production',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Perform logout
admin_logout();

// Redirect to login
header('Location: /setup/login.php');
exit;
