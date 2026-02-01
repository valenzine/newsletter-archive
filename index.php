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
    $page_config = [
        'title' => 'Setup Required - ' . $site_title,
        'body_class' => 'setup-prompt-page',
        'custom_css' => '/css/styles.css',
    ];
    
    // Output unified page head
    require __DIR__ . '/inc/page_head.inc.php';
?>

        <div class="setup-prompt">
            <div class="setup-icon">
                <img src="/img/box.png" alt="Newsletter Archive" width="100">
            </div>
            <h1><?php _e('setup_prompt.title'); ?></h1>
            <p><?php _e('setup_prompt.description'); ?></p>

            <?php if (!$admin_exists): ?>
                <a href="/setup/setup.php" class="btn">🛠️ <?php _e('setup_prompt.start_setup'); ?></a>
            <?php else: ?>
                <a href="/setup/setup.php?onboarding=1" class="btn">🛠️ <?php _e('setup_prompt.complete_setup'); ?></a>
            <?php endif; ?>

            <a href="/?skip_setup=1" class="btn btn-secondary"><?php _e('setup_prompt.skip_for_now'); ?></a>

            <div class="steps">
                <h3><?php _e('setup_prompt.what_youll_do'); ?></h3>
                <ol>
                    <?php if (!$admin_exists): ?>
                        <li><?php _e('setup_prompt.step_create_account'); ?></li>
                    <?php endif; ?>
                    <li><?php _e('setup_prompt.step_customize'); ?></li>
                    <li><?php _e('setup_prompt.step_add_api_key'); ?></li>
                    <li><?php _e('setup_prompt.step_import'); ?></li>
                </ol>
                <p style="margin-top: 16px; color: #9ca3af; font-size: 14px;">
                    <?php _e('setup_prompt.time_estimate'); ?>
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

// Configure page head
$page_config = [
    'title' => $page_title,
    'body_class' => implode(' ', $body_classes),
    'custom_css' => '/css/styles.css',
];

// Output unified page head
require __DIR__ . '/inc/page_head.inc.php';
?>

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
                    <button class="btn btn-sort" id="sort-toggle"><?php _e('sort.newest_first'); ?></button>
                    <a href="/search" class="btn btn-search" aria-label="<?= htmlspecialchars(__('nav.search')) ?>">
                        🔍 <?php _e('nav.search'); ?>
                    </a>
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