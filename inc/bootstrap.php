<?php

/**
 * Application Bootstrap
 * 
 * This file loads environment configuration and application constants.
 * This is the main entry point for configuration.
 * 
 * Configuration is hybrid:
 * 1. Load .env file (gitignored, deployment-specific)
 * 2. Override with database settings if set (user-editable via admin UI)
 * 
 * User-specific configuration should be in .env file (gitignored).
 * User-customizable settings can be edited via /setup/settings.php
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables from .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
try {
    $dotenv->load();
} catch (Exception $e) {
    // If .env is missing or invalid, continue with defaults
    error_log('Warning: .env file not loaded - ' . $e->getMessage());
}

// Load database functions for settings hybrid approach
require_once __DIR__ . '/database.inc.php';

// -----------------------------------------------------------------------------
// Site Configuration (Hybrid: .env + Database)
// -----------------------------------------------------------------------------

// Base configuration from .env (always used)
$site_base_url = $_ENV['SITE_BASE_URL'] ?? 'http://localhost:8182';
$site_name = $_ENV['SITE_NAME'] ?? 'Newsletter Archive';
$admin_email = $_ENV['ADMIN_EMAIL'] ?? 'admin@localhost';

// Social Metadata / SEO - Database overrides .env if set
$site_title = get_setting('site_title') ?? ($_ENV['SITE_TITLE'] ?? $site_name);
$site_description = get_setting('site_description') ?? ($_ENV['SITE_DESCRIPTION'] ?? 'Archive of past email campaigns');
$og_image = get_setting('og_image') ?? ($_ENV['OG_IMAGE'] ?? '/img/share.jpg');
$twitter_site = get_setting('twitter_site') ?? ($_ENV['TWITTER_SITE'] ?? '');
$twitter_creator = get_setting('twitter_creator') ?? ($_ENV['TWITTER_CREATOR'] ?? '');

// Locale and timezone settings - Database overrides .env if set
$locale = get_setting('locale') ?? ($_ENV['LOCALE'] ?? 'en');
setlocale(LC_TIME, $locale);
$timezone = new DateTimeZone($_ENV['TIMEZONE'] ?? 'UTC');

// Load i18n system with the configured locale
require_once __DIR__ . '/i18n.php';

// -----------------------------------------------------------------------------
// Environment & Debug
// -----------------------------------------------------------------------------

$is_local = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1']) 
            || str_starts_with($_SERVER['SERVER_NAME'] ?? '', 'localhost:');

$dev_mode = ($_ENV['APP_ENV'] ?? 'production') === 'development';

$debug_mode = filter_var($_ENV['DEBUG_MODE'] ?? false, FILTER_VALIDATE_BOOLEAN);
if (isset($_GET['debug']) && htmlspecialchars($_GET['debug']) === 'true') {
    $debug_mode = true;
}

// -----------------------------------------------------------------------------
// API Keys
// -----------------------------------------------------------------------------

// MailerLite API key for syncing campaigns
$mailerlite_api_key = $_ENV['MAILERLITE_API_KEY'] ?? '';

// Cron token for automated tasks - load from database settings
$cron_token = get_setting('cron_token');

// -----------------------------------------------------------------------------
// Path Configuration
// -----------------------------------------------------------------------------

// Mailchimp campaign content directory (legacy imports)
$mailchimp_campaigns_directory = __DIR__ . '/../source/campaigns_content';

// MailerLite campaign content directory
$mailerlite_campaigns_directory = __DIR__ . '/../source/mailerlite_campaigns';

// -----------------------------------------------------------------------------
// PHP Settings
// -----------------------------------------------------------------------------

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// -----------------------------------------------------------------------------
// Helper Functions
// -----------------------------------------------------------------------------

/**
 * Get the application version from composer.json
 */
function get_composer_version(): string {
    static $version = null;
    if ($version === null) {
        $composer_file = __DIR__ . '/../composer.json';
        if (file_exists($composer_file)) {
            $composer = json_decode(file_get_contents($composer_file), true);
            $version = $composer['version'] ?? '0.0.0';
        } else {
            $version = '0.0.0';
        }
    }
    return $version;
}
