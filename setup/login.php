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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Login - <?= htmlspecialchars($site_title ?? 'Newsletter Archive') ?></title>
    <link rel="stylesheet" href="/css/admin.css?ver=<?= htmlspecialchars(get_composer_version()) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/img/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/img/apple-touch-icon.png">
    <style>
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 0 20px;
        }
        .login-box {
            background: var(--card-bg, #fff);
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .login-title {
            text-align: center;
            margin-bottom: 25px;
            color: var(--text-color, #333);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color, #ddd);
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color, #007bff);
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: var(--primary-color, #007bff);
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-login:hover {
            background: var(--primary-hover, #0056b3);
        }
        .btn-login:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--text-muted, #666);
        }
    </style>
</head>
<body class="admin-page">
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
