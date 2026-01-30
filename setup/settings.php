<?php
/**
 * Site Settings Interface
 * 
 * Admin interface for configuring site-wide settings.
 * Database values override .env defaults (hybrid configuration).
 */

header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/database.inc.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/admin_auth.php';

// Require admin authentication
require_admin(false);

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        try {
            // Site Settings
            update_setting('site_title', !empty($_POST['site_title']) ? trim($_POST['site_title']) : null);
            update_setting('site_description', !empty($_POST['site_description']) ? trim($_POST['site_description']) : null);
            
            // Social Metadata
            update_setting('og_image', !empty($_POST['og_image']) ? trim($_POST['og_image']) : null);
            update_setting('twitter_site', !empty($_POST['twitter_site']) ? trim($_POST['twitter_site']) : null);
            update_setting('twitter_creator', !empty($_POST['twitter_creator']) ? trim($_POST['twitter_creator']) : null);
            
            // Welcome Page Settings
            update_setting('welcome_enabled', isset($_POST['welcome_enabled']) ? '1' : '0');
            update_setting('welcome_title', !empty($_POST['welcome_title']) ? trim($_POST['welcome_title']) : null);
            update_setting('welcome_description', !empty($_POST['welcome_description']) ? trim($_POST['welcome_description']) : null);
            update_setting('welcome_subscribe_url', !empty($_POST['welcome_subscribe_url']) ? trim($_POST['welcome_subscribe_url']) : null);
            update_setting('welcome_subscribe_text', !empty($_POST['welcome_subscribe_text']) ? trim($_POST['welcome_subscribe_text']) : null);
            update_setting('welcome_archive_button_text', !empty($_POST['welcome_archive_button_text']) ? trim($_POST['welcome_archive_button_text']) : null);
            
            // Automation Settings
            update_setting('cron_token', !empty($_POST['cron_token']) ? trim($_POST['cron_token']) : null);
            
            // Analytics Settings - validate Google Analytics 4 Measurement ID format
            $ga_id = !empty($_POST['google_analytics_id']) ? trim($_POST['google_analytics_id']) : null;
            if ($ga_id !== null && !preg_match('/^G-[A-Z0-9]+$/', $ga_id)) {
                throw new Exception('Invalid Google Analytics 4 Measurement ID format. Must be in format G-XXXXXXXXXX (e.g., G-ABC123DEF4)');
            }
            update_setting('google_analytics_id', $ga_id);
            
            // Localization Settings
            update_setting('locale', !empty($_POST['locale']) ? trim($_POST['locale']) : null);
            
            $success_message = 'Settings saved successfully!';
        } catch (Exception $e) {
            $error_message = 'Error saving settings: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// Get current settings
$settings = get_all_settings();

// Helper function to get setting with .env fallback for display
function get_setting_display(string $key, string $env_key = '', $default = ''): string {
    global $settings;
    $db_value = $settings[$key] ?? null;
    if ($db_value !== null && $db_value !== '') {
        return $db_value;
    }
    if ($env_key && isset($_ENV[$env_key])) {
        return $_ENV[$env_key];
    }
    return $default;
}


// Configure page head
$page_config = [
    'title' => 'Site Settings - ' . $site_title,
    'noindex' => true,
    'body_class' => 'admin-page',
    'custom_css' => '/css/admin.css',
];

// Output unified page head
require_once __DIR__ . '/../inc/page_head.inc.php';
?>

    <!-- Admin Header -->
    <div class="admin-header">
        <div class="admin-header-content">
            <h1>⚙️ Site Settings</h1>
            <div class="admin-user-info">
                <span>Admin</span> | 
                <a href="/setup/setup.php">Dashboard</a> | 
                <a href="/setup/logout.php">Logout</a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="admin-container">
        <div class="admin-content settings-page">
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>
            
            <div class="result-box">
                <h2>Configuration Overview</h2>
                <p>
                    <strong>Hybrid Configuration:</strong> Settings can be defined in <code>.env</code> file (deployment-specific) 
                    and overridden here (user-customizable). Database values take precedence over <code>.env</code> defaults.
                </p>
                <p>
                    <strong>Empty fields below will use <code>.env</code> values.</strong> Enter a value to override, or clear to reset to <code>.env</code> default.
                </p>
            </div>
            
            <form method="POST" class="settings-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                
                <!-- Site Settings -->
                <div class="result-box">
                    <h2>Site Information</h2>
                    
                    <div class="form-group">
                        <label for="site_title">Site Title (og:title)</label>
                        <input 
                            type="text" 
                            id="site_title" 
                            name="site_title" 
                            value="<?= htmlspecialchars($settings['site_title'] ?? '') ?>"
                            placeholder="<?= htmlspecialchars($_ENV['SITE_TITLE'] ?? $_ENV['SITE_NAME'] ?? 'Newsletter Archive') ?> (from .env)"
                        >
                        <small>Page title used in browser tabs and social sharing. Leave empty to use <code>SITE_TITLE</code> or <code>SITE_NAME</code> from .env</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="site_description">Site Description</label>
                        <textarea 
                            id="site_description" 
                            name="site_description" 
                            rows="3"
                            placeholder="<?= htmlspecialchars($_ENV['SITE_DESCRIPTION'] ?? 'Archive of past email campaigns') ?> (from .env)"
                        ><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea>
                        <small>Meta description for SEO. Leave empty to use <code>SITE_DESCRIPTION</code> from .env</small>
                    </div>
                </div>
                
                <!-- Social Metadata -->
                <div class="result-box">
                    <h2>Social Sharing & SEO</h2>
                    
                    <div class="form-group">
                        <label for="og_image">Open Graph Image (og:image)</label>
                        <input 
                            type="text" 
                            id="og_image" 
                            name="og_image" 
                            value="<?= htmlspecialchars($settings['og_image'] ?? '') ?>"
                            placeholder="<?= htmlspecialchars($_ENV['OG_IMAGE'] ?? '/img/share.jpg') ?> (from .env)"
                        >
                        <small>Image URL for social sharing (1200x630px recommended). Can be relative path (<code>/img/share.jpg</code>) or full URL. Leave empty to use <code>OG_IMAGE</code> from .env</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="twitter_site">Twitter Site Handle</label>
                        <input 
                            type="text" 
                            id="twitter_site" 
                            name="twitter_site" 
                            value="<?= htmlspecialchars($settings['twitter_site'] ?? '') ?>"
                            placeholder="<?= htmlspecialchars($_ENV['TWITTER_SITE'] ?? 'yourusername') ?> (from .env)"
                        >
                        <small>Twitter username (without @) for site attribution. Leave empty to use <code>TWITTER_SITE</code> from .env or omit tag</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="twitter_creator">Twitter Creator Handle</label>
                        <input 
                            type="text" 
                            id="twitter_creator" 
                            name="twitter_creator" 
                            value="<?= htmlspecialchars($settings['twitter_creator'] ?? '') ?>"
                            placeholder="<?= htmlspecialchars($_ENV['TWITTER_CREATOR'] ?? 'yourusername') ?> (from .env)"
                        >
                        <small>Twitter username (without @) for content creator. Leave empty to use <code>TWITTER_CREATOR</code> from .env or omit tag</small>
                    </div>
                </div>
                
                <!-- Welcome Page Settings -->
                <div class="result-box">
                    <h2>Welcome Page</h2>
                    
                    <div class="form-group">
                        <label>
                            <input 
                                type="checkbox" 
                                id="welcome_enabled" 
                                name="welcome_enabled" 
                                value="1"
                                <?= (get_setting_display('welcome_enabled', '', '1') === '1') ? 'checked' : '' ?>
                            >
                            Enable welcome page for first-time visitors
                        </label>
                        <small>Shows a welcome overlay on first visit with archive introduction</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="welcome_title">Welcome Title</label>
                        <input 
                            type="text" 
                            id="welcome_title" 
                            name="welcome_title" 
                            value="<?= htmlspecialchars($settings['welcome_title'] ?? '') ?>"
                            placeholder="Welcome to Our Newsletter Archive (default)"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="welcome_description">Welcome Description</label>
                        <textarea 
                            id="welcome_description" 
                            name="welcome_description" 
                            rows="3"
                            placeholder="Explore our complete collection of newsletters... (default)"
                        ><?= htmlspecialchars($settings['welcome_description'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="welcome_subscribe_url">Subscribe URL (optional)</label>
                        <input 
                            type="url" 
                            id="welcome_subscribe_url" 
                            name="welcome_subscribe_url" 
                            value="<?= htmlspecialchars($settings['welcome_subscribe_url'] ?? '') ?>"
                            placeholder="https://yoursite.com/subscribe"
                        >
                        <small>Link to newsletter subscription page. Leave empty to hide subscribe button</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="welcome_subscribe_text">Subscribe Button Text</label>
                        <input 
                            type="text" 
                            id="welcome_subscribe_text" 
                            name="welcome_subscribe_text" 
                            value="<?= htmlspecialchars($settings['welcome_subscribe_text'] ?? '') ?>"
                            placeholder="Subscribe to Newsletter (default)"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="welcome_archive_button_text">Archive Button Text</label>
                        <input 
                            type="text" 
                            id="welcome_archive_button_text" 
                            name="welcome_archive_button_text" 
                            value="<?= htmlspecialchars($settings['welcome_archive_button_text'] ?? '') ?>"
                            placeholder="Browse Archive (default)"
                        >
                    </div>
                </div>
                
                <!-- Automation Settings -->
                <div class="result-box">
                    <h2>Automation & Cron Jobs</h2>
                    
                    <div class="form-group">
                        <label for="cron_token">Cron Token (Security Key)</label>
                        <input 
                            type="text" 
                            id="cron_token" 
                            name="cron_token" 
                            value="<?= htmlspecialchars($settings['cron_token'] ?? '') ?>"
                            placeholder="Leave empty to disable cron access"
                        >
                        <small>Secret token for automated sync via cron job. Generate with: <code>openssl rand -hex 32</code></small>
                    </div>
                    
                    <?php if (!empty($settings['cron_token'])): ?>
                        <div class="alert alert-info">
                            <strong>Cron URL:</strong><br>
                            <code><?= htmlspecialchars($site_base_url) ?>/setup/mailerlite.php?cron_token=<?= htmlspecialchars($settings['cron_token']) ?></code>
                            
                            <p class="cron-example-title"><strong>Example cron job (sync daily at 8 AM):</strong></p>
                            <code>0 8 * * * wget -qO- '<?= htmlspecialchars($site_base_url) ?>/setup/mailerlite.php?cron_token=<?= htmlspecialchars($settings['cron_token']) ?>'</code>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Analytics & Tracking -->
                <div class="result-box">
                    <h2>Analytics & Tracking</h2>
                    
                    <div class="form-group">
                        <label for="google_analytics_id">Google Analytics 4 Measurement ID</label>
                        <input 
                            type="text" 
                            id="google_analytics_id" 
                            name="google_analytics_id" 
                            value="<?= htmlspecialchars($settings['google_analytics_id'] ?? '') ?>"
                            placeholder="<?= htmlspecialchars($_ENV['GOOGLE_ANALYTICS_ID'] ?? 'G-XXXXXXXXXX') ?> (from .env)"
                        >
                        <small>Google Analytics 4 Measurement ID (format: G-XXXXXXXXXX). Find this in your <a href="https://analytics.google.com/" target="_blank" rel="noopener">Google Analytics</a> property settings under Admin → Data Streams → Web → Measurement ID. Leave empty to disable tracking.</small>
                    </div>
                </div>
                
                <!-- Localization Settings -->
                <div class="result-box">
                    <h2>Localization</h2>
                    
                    <div class="form-group">
                        <label for="locale">Language</label>
                        <select id="locale" name="locale">
                            <option value="">Default (from .env: <?= htmlspecialchars($_ENV['LOCALE'] ?? 'en') ?>)</option>
                            <option value="en" <?= ($settings['locale'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
                            <option value="es" <?= ($settings['locale'] ?? '') === 'es' ? 'selected' : '' ?>>Español</option>
                        </select>
                        <small>Choose the language for the interface. Leave as Default to use <code>LOCALE</code> from .env</small>
                    </div>
                </div>
                
                <!-- Save Button -->
                <div class="result-box">
                    <button type="submit" name="save_settings" class="btn btn-primary">
                        💾 Save All Settings
                    </button>
                </div>
            </form>
            
        </div>
    </div>

</body>
</html>
