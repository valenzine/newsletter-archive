<?php
/**
 * Unified Page Head Template
 * 
 * Single source of truth for all <head> sections across the application.
 * Replaces duplicate head code and ensures consistency.
 * 
 * Usage:
 *   $page_config = [
 *       'title' => 'Page Title',                    // Required: Full page title
 *       'description' => 'Page description',        // Optional: defaults to site description
 *       'noindex' => false,                         // Optional: set true for admin pages
 *       'body_class' => 'custom-class',             // Optional: CSS class for <body>
 *       'custom_css' => '/css/custom.css',          // Optional: additional CSS file
 *       'inline_styles' => '<style>...</style>',    // Optional: inline styles
 *       'head_scripts' => '<script>...</script>',   // Optional: scripts in <head>
 *   ];
 *   require __DIR__ . '/inc/page_head.inc.php';
 * 
 * IMPORTANT: This file outputs BOTH <html> opening tag AND complete <head> section.
 * After including this file, immediately open <body> tag with the provided $body_class.
 */

// Ensure bootstrap is loaded
if (!isset($site_title)) {
    require_once __DIR__ . '/bootstrap.php';
}

// Validate and extract configuration
if (!isset($page_config) || !is_array($page_config)) {
    $page_config = [];
}

$page_title = $page_config['title'] ?? $site_title;
$page_description = $page_config['description'] ?? $site_description;
$noindex = $page_config['noindex'] ?? false;
$body_class = $page_config['body_class'] ?? '';
$custom_css = $page_config['custom_css'] ?? null;
$inline_styles = $page_config['inline_styles'] ?? null;
$head_scripts = $page_config['head_scripts'] ?? null;

// Build full OG image URL
$og_image_url = str_starts_with($og_image, 'http') 
    ? $og_image 
    : rtrim($site_base_url, '/') . '/' . ltrim($og_image, '/');

$locale = get_locale();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($locale) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php if ($noindex): ?>
    <meta name="robots" content="noindex, nofollow">
<?php endif; ?>
    <title><?= htmlspecialchars($page_title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($page_description) ?>">
    <meta name="theme-color" content="#ffffff">
    
    <!-- Open Graph / Social Media -->
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

<?php if ($custom_css): ?>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= htmlspecialchars($custom_css) ?>?ver=<?= htmlspecialchars(get_composer_version()) ?>">
<?php endif; ?>

<?php if ($inline_styles): ?>
    <!-- Inline Styles -->
    <?= $inline_styles ?>

<?php endif; ?>

<?php if ($head_scripts): ?>
    <!-- Head Scripts -->
    <?= $head_scripts ?>

<?php endif; ?>

<?php 
// Load Google Analytics (automatically skips on /setup/ pages)
require_once __DIR__ . '/analytics.inc.php'; 
?>
</head>
<body<?= $body_class ? ' class="' . htmlspecialchars($body_class) . '"' : '' ?>>
