<?php

/**
 * Session Management (Admin-only)
 * 
 * This file is only needed for admin pages in /setup/.
 * The public archive does not require sessions.
 * 
 * NOTE: Admin authentication is now handled by inc/admin_auth.php
 * This file is kept for backwards compatibility but may be removed in future.
 */

if (!defined('SESSION_LIFETIME')) {
    define('SESSION_LIFETIME', 3600); // 1 hour for admin sessions
}

// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/setup/',
        'domain' => '',
        'secure' => ($_ENV['APP_ENV'] ?? 'production') === 'production',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    session_start();
}
