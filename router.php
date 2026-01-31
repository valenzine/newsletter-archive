<?php

/**
 * URL Router
 * 
 * Handles clean URL routing with backward compatibility.
 * Maps clean URLs to their PHP file equivalents:
 * 
 * Routes:
 * - /{campaign_id}        → index.php?id={campaign_id}
 * - /search               → search.php
 * - /search/{query}       → search.php?q={query}
 * 
 * Backward compatibility:
 * - /?id={campaign_id}    → Still works
 * - /search.php           → Still works
 * - /search.php?q={query} → Still works
 */

// For PHP's built-in server: serve static files directly
// Return false to let PHP handle the file (CSS, JS, images, etc.)
if (php_sapi_name() === 'cli-server') {
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $file = __DIR__ . $requestPath;
    
    // Resolve the real path and ensure it's within document root (prevent path traversal)
    $realFile = realpath($file);
    $docRoot = realpath(__DIR__);
    
    if ($realFile && strpos($realFile, $docRoot) === 0 && is_file($realFile)) {
        return false;
    }
}

// Get the request URI and remove query string
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request_uri = rtrim($request_uri, '/');

// Remove leading slash for easier matching
$path = ltrim($request_uri, '/');

// Route: Root path / → index.php (home page)
if ($path === '') {
    require __DIR__ . '/index.php';
    exit;
}

// Route: /tag/{tagname}
if (preg_match('#^tag/([a-z0-9_-]+)$#i', $path, $matches)) {
    $_GET['tag'] = $matches[1];
    require __DIR__ . '/index.php';
    exit;
}

// Route: /search
if ($path === 'search') {
    require __DIR__ . '/search.php';
    exit;
}

// Route: /search/{query}
if (preg_match('#^search/(.+)$#i', $path, $matches)) {
    $searchQuery = urldecode($matches[1]);
    
    // Basic input validation for search query
    // Reject null bytes, control characters, and extremely long queries
    if (
        strlen($searchQuery) > 500 ||                    // Max 500 chars
        preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $searchQuery)  // Control chars (except \t, \n, \r)
    ) {
        http_response_code(400);
        echo 'Invalid search query';
        exit;
    }
    
    $_GET['q'] = $searchQuery;
    require __DIR__ . '/search.php';
    exit;
}

// Route: /{campaign_id} (16 character hex hash)
// Only match if it looks like a campaign ID (16 hex chars)
if (preg_match('#^([a-f0-9]{16})$#i', $path, $matches)) {
    $_GET['id'] = $matches[1];
    require __DIR__ . '/index.php';
    exit;
}

// Not found - return 404
http_response_code(404);

// Load bootstrap for i18n and site config
require_once __DIR__ . '/inc/bootstrap.php';

// Configure page head
$page_config = [
    'title' => __('404.title') . ' | ' . $site_title,
    'body_class' => 'error-page',
    'custom_css' => '/css/styles.css',
];

// Output unified page head
require __DIR__ . '/inc/page_head.inc.php';
?>
    <h1><?php _e('404.heading'); ?></h1>
    <p><?php _e('404.message'); ?></p>
    <p><a href="/"><?php _e('404.back_home'); ?></a></p>
</body>
</html>
