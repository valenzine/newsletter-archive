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

// Route: /{campaign_id} (32 character hex hash)
// Only match if it looks like a campaign ID (32 hex chars)
if (preg_match('#^([a-f0-9]{32})$#i', $path, $matches)) {
    $_GET['id'] = $matches[1];
    require __DIR__ . '/index.php';
    exit;
}

// Not found - return 404
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found | Newsletter Archive</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            max-width: 600px;
            margin: 100px auto;
            padding: 20px;
            text-align: center;
        }
        h1 {
            font-size: 4em;
            margin: 0;
            color: #333;
        }
        p {
            font-size: 1.2em;
            color: #666;
        }
        a {
            color: #0066cc;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <h1>404</h1>
    <p>Page not found</p>
    <p><a href="/">Back to home</a></p>
</body>
</html>
