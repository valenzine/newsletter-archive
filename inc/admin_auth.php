<?php

/**
 * Admin Authentication Module
 * 
 * Provides secure admin authentication with:
 * - Session-based login
 * - Rate limiting with exponential backoff
 * - Remember me tokens (14 days)
 * - CSRF protection
 */

// Constants
define('MAX_LOGIN_ATTEMPTS', 10);
define('LOCKOUT_WINDOW', 15 * 60); // 15 minutes
define('REMEMBER_ME_DURATION', 14 * 24 * 60 * 60); // 14 days
define('CSRF_TOKEN_NAME', 'admin_csrf_token');

/**
 * Ensure admin session is started with secure cookie settings
 * Call this before any session-dependent operations (auth checks, CSRF)
 */
function ensure_admin_session(): void {
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
}

/**
 * Check if admin account exists
 * @return bool
 */
function admin_exists(): bool {
    try {
        $pdo = get_database();
        $stmt = $pdo->query('SELECT COUNT(*) FROM admin');
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Create admin account (first-time setup)
 * @param string $username
 * @param string $email
 * @param string $password
 * @return bool
 */
function create_admin(string $username, string $email, string $password): bool {
    if (admin_exists()) {
        return false; // Only one admin allowed
    }
    
    $pdo = get_database();
    $stmt = $pdo->prepare('
        INSERT INTO admin (id, username, email, password_hash)
        VALUES (1, ?, ?, ?)
    ');
    
    return $stmt->execute([
        $username,
        $email,
        password_hash($password, PASSWORD_DEFAULT)
    ]);
}

/**
 * Get admin account
 * @return array|null
 */
function get_admin(): ?array {
    $pdo = get_database();
    $stmt = $pdo->query('SELECT * FROM admin WHERE id = 1');
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    return $admin ?: null;
}

/**
 * Verify admin credentials
 * @param string $username
 * @param string $password
 * @return array|false Admin data or false
 */
function verify_admin(string $username, string $password) {
    $admin = get_admin();
    
    if (!$admin) {
        return false;
    }
    
    if ($admin['username'] !== $username) {
        return false;
    }
    
    if (!password_verify($password, $admin['password_hash'])) {
        return false;
    }
    
    // Update last login
    $pdo = get_database();
    $stmt = $pdo->prepare('UPDATE admin SET last_login = CURRENT_TIMESTAMP WHERE id = 1');
    $stmt->execute();
    
    return $admin;
}

/**
 * Check if current user is authenticated as admin
 * @return bool
 */
function is_admin_authenticated(): bool {
    // Development mode bypass
    if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
        return true;
    }
    
    // Ensure session is started
    ensure_admin_session();
    
    // Check session
    if (isset($_SESSION['admin_id']) && $_SESSION['admin_id'] === 1) {
        return true;
    }
    
    // Check remember me cookie
    if (isset($_COOKIE['remember_admin'])) {
        $admin = verify_remember_token($_COOKIE['remember_admin']);
        if ($admin) {
            // Restore session
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            return true;
        }
    }
    
    return false;
}

/**
 * Login admin and start session
 * @param array $admin Admin data
 * @param bool $remember Create remember me token
 */
function admin_login(array $admin, bool $remember = false): void {
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    
    if ($remember) {
        create_remember_token($admin['id']);
    }
}

/**
 * Logout admin
 */
function admin_logout(): void {
    // Clear remember token if exists
    if (isset($_COOKIE['remember_admin'])) {
        delete_remember_token($_COOKIE['remember_admin']);
        setcookie('remember_admin', '', time() - 3600, '/setup/', '', true, true);
    }
    
    // Clear session
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_username']);
    session_destroy();
}

/**
 * Create remember me token
 * @param int $adminId
 */
function create_remember_token(int $adminId): void {
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + REMEMBER_ME_DURATION);
    
    $pdo = get_database();
    $stmt = $pdo->prepare('
        INSERT INTO remember_tokens (token_hash, admin_id, expires_at)
        VALUES (?, ?, ?)
    ');
    $stmt->execute([$tokenHash, $adminId, $expiresAt]);
    
    // Set cookie
    setcookie('remember_admin', $token, [
        'expires' => time() + REMEMBER_ME_DURATION,
        'path' => '/setup/',
        'domain' => '',
        'secure' => ($_ENV['APP_ENV'] ?? 'production') === 'production',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

/**
 * Verify remember me token
 * @param string $token
 * @return array|null Admin data or null
 */
function verify_remember_token(string $token): ?array {
    $tokenHash = hash('sha256', $token);
    
    $pdo = get_database();
    $stmt = $pdo->prepare('
        SELECT a.* FROM admin a
        JOIN remember_tokens rt ON a.id = rt.admin_id
        WHERE rt.token_hash = ? AND rt.expires_at > CURRENT_TIMESTAMP
    ');
    $stmt->execute([$tokenHash]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $admin ?: null;
}

/**
 * Delete remember token
 * @param string $token
 */
function delete_remember_token(string $token): void {
    $tokenHash = hash('sha256', $token);
    
    $pdo = get_database();
    $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE token_hash = ?');
    $stmt->execute([$tokenHash]);
}

/**
 * Clean up expired tokens
 */
function cleanup_expired_tokens(): void {
    $pdo = get_database();
    $pdo->exec('DELETE FROM remember_tokens WHERE expires_at < CURRENT_TIMESTAMP');
}

/**
 * Record login attempt
 * @param string $ip
 * @param bool $success
 */
function record_login_attempt(string $ip, bool $success): void {
    $pdo = get_database();
    $stmt = $pdo->prepare('
        INSERT INTO login_attempts (ip_address, success)
        VALUES (?, ?)
    ');
    $stmt->execute([$ip, $success ? 1 : 0]);
}

/**
 * Get recent failed attempts for IP
 * @param string $ip
 * @return int Count of failed attempts in lockout window
 */
function get_failed_attempts(string $ip): int {
    $pdo = get_database();
    $windowStart = date('Y-m-d H:i:s', time() - LOCKOUT_WINDOW);
    
    $stmt = $pdo->prepare('
        SELECT COUNT(*) FROM login_attempts
        WHERE ip_address = ? AND success = 0 AND attempted_at > ?
    ');
    $stmt->execute([$ip, $windowStart]);
    
    return (int)$stmt->fetchColumn();
}

/**
 * Check if IP is rate limited
 * @param string $ip
 * @return array ['limited' => bool, 'wait' => int seconds]
 */
function check_rate_limit(string $ip): array {
    $attempts = get_failed_attempts($ip);
    
    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
        // Calculate wait time with exponential backoff
        $overAttempts = $attempts - MAX_LOGIN_ATTEMPTS;
        $waitSeconds = min(pow(2, $overAttempts) * 60, LOCKOUT_WINDOW); // Max 15 min
        
        return ['limited' => true, 'wait' => $waitSeconds];
    }
    
    return ['limited' => false, 'wait' => 0];
}

/**
 * Clean up old login attempts
 */
function cleanup_login_attempts(): void {
    $pdo = get_database();
    $cutoff = date('Y-m-d H:i:s', time() - LOCKOUT_WINDOW * 2);
    
    $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?');
    $stmt->execute([$cutoff]);
}

/**
 * Generate CSRF token
 * @return string
 */
function generate_csrf_token(): string {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verify CSRF token
 * @param string $token
 * @return bool
 */
function verify_csrf_token(string $token): bool {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && 
           hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Get CSRF token input field HTML
 * @return string
 */
function csrf_field(): string {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Require admin authentication or redirect to login
 * @param bool $allowSetup Allow access if no admin exists (for first-time setup)
 */
function require_admin(bool $allowSetup = false): void {
    // Ensure session is started (required for CSRF and auth)
    ensure_admin_session();
    
    // Initialize admin tables if they don't exist
    ensure_admin_tables();
    
    // If no admin exists, redirect to setup (if allowed) or show error
    if (!admin_exists()) {
        if ($allowSetup) {
            return; // Allow access to setup
        }
        header('Location: /setup/setup.php');
        exit;
    }
    
    // Check authentication
    if (!is_admin_authenticated()) {
        header('Location: /setup/login.php');
        exit;
    }
}

/**
 * Ensure admin tables exist in database
 */
function ensure_admin_tables(): void {
    $pdo = get_database();
    
    // Check if admin table exists
    $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admin'");
    if ($result->fetch()) {
        return; // Tables already exist
    }
    
    // Create tables
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            username TEXT UNIQUE NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_address TEXT NOT NULL,
            attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            success INTEGER DEFAULT 0
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip_address, attempted_at)");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS remember_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            token_hash TEXT UNIQUE NOT NULL,
            admin_id INTEGER NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES admin(id) ON DELETE CASCADE
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_remember_tokens_hash ON remember_tokens(token_hash)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_remember_tokens_expires ON remember_tokens(expires_at)");
}
