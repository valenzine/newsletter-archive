<?php

/**
 * Newsletter Archive - Main Index
 * 
 * Public email campaign archive with inbox-style layout.
 * No authentication required - all campaigns are publicly viewable.
 */

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/database.inc.php';
require_once __DIR__ . '/inc/functions.php';

// Check if site needs initial setup
$needs_setup = false;
try {
    require_once __DIR__ . '/inc/admin_auth.php';
    ensure_admin_tables();
    $admin_exists = admin_exists();
    $campaign_count = get_campaign_count(false); // Only visible campaigns

    // Show setup prompt if no admin OR (admin exists but no campaigns and no API key)
    $needs_setup = !$admin_exists || ($campaign_count == 0 && empty($mailerlite_api_key));
} catch (Exception $e) {
    // If database error, definitely needs setup
    $needs_setup = true;
}

// If setup is needed, show setup page
if ($needs_setup && !isset($_GET['skip_setup'])) {
?>
    <!DOCTYPE html>
    <html lang="<?= htmlspecialchars(get_locale()) ?>">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Setup Required - <?= htmlspecialchars($site_title) ?></title>
<?php require_once __DIR__ . '/inc/head.inc.php'; ?>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }

            .setup-prompt {
                background: white;
                border-radius: 12px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                max-width: 600px;
                padding: 40px;
                text-align: center;
            }

            .setup-icon {
                font-size: 64px;
                margin-bottom: 20px;
            }

            h1 {
                font-size: 28px;
                color: #1f2937;
                margin-bottom: 16px;
            }

            p {
                font-size: 16px;
                color: #6b7280;
                margin-bottom: 30px;
                line-height: 1.6;
            }

            .btn {
                display: inline-block;
                background: #667eea;
                color: white;
                padding: 14px 32px;
                border-radius: 8px;
                text-decoration: none;
                font-weight: 500;
                font-size: 16px;
                transition: background 0.2s;
            }

            .btn:hover {
                background: #5568d3;
            }

            .btn-secondary {
                background: #e5e7eb;
                color: #374151;
                margin-left: 12px;
            }

            .btn-secondary:hover {
                background: #d1d5db;
            }

            .steps {
                text-align: left;
                background: #f9fafb;
                border-radius: 8px;
                padding: 24px;
                margin-top: 24px;
            }

            .steps h3 {
                font-size: 18px;
                color: #1f2937;
                margin-bottom: 16px;
            }

            .steps ol {
                padding-left: 20px;
            }

            .steps li {
                color: #4b5563;
                margin-bottom: 12px;
                line-height: 1.6;
            }

            .steps code {
                background: #fff;
                padding: 2px 6px;
                border-radius: 4px;
                font-size: 14px;
                color: #667eea;
            }
        </style>
    </head>

    <body>
        <div class="setup-prompt">
            <div class="setup-icon">
                <img src="/img/box.png" alt="Newsletter Archive" width="100">
            </div>
            <h1>Welcome to your Newsletter Archive!</h1>
            <p>This archive is not configured yet. Let's get you set up in just a few steps.</p>

            <?php if (!$admin_exists): ?>
                <a href="/setup/setup.php" class="btn">🛠️ Start Setup</a>
            <?php else: ?>
                <a href="/setup/setup.php?onboarding=1" class="btn">🛠️ Complete Setup</a>
            <?php endif; ?>

            <a href="/?skip_setup=1" class="btn btn-secondary">Skip for Now</a>

            <div class="steps">
                <h3>What you'll do:</h3>
                <ol>
                    <?php if (!$admin_exists): ?>
                        <li>Create your admin account</li>
                    <?php endif; ?>
                    <li>Customize site title and branding</li>
                    <li>Add your MailerLite API key to <code>.env</code></li>
                    <li>Import your first campaigns</li>
                </ol>
                <p style="margin-top: 16px; color: #9ca3af; font-size: 14px;">
                    Takes less than 5 minutes to complete!
                </p>
            </div>
        </div>
    </body>

    </html>
<?php
    exit;
}

// Check if welcome page should be shown
$show_welcome = false;
$skip_welcome = isset($_GET['skip_welcome']) && $_GET['skip_welcome'] === '1';

if (!$skip_welcome && is_welcome_enabled()) {
    // Check if user has seen welcome page (cookie check)
    $welcome_cookie = 'archive_welcome_seen';
    if (!isset($_COOKIE[$welcome_cookie])) {
        $show_welcome = true;
    }
}

// Check if viewing a specific campaign
$direct_campaign_id = isset($_GET['id']) ? trim($_GET['id']) : null;

// Detect device type
$detect = new \Detection\MobileDetect();
$is_mobile = $detect->isMobile();

// Load campaign data if viewing specific campaign (for meta tags & SEO)
$campaign = null;
$page_title = $site_title;

if ($direct_campaign_id) {
    $campaign = get_campaign($direct_campaign_id);
    if ($campaign) {
        $page_title = $campaign['subject'] . ' | ' . $site_title;
    }
}

// Body CSS classes
$body_classes = [$is_mobile ? 'mobile' : 'desktop'];

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(get_locale()) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>

    <!-- Styles -->
    <link rel="stylesheet" href="/css/styles.css?ver=<?= htmlspecialchars(get_composer_version()) ?>" />

    <meta name="description" content="<?= htmlspecialchars($site_description) ?>">
    <meta name="theme-color" content="#ffffff">

    <!-- Social Metadata & Favicons -->
    <?php require_once __DIR__ . '/inc/head.inc.php'; ?>
</head>

<body class="<?= implode(' ', $body_classes) ?>">

    <?php if ($show_welcome): ?>
        <!-- Welcome Page Overlay -->
        <?php require_once __DIR__ . '/inc/welcome.inc.php'; ?>
    <?php endif; ?>

    <!-- Mobile Campaign Navigation (hidden by default, shown when viewing campaign) -->
    <div id="mobile-campaign-nav" class="mobile-campaign-nav mobile-only hidden">
        <button id="back-to-list" class="nav-btn nav-back" aria-label="<?= htmlspecialchars(__('nav.back_to_list')) ?>">
            <span>←</span> <?php _e('nav.back_to_list'); ?>
        </button>
        <div class="nav-arrows">
            <button id="prev-campaign" class="nav-btn nav-arrow" aria-label="<?= htmlspecialchars(__('nav.previous')) ?>" disabled>
                ←
            </button>
            <button id="next-campaign" class="nav-btn nav-arrow" aria-label="<?= htmlspecialchars(__('nav.next')) ?>">
                →
            </button>
        </div>
    </div>

    <!-- Main Application - Email Inbox Layout -->
    <div id="app-container" class="inbox-layout">
        <!-- Left Sidebar: Email List -->
        <div id="email-list-panel" class="email-list-panel">
            <header id="filter-box">
                <div id="header-title">
                    <h1><?= htmlspecialchars($site_title) ?></h1>
                </div>
                <div id="filters">
                    <a href="/search.php" class="btn btn-search" aria-label="<?= htmlspecialchars(__('nav.search')) ?>">
                        🔍 <?php _e('nav.search'); ?>
                    </a>
                    <button class="btn btn-sort" id="sort-toggle"><?php _e('sort.newest_first'); ?></button>
                </div>
            </header>

            <div id="email-list-container">
                <ul id="email-list">
                    <li class="loading-state"><?php _e('list.loading'); ?></li>
                </ul>
            </div>
        </div>

        <!-- Right Content: Campaign View -->
        <div id="content-panel" class="content-panel">
            <div id="campaign-content">
                <!-- Placeholder when no campaign selected (desktop only) -->
                <div id="content-placeholder" class="content-placeholder desktop-only">
                    <p><?php _e('list.select_campaign'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Configuration & Scripts -->
    <script>
        // Pass configuration to JavaScript
        window.archiveCfg = {
            apiBase: '/api',
            isMobile: <?= $is_mobile ? 'true' : 'false' ?>,
            directCampaignId: <?= $direct_campaign_id ? '"' . htmlspecialchars($direct_campaign_id, ENT_QUOTES) . '"' : 'null' ?>,
            siteName: <?= json_encode($site_title) ?>,
            locale: <?= json_encode(get_locale()) ?>,
            i18n: <?= json_encode(get_translations()) ?>
        };
    </script>
    <script src="/js/main.js?ver=<?= htmlspecialchars(get_composer_version()) ?>"></script>

    <?php include_once __DIR__ . '/inc/footer.inc.php'; ?>

</body>

</html>