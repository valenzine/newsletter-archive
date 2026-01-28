<?php
/**
 * Setup and Administration Interface
 * 
 * Admin dashboard for managing the campaign archive.
 * Protected by database-backed authentication
 */

header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/database.inc.php';
require_once __DIR__ . '/../inc/functions.php';
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

// Handle first-time setup
if (!admin_exists()) {
    $setup_error = '';
    $setup_success = false;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_admin'])) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            $setup_error = 'Invalid request. Please try again.';
        } else {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $password_confirm = $_POST['password_confirm'] ?? '';
            
            // Validate
            if (empty($username) || empty($email) || empty($password)) {
                $setup_error = 'All fields are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $setup_error = 'Invalid email address.';
            } elseif (strlen($password) < 8) {
                $setup_error = 'Password must be at least 8 characters long.';
            } elseif ($password !== $password_confirm) {
                $setup_error = 'Passwords do not match.';
            } else {
                // Create admin
                if (create_admin($username, $email, $password)) {
                    // Auto-login after account creation
                    $admin = get_admin(); // Get the newly created admin
                    admin_login($admin, false); // Don't remember
                    
                    // Redirect to onboarding checklist
                    header('Location: /setup/setup.php?onboarding=1');
                    exit;
                } else {
                    $setup_error = 'Failed to create admin account.';
                }
            }
        }
    }
    
    // Show first-time setup form
    $page_config = [
        'title' => 'First-Time Setup - ' . $site_title,
        'noindex' => true,
        'custom_css' => '/css/admin.css',
    ];
    
    // Output unified page head
    require_once __DIR__ . '/../inc/page_head.inc.php';
    ?>
        <div class="setup-container">
            <div class="setup-box">
                <h1 class="setup-title">Welcome! 👋</h1>
                <p class="setup-description">Let's set up your admin account to get started.</p>
                
                <?php if ($setup_error): ?>
                    <div class="error-message"><?= htmlspecialchars($setup_error) ?></div>
                <?php endif; ?>
                
                <?php if ($setup_success): ?>
                    <div class="success-message">
                        ✅ Admin account created successfully!<br>
                        <a href="/setup/login.php">Click here to log in</a>
                    </div>
                <?php else: ?>
                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required autofocus
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" required
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password (minimum 8 characters)</label>
                            <input type="password" id="password" name="password" required minlength="8">
                        </div>
                        
                        <div class="form-group">
                            <label for="password_confirm">Confirm Password</label>
                            <input type="password" id="password_confirm" name="password_confirm" required minlength="8">
                        </div>
                        
                        <button type="submit" name="setup_admin" class="btn-setup">
                            Create Admin Account
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Require authentication
require_admin(false);

$action = $_GET['action'] ?? '';
$time_start = microtime(true);
$admin = get_admin();

// Configure page head
$page_config = [
    'title' => __('admin.title') . ' | ' . $site_title,
    'body_class' => 'admin-page',
    'custom_css' => '/css/admin.css',
];

// Output unified page head
require_once __DIR__ . '/../inc/page_head.inc.php';
?>
    <div class="admin-header">
        <div class="admin-header-content">
            <h1>🛠️ <?php _e('admin.title'); ?></h1>
            <div class="admin-user-info">
                <span>Logged in as: <?= htmlspecialchars($admin['username']) ?></span>
                <span>v<?= htmlspecialchars(get_composer_version()) ?></span>
                <a href="/setup/settings.php" class="btn-link">⚙️ Settings</a>
                <a href="/setup/logout.php" class="btn-link">Logout</a>
                <a href="/" class="btn-link">← View Site</a>
            </div>
        </div>
    </div>
    
    <div class="admin-container">
        <div class="admin-content">
            <?php

            switch ($action) {
                case 'campaigns':
                    echo "<h2 class='page-title'>Manage Campaigns</h2>";
                    
                    // Handle hide/unhide actions
                    if (isset($_POST['toggle_hidden'])) {
                        $campaign_id = $_POST['campaign_id'] ?? '';
                        $campaign = get_campaign($campaign_id);
                        if ($campaign) {
                            $new_status = !$campaign['hidden'];
                            set_campaign_hidden($campaign_id, $new_status);
                            echo "<div class='alert alert-success'>Campaign " . ($new_status ? 'hidden' : 'unhidden') . ".</div>";
                        }
                    }
                    
                    $campaigns = get_campaigns(['hidden' => true, 'limit' => 100]);
                    $total = get_campaign_count(true);
                    $hidden = get_campaign_count(true) - get_campaign_count(false);
                    
                    echo "<div class='stats-grid'>";
                    echo "<div class='stat-card'><div class='stat-value'>$total</div><div class='stat-label'>Total</div></div>";
                    echo "<div class='stat-card'><div class='stat-value'>$hidden</div><div class='stat-label'>Hidden</div></div>";
                    echo "</div>";
                    
                    echo "<table class='admin-table'>";
                    echo "<thead><tr><th>Subject</th><th>Date</th><th>Source</th><th>Status</th><th>Actions</th></tr></thead>";
                    echo "<tbody>";
                    foreach ($campaigns as $campaign) {
                        $status = $campaign['hidden'] ? '🚫 Hidden' : '✅ Visible';
                        $btn_text = $campaign['hidden'] ? 'Unhide' : 'Hide';
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars(substr($campaign['subject'], 0, 50)) . "</td>";
                        echo "<td>" . date('M j, Y', strtotime($campaign['sent_at'])) . "</td>";
                        echo "<td>" . htmlspecialchars($campaign['source']) . "</td>";
                        echo "<td>" . $status . "</td>";
                        echo "<td>";
                        echo "<form method='post' style='display:inline'>";
                        echo "<input type='hidden' name='campaign_id' value='" . htmlspecialchars($campaign['id']) . "'>";
                        echo "<button type='submit' name='toggle_hidden' class='btn btn-small'>$btn_text</button>";
                        echo "</form>";
                        echo "</td>";
                        echo "</tr>";
                    }
                    echo "</tbody></table>";
                    echo "<p><a href='?' class='btn btn-secondary'>Back to Dashboard</a></p>";
                    break;

                case 'reset':
                    echo "<h2 class='page-title'>Reset Campaign Database</h2>";
                    
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reset'])) {
                        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                            echo "<div class='alert alert-error'>Invalid request. Please try again.</div>";
                        } else {
                            try {
                                $db = get_database();
                                $db->exec('DELETE FROM campaigns');
                                $db->exec('DELETE FROM campaigns_fts');
                                update_setting('last_sync', null);
                                
                                echo "<div class='alert alert-success'>";
                                echo "✅ <strong>Database reset successfully!</strong><br>";
                                echo "All campaigns have been deleted. Your admin account remains intact.";
                                echo "</div>";
                                echo "<p><a href='?' class='btn btn-primary'>← Back to Dashboard</a></p>";
                            } catch (Exception $e) {
                                echo "<div class='alert alert-error'>Error resetting database: " . htmlspecialchars($e->getMessage()) . "</div>";
                            }
                        }
                    } else {
                        // Show confirmation form
                        $campaign_count = get_campaign_count(true);
                        echo "<div class='result-box' style='border-left: 4px solid #ef4444;'>";
                        echo "<h3>⚠️ Warning: Irreversible Action</h3>";
                        echo "<p>This will permanently delete <strong>all {$campaign_count} campaigns</strong> from the database.</p>";
                        echo "<p>Your admin account and site settings will be preserved.</p>";
                        echo "<p style='margin-top: 20px;'><strong>This action cannot be undone!</strong></p>";
                        echo "</div>";
                        
                        echo "<form method='POST' action='?action=reset' style='margin-top: 20px;'>";
                        echo "<input type='hidden' name='csrf_token' value='" . htmlspecialchars(generate_csrf_token()) . "'>";
                        echo "<button type='submit' name='confirm_reset' class='btn' style='background: #ef4444; margin-right: 12px;' onclick='return confirm(\"Are you absolutely sure? This will delete all campaigns!\");'>";
                        echo "🗑️ Yes, Reset Database";
                        echo "</button>";
                        echo "<a href='?' class='btn btn-secondary'>Cancel</a>";
                        echo "</form>";
                    }
                    break;

                default:
                    // Dashboard
                    $last_sync = get_setting('last_sync');
                    $total_campaigns = get_campaign_count(true);
                    $hidden_campaigns = get_campaign_count(true) - get_campaign_count(false);
                    $show_onboarding = isset($_GET['onboarding']) || ($total_campaigns == 0 && empty($mailerlite_api_key));
                    
                    echo "<h2 class='page-title'>Dashboard</h2>";
                    
                    // Onboarding checklist for new admins
                    if ($show_onboarding):
                        $has_api_key = !empty($mailerlite_api_key);
                        $has_customized = get_setting('site_title') !== null; // Check if any DB customization done
                        $has_campaigns = $total_campaigns > 0;
                        
                        $steps_complete = ($has_api_key ? 1 : 0) + ($has_customized ? 1 : 0) + ($has_campaigns ? 1 : 0);
                        $progress_percent = round(($steps_complete / 3) * 100);
                    ?>
                        <div class="result-box" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; margin-bottom: 30px;">
                            <h2 style="color: white; margin-top: 0;">👋 Welcome! Let's get your newsletter archive set up</h2>
                            <div style="background: rgba(255,255,255,0.2); border-radius: 8px; padding: 8px 16px; margin: 15px 0;">
                                <div style="font-size: 14px; margin-bottom: 5px;">Setup Progress: <?= $steps_complete ?>/3 steps complete</div>
                                <div style="background: rgba(255,255,255,0.3); border-radius: 4px; height: 8px; overflow: hidden;">
                                    <div style="background: #4ade80; height: 100%; width: <?= $progress_percent ?>%; transition: width 0.3s;"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="result-box">
                            <h3>📋 Setup Checklist</h3>
                            <div style="display: flex; flex-direction: column; gap: 15px; margin-top: 20px;">
                                
                                <!-- Step 1: Configure Settings -->
                                <div style="display: flex; gap: 15px; align-items: start; padding: 15px; background: <?= $has_customized ? '#f0fdf4' : '#f9fafb' ?>; border-radius: 8px; border-left: 4px solid <?= $has_customized ? '#22c55e' : '#d1d5db' ?>;">
                                    <div style="font-size: 24px; flex-shrink: 0;"><?= $has_customized ? '✅' : '⚪' ?></div>
                                    <div style="flex: 1;">
                                        <h4 style="margin: 0 0 8px 0;">Customize Your Site</h4>
                                        <p style="margin: 0 0 12px 0; color: #6b7280;">Set your site title, description, and social sharing images to match your brand.</p>
                                        <?php if (!$has_customized): ?>
                                            <a href="/setup/settings.php" class="btn btn-primary" style="display: inline-block;">⚙️ Go to Settings</a>
                                        <?php else: ?>
                                            <span style="color: #22c55e; font-weight: 500;">✓ Complete! <a href="/setup/settings.php" style="margin-left: 8px;">Edit Settings</a></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Step 2: Add API Key -->
                                <div style="display: flex; gap: 15px; align-items: start; padding: 15px; background: <?= $has_api_key ? '#f0fdf4' : '#f9fafb' ?>; border-radius: 8px; border-left: 4px solid <?= $has_api_key ? '#22c55e' : '#d1d5db' ?>;">
                                    <div style="font-size: 24px; flex-shrink: 0;"><?= $has_api_key ? '✅' : '⚪' ?></div>
                                    <div style="flex: 1;">
                                        <h4 style="margin: 0 0 8px 0;">Add MailerLite API Key</h4>
                                        <p style="margin: 0 0 12px 0; color: #6b7280;">Add your MailerLite API key to <code>.env</code> file to enable campaign syncing.</p>
                                        <?php if (!$has_api_key): ?>
                                            <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; font-family: monospace; font-size: 13px; margin-bottom: 8px;">
                                                MAILERLITE_API_KEY=your-api-key-here
                                            </div>
                                            <p style="margin: 0; font-size: 14px; color: #6b7280;">Get your API key from: <a href="https://dashboard.mailerlite.com/integrations/api" target="_blank">MailerLite Dashboard → Integrations → API</a></p>
                                        <?php else: ?>
                                            <span style="color: #22c55e; font-weight: 500;">✓ API key configured!</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Step 3: Sync Campaigns -->
                                <div style="display: flex; gap: 15px; align-items: start; padding: 15px; background: <?= $has_campaigns ? '#f0fdf4' : '#f9fafb' ?>; border-radius: 8px; border-left: 4px solid <?= $has_campaigns ? '#22c55e' : '#d1d5db' ?>;">
                                    <div style="font-size: 24px; flex-shrink: 0;"><?= $has_campaigns ? '✅' : '⚪' ?></div>
                                    <div style="flex: 1;">
                                        <h4 style="margin: 0 0 8px 0;">Import Your Campaigns</h4>
                                        <p style="margin: 0 0 12px 0; color: #6b7280;">Sync your email campaigns from MailerLite or import from Mailchimp.</p>
                                        <?php if (!$has_campaigns): ?>
                                            <?php if ($has_api_key): ?>
                                                <a href="mailerlite.php" class="btn btn-primary" style="display: inline-block; margin-right: 8px;">🔄 Sync from MailerLite</a>
                                                <a href="import_mailchimp.php" class="btn btn-secondary" style="display: inline-block;">📦 Import from Mailchimp</a>
                                            <?php else: ?>
                                                <span style="color: #9ca3af;">Add API key first to sync campaigns</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: #22c55e; font-weight: 500;">✓ You have <?= $total_campaigns ?> campaign<?= $total_campaigns != 1 ? 's' : '' ?>! <a href="mailerlite.php" style="margin-left: 8px;">Sync More</a></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                            </div>
                            
                            <?php if ($steps_complete >= 3): ?>
                                <div style="margin-top: 20px; padding: 15px; background: #f0fdf4; border-radius: 8px; text-align: center;">
                                    <h4 style="color: #15803d; margin-top: 0;">🎉 Setup Complete!</h4>
                                    <p style="margin-bottom: 15px;">Your newsletter archive is ready. <a href="/" target="_blank">View your public archive →</a></p>
                                    <a href="?" class="btn btn-secondary" style="display: inline-block;">Hide Checklist</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class='stats-grid'>
                        <div class='stat-card'><div class='stat-value'><?= $total_campaigns ?></div><div class='stat-label'>Total Campaigns</div></div>
                        <div class='stat-card'><div class='stat-value'><?= $hidden_campaigns ?></div><div class='stat-label'>Hidden</div></div>
                        <div class='stat-card'><div class='stat-value'><?= $last_sync ?: 'Never' ?></div><div class='stat-label'>Last Sync</div></div>
                    </div>
                    
                    <div class='action-grid'>
                        <a href='mailerlite.php' class='action-card featured'>
                            <h3>🔄 Sync MailerLite</h3>
                            <p>Fetch and import campaigns from your MailerLite account.</p>
                        </a>
                        
                        <a href='import_mailchimp.php' class='action-card'>
                            <h3>📦 Import Mailchimp</h3>
                            <p>One-time import of campaigns from Mailchimp ZIP export.</p>
                        </a>
                        
                        <a href='?action=campaigns' class='action-card'>
                            <h3>📋 Manage Campaigns</h3>
                            <p>View, hide, or unhide campaigns.</p>
                        </a>
                        
                        <a href='?action=reset' class='action-card' style='border-left: 4px solid #ef4444;'>
                            <h3>🗑️ Reset Database</h3>
                            <p>Delete all campaigns and start fresh. (Keeps admin account)</p>
                        </a>
                    </div>
                    
                    <?php
                    break;
            }

            ?>
        </div>
        
        <div class="footer-time">
            <?php
            $time_end = microtime(true);
            $execution_time = ($time_end - $time_start);
            echo '<strong>Execution Time:</strong> ' . number_format($execution_time, 3) . 's';
            ?>
        </div>
    </div>
</body>
</html>
