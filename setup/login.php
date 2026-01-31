<?php
/**
 * Admin Login Page
 * 
 * Secure login with:
 * - Rate limiting (exponential backoff)
 * - CSRF protection
 * - Remember me (14 days)
 */

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/database.inc.php';
require_once __DIR__ . '/../inc/admin_auth.php';

// Start session
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

// Initialize tables
ensure_admin_tables();

// If no admin exists, redirect to setup
if (!admin_exists()) {
    header('Location: /setup/setup.php');
    exit;
}

// Already logged in?
if (is_admin_authenticated()) {
    header('Location: /setup/setup.php');
    exit;
}

$error = '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// Check rate limit before processing
$rateLimit = check_rate_limit($ip);
if ($rateLimit['limited']) {
    $waitMinutes = ceil($rateLimit['wait'] / 60);
    $error = "Too many login attempts. Please wait {$waitMinutes} minute(s) before trying again.";
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$rateLimit['limited']) {
    // Verify CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);
        
        $admin = verify_admin($username, $password);
        
        if ($admin) {
            // Success
            record_login_attempt($ip, true);
            admin_login($admin, $remember);
            
            // Cleanup old data
            cleanup_login_attempts();
            cleanup_expired_tokens();
            
            header('Location: /setup/setup.php');
            exit;
        } else {
            // Failed
            record_login_attempt($ip, false);
            $error = 'Invalid username or password.';
            
            // Check if now rate limited
            $rateLimit = check_rate_limit($ip);
            if ($rateLimit['limited']) {
                $waitMinutes = ceil($rateLimit['wait'] / 60);
                $error = "Too many login attempts. Please wait {$waitMinutes} minute(s) before trying again.";
            }
        }
    }
}

// Configure page head
$page_config = [
    'title' => 'Admin Login - ' . $site_title,
    'noindex' => true,
    'custom_css' => '/css/admin.css',
    'body_class' => 'admin-page',
];


// Output unified page head
require_once __DIR__ . '/../inc/page_head.inc.php';
?>

    <div class="login-container">
        <div class="login-box">
            <h1 class="login-title">Admin Login</h1>
            
            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <?= csrf_field() ?>
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required 
                           autocomplete="username" autofocus
                           <?= $rateLimit['limited'] ? 'disabled' : '' ?>>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required
                           autocomplete="current-password"
                           <?= $rateLimit['limited'] ? 'disabled' : '' ?>>
                </div>
                
                <div class="form-group checkbox-group">
                    <input type="checkbox" id="remember" name="remember" value="1"
                           <?= $rateLimit['limited'] ? 'disabled' : '' ?>>
                    <label for="remember">Remember me for 14 days</label>
                </div>
                
                <button type="submit" class="btn-login" <?= $rateLimit['limited'] ? 'disabled' : '' ?>>
                    Log In
                </button>
            </form>
            
            <a href="/" class="back-link">← Back to Archive</a>
        </div>
    </div>
</body>
</html>
