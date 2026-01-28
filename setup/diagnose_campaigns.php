<?php
/**
 * Campaign Diagnostics Tool
 * 
 * Identifies campaigns with missing files and provides recovery options.
 */

require_once __DIR__.'/../inc/bootstrap.php';
require_once __DIR__.'/../inc/functions.php';
require_once __DIR__.'/../inc/database.inc.php';
require_once __DIR__.'/../inc/admin_auth.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 3600,
        'path' => '/setup/',
        'domain' => '',
        'secure' => ($_ENV['APP_ENV'] ?? 'production') === 'production',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Require authentication
require_admin(false);

use MailerLite\MailerLite;

$admin = get_admin();
$admin_name = $admin['username'];

// API rate limiting constant (seconds between MailerLite API requests)
const API_RATE_LIMIT_DELAY = 2;

/**
 * Initialize SSE stream with proper headers
 * Clears all output buffers and sets Server-Sent Events headers for real-time streaming
 */
function init_sse_stream() {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    header('Connection: keep-alive');
}

// Handle recovery action
if (isset($_POST['recover_campaigns'])) {
    // CSRF protection
    if (empty($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        init_sse_stream();
        send_sse_event('CSRF token missing or invalid', 'error');
        exit;
    }
    
    global $mailerlite_api_key, $mailerlite_campaigns_content_directory;
    $campaign_ids = json_decode($_POST['campaign_ids'], true);
    
    // Validate that campaign_ids is an array
    if (!is_array($campaign_ids)) {
        init_sse_stream();
        send_sse_event('Invalid campaign IDs', 'error');
        exit;
    }
    
    // Validate each campaign ID and limit the number of campaigns per request
    $MAX_CAMPAIGNS_PER_REQUEST = 20;
    if (count($campaign_ids) > $MAX_CAMPAIGNS_PER_REQUEST) {
        init_sse_stream();
        send_sse_event('Too many campaigns selected for recovery. Please select 20 or fewer at a time.', 'error');
        exit;
    }
    foreach ($campaign_ids as $id) {
        // Accept only string or numeric IDs, and ensure they are alphanumeric (plus dashes/underscores)
        if ((!is_string($id) && !is_numeric($id)) || !preg_match('/^[a-zA-Z0-9_-]+$/', (string)$id)) {
            init_sse_stream();
            send_sse_event('Invalid campaign ID format: ' . htmlspecialchars((string)$id), 'error');
            exit;
        }
    }
    
    if (!empty($campaign_ids)) {
        init_sse_stream();
        
        send_sse_event("Starting recovery for " . count($campaign_ids) . " campaigns...", 'info');
        
        $mailerlite = new MailerLite(['api_key' => $mailerlite_api_key]);
        $campaignsApi = $mailerlite->campaigns;
        $contentDir = trailingslashit($mailerlite_campaigns_content_directory);
        $jsonDir = trailingslashit($contentDir . 'json/');
        
        $recovered = 0;
        $failed = 0;
        
        foreach ($campaign_ids as $index => $id) {
            $num = $index + 1;
            $total = count($campaign_ids);
            
            send_sse_event("[{$num}/{$total}] Recovering campaign ID: {$id}...", 'info');
            
            // Sanitize the ID to prevent path traversal
            $safe_id = basename($id);
            if ($safe_id !== $id) {
                send_sse_event("  ✗ Invalid campaign ID format", 'error');
                $failed++;
                continue;
            }
            
            try {
                // Fetch campaign details
                $details = $campaignsApi->find($id);
                $campaign = $details['body']['data'] ?? null;
                
                if (!$campaign) {
                    send_sse_event("  ✗ Failed: Campaign not found in MailerLite", 'error');
                    $failed++;
                    continue;
                }
                
                $name = $campaign['name'] ?? 'Unknown';
                send_sse_event("  Found: {$name}", 'info');
                
                // Get HTML content
                $html = $details['body']['data']['html'] ?? 
                        $details['body']['data']['emails'][0]['html'] ?? 
                        $details['body']['data']['emails'][0]['content'] ?? null;
                
                // Fallback to preview_url
                if (!$html && isset($details['body']['data']['preview_url'])) {
                    $preview_url = $details['body']['data']['preview_url'];
                    send_sse_event("  Fetching from preview URL...", 'info');
                    list($html, $curl_err, $http_code, $headers) = fetch_url_with_user_agent($preview_url);
                    
                    if ($curl_err || empty($html)) {
                        send_sse_event("  ✗ Failed to fetch HTML: " . ($curl_err ?: 'Empty response'), 'error');
                        $failed++;
                        continue;
                    }
                }
                
                if ($html) {
                    // Save HTML file
                    $htmlFile = $contentDir . $id . '.html';
                    file_put_contents($htmlFile, $html);
                    
                    // Save JSON metadata
                    $meta = $campaign;
                    $meta['archived_at'] = date('c');
                    $meta['recovered_at'] = date('c');
                    $jsonFile = $jsonDir . $id . '.json';
                    file_put_contents($jsonFile, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    
                    send_sse_event("  ✓ Recovered successfully", 'success');
                    $recovered++;
                } else {
                    send_sse_event("  ✗ Failed: No HTML content available", 'error');
                    $failed++;
                }
                
                sleep(API_RATE_LIMIT_DELAY);
                
            } catch (Exception $e) {
                send_sse_event("  ✗ Error: " . $e->getMessage(), 'error');
                $failed++;
            }
        }
        
        send_sse_event("Recovery complete! Recovered: {$recovered}, Failed: {$failed}", 'success');
        echo "data: " . json_encode(['type' => 'complete', 'recovered' => $recovered, 'failed' => $failed]) . "\n\n";
        flush();
        exit;
    }
}

// Handle database repair for campaigns with missing names  
if (isset($_POST['repair_names'])) {
    // CSRF protection
    if (empty($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        init_sse_stream();
        send_sse_event('CSRF token missing or invalid', 'error');
        exit;
    }
    
    global $campaign_database;
    
    init_sse_stream();
    
    $repaired = 0;
    $db = $campaign_database;
    $all_campaigns = $db->findAll();
    $total = count($all_campaigns);
    $missing = 0;
    
    send_sse_event("Scanning {$total} campaigns...", 'info');
    
    // Single pass: count and repair campaigns with missing names
    foreach ($all_campaigns as $campaign) {
        if (empty($campaign['name']) && !empty($campaign['title'])) {
            $missing++;
            $repaired++;

            // Create update data WITHOUT _id field
            $updateData = $campaign;
            unset($updateData['_id']); // Remove _id to avoid SleekDB error
            $updateData['name'] = $campaign['title'];

            $db->updateById($campaign['_id'], $updateData);

            // Progress update every 10 campaigns or at the end
            if ($repaired % 10 === 0) {
                send_sse_event("[{$repaired}] Repaired: {$campaign['title']}", 'success');
            }
        }
    }

    send_sse_event("Found {$missing} campaigns with missing names", 'info');

    if ($missing === 0) {
        send_sse_event("No repairs needed!", 'success');
        echo "data: " . json_encode(['type' => 'complete', 'repaired' => 0]) . "\n\n";
        if (ob_get_level() > 0) ob_end_flush();
        exit;
    }

    send_sse_event("Repair complete! Fixed {$repaired} campaigns.", 'success');
    echo "data: " . json_encode(['type' => 'complete', 'repaired' => $repaired]) . "\n\n";
    flush();
    exit;
}

// Handle fixing filenames for campaigns with missing files
if (isset($_POST['fix_filenames'])) {
    // CSRF protection
    if (empty($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        init_sse_stream();
        send_sse_event('CSRF token missing or invalid', 'error');
        exit;
    }
    
    global $campaign_database, $campaigns_directory;
    $campaign_ids = json_decode($_POST['campaign_ids'], true);
    
    // Validate that campaign_ids is an array
    if (!is_array($campaign_ids)) {
        init_sse_stream();
        send_sse_event('Invalid campaign IDs', 'error');
        exit;
    }
    
    init_sse_stream();
    
    send_sse_event("Starting filename repair for " . count($campaign_ids) . " campaigns...", 'info');
    
    $fixed = 0;
    $failed = 0;
    
    foreach ($campaign_ids as $index => $id) {
        $num = $index + 1;
        $total = count($campaign_ids);
        
        $campaign = $campaign_database->findById($id);
        if (!$campaign) {
            send_sse_event("[{$num}/{$total}] Campaign ID {$id} not found in database", 'error');
            $failed++;
            continue;
        }
        
        $title = $campaign['title'] ?? $campaign['name'] ?? 'Unknown';
        send_sse_event("[{$num}/{$total}] Fixing: {$title}", 'info');
        
        // Generate filename from ID (Mailchimp format)
        $campaign_id = $campaign['id'] ?? '';
        if (empty($campaign_id)) {
            send_sse_event("  ✗ No campaign ID found", 'error');
            $failed++;
            continue;
        }
        
        $filename = get_filename($campaign_id);
        
        // Try to find the file
        $found_file = match_campaign_to_file($filename, $campaigns_directory);
        
        if (is_array($found_file) && $found_file[0] === 404) {
            send_sse_event("  ✗ File not found for: {$filename}", 'error');
            $failed++;
        } else {
            // Update database with correct filename
            $updateData = $campaign;
            unset($updateData['_id']);
            $updateData['filename'] = $found_file;
            
            $campaign_database->updateById($id, $updateData);
            send_sse_event("  ✓ Fixed: {$found_file}", 'success');
            $fixed++;
        }
    }
    
    send_sse_event("Filename repair complete! Fixed: {$fixed}, Failed: {$failed}", 'success');
    echo "data: " . json_encode(array('type' => 'complete', 'fixed' => $fixed, 'failed' => $failed)) . "\n\n";
    flush();
    exit;
}

// Run diagnostics
global $campaign_database, $mailerlite_campaigns_content_directory, $campaigns_directory, $mailerlite_api_key;
$db = $campaign_database;
$all_campaigns = $db->findAll();

$missing_files = [];
$missing_db_records = [];
$mailchimp_count = 0;
$mailerlite_count = 0;
$campaigns_with_missing_names = 0;

// Check for campaigns without filename or with missing names
foreach ($all_campaigns as $campaign) {
    $source = $campaign['source'] ?? 'Mailchimp';
    
    if ($source === 'MailerLite') {
        $mailerlite_count++;
    } else {
        $mailchimp_count++;
    }
    
    // Count campaigns with missing names but have titles
    if (empty($campaign['name']) && !empty($campaign['title'])) {
        $campaigns_with_missing_names++;
    }
    
    if (empty($campaign['filename'])) {
        $missing_files[] = [
            'id' => $campaign['_id'] ?? 'unknown',
            'mailerlite_id' => $campaign['mailerlite_id'] ?? $campaign['id'] ?? null,
            'name' => $campaign['name'] ?? 'Unknown',
            'source' => $source,
            'sent_at' => $campaign['sent_at'] ?? $campaign['send_time'] ?? 'Unknown',
            'issue' => 'No filename field'
        ];
    } else {
        // Check if file actually exists
        $contentDir = ($source === 'MailerLite') ? $mailerlite_campaigns_content_directory : $campaigns_directory;
        $filePath = trailingslashit($contentDir) . $campaign['filename'];
        
        if (!file_exists($filePath)) {
            $missing_files[] = [
                'id' => $campaign['_id'] ?? 'unknown',
                'mailerlite_id' => $campaign['mailerlite_id'] ?? $campaign['id'] ?? null,
                'name' => $campaign['name'] ?? 'Unknown',
                'source' => $source,
                'sent_at' => $campaign['sent_at'] ?? $campaign['send_time'] ?? 'Unknown',
                'filename' => $campaign['filename'],
                'issue' => 'File not found'
            ];
        }
    }
}

// Filter recoverable campaigns (MailerLite only)
$recoverable = array_filter($missing_files, function($c) { 
    return $c['source'] === 'MailerLite' && !empty($c['mailerlite_id']); 
});


// Configure page head
$page_config = [
    'title' => 'Campaign Diagnostics | ' . $site_title,
    'body_class' => 'admin-page',
    'custom_css' => '/css/admin.css',
];

// Output unified page head
require_once __DIR__ . '/../inc/page_head.inc.php';
?>
    <div class="admin-header">
        <div class="admin-header-content">
            <h1>🔧 Campaign Diagnostics</h1>
            <div class="admin-user-info">
                <span>v<?= htmlspecialchars(get_composer_version()) ?></span>
                <span>👤 <?= htmlspecialchars($admin_name) ?></span>
                <a href="/setup/setup.php">← Admin</a>
                <a href="/">View Site</a>
                <a href="/logout.php">Logout</a>
            </div>
        </div>
    </div>

    <div class="admin-container">
        <div class="admin-content">
            
            <div class="diagnostic-card">
                <h2>Database Overview</h2>
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-value"><?= count($all_campaigns) ?></div>
                        <div class="stat-label">Total Campaigns</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value stat-good"><?= $mailchimp_count ?></div>
                        <div class="stat-label">Mailchimp</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value stat-good"><?= $mailerlite_count ?></div>
                        <div class="stat-label">MailerLite</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value <?= count($missing_files) > 0 ? 'stat-error' : 'stat-good' ?>">
                            <?= count($missing_files) ?>
                        </div>
                        <div class="stat-label">Missing Files</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value <?= $campaigns_with_missing_names > 0 ? 'stat-warn' : 'stat-good' ?>">
                            <?= $campaigns_with_missing_names ?>
                        </div>
                        <div class="stat-label">Missing Names</div>
                    </div>
                </div>
            </div>

            <?php if ($campaigns_with_missing_names > 0): ?>
            <div class="diagnostic-card">
                <h2>Campaigns with Missing Names (<?= $campaigns_with_missing_names ?>)</h2>
                <p>These campaigns have a <code>title</code> field but are missing the <code>name</code> field, which can cause indexing issues.</p>
                
                <div class="alert alert-info">
                    <strong>Quick Fix Available:</strong> Copy the title to the name field for all affected campaigns.
                    <button id="repair-names-btn" class="btn btn-primary btn-spacing">
                        Repair Missing Names
                    </button>
                </div>
                
                <div id="repair-progress-console" class="progress-console">
                    <div class="console-header">Repair Progress</div>
                    <div class="console-body" id="repair-console-output"></div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (count($missing_files) > 0): ?>
            <div class="diagnostic-card">
                <h2>Campaigns with Missing Files (<?= count($missing_files) ?>)</h2>
                <p>These campaigns are in the database but their HTML files cannot be found at the expected path.</p>
                
                <div class="alert alert-warning">
                    <strong>Fix Available:</strong> Re-match these campaigns to their actual HTML files on disk.
                    <button id="fix-filenames-btn" class="btn btn-primary btn-spacing">
                        Fix Filename Paths
                    </button>
                </div>
                
                <div id="fix-progress-console" class="progress-console">
                    <div class="console-header">Filename Repair Progress</div>
                    <div class="console-body" id="fix-console-output"></div>
                </div>
                
                <?php if (count($recoverable) > 0): ?>
                <div class="alert alert-info">
                    <strong><?= count($recoverable) ?> campaigns can be recovered</strong> from MailerLite API.
                    <button id="recover-btn" class="btn btn-primary btn-spacing">
                        Recover Missing MailerLite Campaigns
                    </button>
                </div>
                
                <div id="progress-console" class="progress-console"></div>
                <?php endif; ?>
                
                <div class="campaign-list">
                    <?php foreach ($missing_files as $campaign): ?>
                    <div class="campaign-item">
                        <strong><?= htmlspecialchars($campaign['name']) ?></strong>
                        <span class="issue-badge"><?= htmlspecialchars($campaign['issue']) ?></span>
                        <div class="meta">
                            Source: <?= htmlspecialchars($campaign['source']) ?> | 
                            Sent: <?= htmlspecialchars($campaign['sent_at']) ?>
                            <?php if (!empty($campaign['mailerlite_id'])): ?>
                            | MailerLite ID: <?= htmlspecialchars($campaign['mailerlite_id']) ?>
                            <?php endif; ?>
                            <?php if (!empty($campaign['filename'])): ?>
                            | Expected file: <?= htmlspecialchars($campaign['filename']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="result-box success">
                <h3>✓ All campaigns have valid files</h3>
                <p>No issues found. All campaigns in the database have corresponding HTML files.</p>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <div id="app-config" 
         data-csrf-token="<?= htmlspecialchars(generate_csrf_token()) ?>"
         data-recoverable-campaigns="<?= htmlspecialchars(json_encode(array_column($recoverable, 'mailerlite_id'))) ?>"
         data-missing-campaign-ids="<?= htmlspecialchars(json_encode(array_column($missing_files, 'id'))) ?>">
    </div>
    <script src="/js/diagnose-campaigns.js?ver=<?= htmlspecialchars(get_composer_version()) ?>" defer></script>
</body>
</html>
