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
ensure_admin_session();

// Perform logout
admin_logout();

// Redirect to login
header('Location: /setup/login.php');
exit;
