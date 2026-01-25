<?php
/**
 * Social Metadata & SEO Tags
 * 
 * Configure these values in your .env file:
 * - SITE_TITLE: Page title
 * - SITE_DESCRIPTION: Meta description
 * - OG_IMAGE: Social sharing image path or URL
 * - TWITTER_SITE: Twitter handle for site
 * - TWITTER_CREATOR: Twitter handle for creator
 */

// Ensure bootstrap is loaded
if (!isset($site_title)) {
    require_once __DIR__ . '/bootstrap.php';
}

// Build full image URL if relative path
$og_image_url = str_starts_with($og_image, 'http') 
    ? $og_image 
    : rtrim($site_base_url, '/') . '/' . ltrim($og_image, '/');
?>
    <meta property="og:title" content="<?= htmlspecialchars($site_title) ?>" />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="<?= htmlspecialchars($site_base_url) ?>" />
    <meta property="og:image" content="<?= htmlspecialchars($og_image_url) ?>?ver=<?= htmlspecialchars(get_composer_version()) ?>" />
    <meta property="og:description" content="<?= htmlspecialchars($site_description) ?>" />
    <meta name="twitter:card" content="summary_large_image" />
<?php if (!empty($twitter_site)): ?>
    <meta name="twitter:site" content="@<?= htmlspecialchars($twitter_site) ?>" />
<?php endif; ?>
<?php if (!empty($twitter_creator)): ?>
    <meta name="twitter:creator" content="@<?= htmlspecialchars($twitter_creator) ?>" />
<?php endif; ?>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lora:wght@400;500;600;700&family=Open+Sans:wght@300;400;500;600;700&display=swap">
    <!-- Favicons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/img/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/img/apple-touch-icon.png">
<?php 
// Load Google Analytics if configured
require_once __DIR__ . '/analytics.inc.php'; 
?>