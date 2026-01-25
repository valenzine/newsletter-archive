<?php
/**
 * View Campaign
 * 
 * Displays a single campaign's content. Used by AJAX requests from the SPA.
 * Direct access redirects to the main index with campaign ID.
 */

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/database.inc.php';

// Get campaign ID
if (!isset($_GET['id'])) {
    header('HTTP/1.0 400 Bad Request');
    echo '<h1>Campaign ID required</h1>';
    exit;
}

$campaign_id = trim($_GET['id']);

// Find campaign
$campaign = get_campaign($campaign_id);

// Check if campaign exists and is not hidden
if (!$campaign || $campaign['hidden']) {
    header('HTTP/1.0 404 Not Found');
    echo '<h1>Campaign not found</h1>';
    exit;
}

// Check if this is an AJAX request (for single-page app)
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

if ($is_ajax) {
    // AJAX mode: return campaign content for embedding
    $content_path = $campaign['content_path'];
    
    // Content path is already relative to document root (e.g., "source/mailerlite_campaigns/123.html")
    $full_path = __DIR__ . '/' . $content_path;
    
    if (!file_exists($full_path)) {
        echo '<div class="campaign-error"><p>Campaign content not found.</p><p>Path: ' . htmlspecialchars($content_path) . '</p></div>';
        exit;
    }
    
    // Output campaign header (metadata)
    echo '<div class="campaign-header">';
    echo '<h1 class="campaign-subject">' . htmlspecialchars($campaign['subject']) . '</h1>';
    if (!empty($campaign['preview_text'])) {
        echo '<p class="campaign-preview">' . htmlspecialchars($campaign['preview_text']) . '</p>';
    }
    echo '<div class="campaign-meta">';
    // Output ISO date in data attribute, will be formatted by JavaScript using locale
    echo '<time datetime="' . htmlspecialchars($campaign['sent_at']) . '" class="campaign-date" data-iso="' . htmlspecialchars($campaign['sent_at']) . '">' . 
         htmlspecialchars(date('F j, Y', strtotime($campaign['sent_at']))) . '</time>';
    echo '</div>';
    echo '</div>';
    
    // Read campaign HTML and extract only the body content
    $content = file_get_contents($full_path);
    
    // Extract just the body content (strip head with styles that would affect our page)
    if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $content, $matches)) {
        $body_content = $matches[1];
    } else {
        // Fallback: use entire content if no body tag found
        $body_content = $content;
    }
    
    // Output the campaign body wrapped in a container
    echo '<div class="campaign-body">';
    echo $body_content;
    echo '</div>';
    exit;
}

// Direct access: Redirect to SPA format
// Preserve 'from' and 'q' parameters if present (for search context)
$redirectUrl = '/?id=' . urlencode($campaign_id);
if (isset($_GET['from']) && $_GET['from'] === 'search') {
    $redirectUrl .= '&from=search';
    if (isset($_GET['q']) && !empty($_GET['q'])) {
        $redirectUrl .= '&q=' . urlencode($_GET['q']);
    }
}
header('Location: ' . $redirectUrl, true, 301);
exit;
