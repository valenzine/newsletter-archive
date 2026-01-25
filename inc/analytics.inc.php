<?php
/**
 * Google Analytics 4 Integration
 * 
 * Loads GA4 tracking code if a Measurement ID is configured.
 * Configure in admin settings or via GOOGLE_ANALYTICS_ID in .env
 * 
 * Note: Only loads on public pages, NOT on admin/setup pages
 */

// Skip analytics on admin/setup pages to prevent polluting analytics data
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
if (str_starts_with($request_uri, '/setup/') || str_starts_with($request_uri, '/setup')) {
    return;
}

// Ensure bootstrap is loaded for settings access
if (!function_exists('get_setting')) {
    require_once __DIR__ . '/bootstrap.php';
}

// Get Google Analytics ID from settings (with .env fallback)
$google_analytics_id = trim((string) get_setting('google_analytics_id'));
if ($google_analytics_id === '' && isset($_ENV['GOOGLE_ANALYTICS_ID'])) {
    $env_google_analytics_id = trim((string) $_ENV['GOOGLE_ANALYTICS_ID']);
    if ($env_google_analytics_id !== '') {
        $google_analytics_id = $env_google_analytics_id;
    }
}

// Only load GA4 if an ID is configured
if (!empty($google_analytics_id)):
?>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($google_analytics_id) ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', '<?= htmlspecialchars($google_analytics_id) ?>');
</script>
<?php endif; ?>