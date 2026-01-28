<?php
/**
 * MailerLite Campaign Sync Interface
 * 
 * Simple, standalone sync interface for newsletter-archive.
 * Uses real-time Server-Sent Events (SSE) for progress updates.
 */

require_once __DIR__.'/../inc/bootstrap.php';
require_once __DIR__.'/../inc/session.inc.php';
require_once __DIR__.'/../inc/functions.php';
require_once __DIR__.'/../inc/database.inc.php';
require_once __DIR__.'/../inc/admin_auth.php';

// Check for cron mode (automated sync via token)
$cron_mode = !empty($cron_token) && isset($_GET['cron_token']) && $_GET['cron_token'] === $cron_token;

if (!is_admin_authenticated() && !$cron_mode) {
    http_response_code(403);
    echo "<h1>403 Forbidden</h1><p>You are not authorized to access this page.</p>";
    exit;
}

// Cron mode: JSON API response (no HTML interface)
if ($cron_mode) {
    header('Content-Type: application/json');
    
    try {
        // Use optimized cron function (only checks for new campaigns)
        $stats = sync_new_campaigns_only($mailerlite_api_key);
        echo json_encode([
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'stats' => $stats
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    
    exit;
}

// Get admin user info
$admin_user = $_SESSION['user'] ?? null;
$admin_name = $admin_user['name'] ?? $admin_user['username'] ?? $admin_user['email'] ?? 'Admin';

// Handle AJAX sync request
if (isset($_GET['action']) && $_GET['action'] === 'sync' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Configure for real-time streaming
    @ini_set('output_buffering', '0');
    @ini_set('zlib.output_compression', '0');
    @ini_set('implicit_flush', '1');
    
    // Clean existing buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Start output buffering for flush control
    ob_start();
    
    // Disable FastCGI buffering
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }
    
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    
    // Send initial padding to prevent buffering
    echo ":" . str_repeat(' ', 1024) . "\n\n";
    ob_flush();
    flush();
    
    // TEST: Send a hardcoded message first
    send_sse_event('TEST: SSE stream is working!', 'info');
    sleep(1); // Give it time to show
    
    send_sse_event('Starting MailerLite sync...', 'info');
    
    // Get force sync limit from POST data
    $force_limit = null;
    if (isset($_POST['force_limit']) && is_numeric($_POST['force_limit'])) {
        $force_limit = (int)$_POST['force_limit'];
        send_sse_event("Force sync mode: Will sync up to $force_limit campaigns", 'warning');
    } else {
        send_sse_event('Normal sync mode: Will sync ALL campaigns', 'info');
    }
    
    // Get API key
    $api_key = $mailerlite_api_key ?? '';
    if (empty($api_key)) {
        send_sse_event('ERROR: MailerLite API key not configured', 'error');
        send_sse_event('complete', 'complete');
        ob_end_flush();
        exit;
    }
    
    // Create progress callback
    $progress_callback = function($data) {
        $message = $data['message'] ?? '';
        $type = $data['type'] ?? 'info';
        if (!empty($message)) {
            send_sse_event($message, $type === 'progress' ? 'info' : $type);
        }
    };
    
    // Run sync
    try {
        $result = sync_mailerlite_campaigns($api_key, $force_limit, $progress_callback);
        
        $imported = $result['imported'] ?? 0;
        $skipped = $result['skipped'] ?? 0;
        $errors = $result['errors'] ?? [];
        
        if (!empty($errors)) {
            foreach ($errors as $error) {
                send_sse_event("Error: $error", 'error');
            }
        }
        
        if ($imported > 0 || $skipped > 0) {
            send_sse_event("✓ Sync complete: {$imported} imported, {$skipped} skipped", 'success');
        } else {
            send_sse_event("✓ Sync complete: No campaigns found", 'info');
        }
        
        // Send completion event with stats
        echo "data: " . json_encode([
            'type' => 'complete',
            'stats' => [
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => count($errors),
            ]
        ]) . "\n\n";
        
    } catch (Exception $e) {
        send_sse_event("Fatal error: " . $e->getMessage(), 'error');
        echo "data: " . json_encode([
            'type' => 'complete',
            'stats' => [
                'imported' => 0,
                'skipped' => 0,
                'errors' => 1,
            ]
        ]) . "\n\n";
    }
    
    ob_end_flush();
    exit;
}

// Main page HTML
$last_sync = get_setting('last_sync', 'Never');

// Configure page head
$page_config = [
    'title' => 'MailerLite Sync | ' . $site_title,
    'body_class' => 'admin-page',
    'custom_css' => '/css/admin.css',
];

// Output unified page head
require_once __DIR__ . '/../inc/page_head.inc.php';
?>
    <div class="admin-header">
        <div class="admin-header-content">
            <h1>MailerLite Sync</h1>
            <div class="admin-user-info">
                <span>Logged in as: <strong><?= htmlspecialchars($admin_name) ?></strong></span>
            </div>
        </div>
    </div>

    <div class="admin-container">
        <div class="admin-content">
            <div class="result-box">
                <h2>Sync Campaigns from MailerLite</h2>
                <p>This will fetch campaigns from your MailerLite account and save them to the archive.</p>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="label">Last Sync</div>
                        <div class="value"><?= htmlspecialchars($last_sync) ?></div>
                    </div>
                </div>

                <div class="sync-controls">
                    <div style="margin-bottom: 1rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" id="force-sync-checkbox" />
                            <span>Force sync (limit to specific number of campaigns)</span>
                        </label>
                    </div>
                    
                    <div id="force-limit-container" style="display: none; margin-bottom: 1rem;">
                        <label for="force-limit">Number of campaigns to sync:</label>
                        <input type="number" id="force-limit" min="1" max="500" value="50" style="margin-left: 0.5rem; padding: 8px; width: 100px;" />
                        <small style="display: block; margin-top: 0.5rem; color: #666;">
                            Useful for testing or re-syncing recent campaigns
                        </small>
                    </div>
                    
                    <button id="sync-btn" class="btn-sync">Start Sync</button>
                </div>

                <div id="progress-log" class="progress-log" style="display: none;"></div>
            </div>

            <div style="margin-top: 2rem; text-align: center;">
                <a href="setup.php" class="btn">← Back to Admin</a>
            </div>
        </div>
    </div>

    <script>
        const syncBtn = document.getElementById('sync-btn');
        const progressLog = document.getElementById('progress-log');
        const forceSyncCheckbox = document.getElementById('force-sync-checkbox');
        const forceLimitContainer = document.getElementById('force-limit-container');
        const forceLimitInput = document.getElementById('force-limit');

        // Toggle force limit input
        forceSyncCheckbox.addEventListener('change', () => {
            forceLimitContainer.style.display = forceSyncCheckbox.checked ? 'block' : 'none';
        });

        syncBtn.addEventListener('click', startSync);

        function startSync() {
            syncBtn.disabled = true;
            syncBtn.textContent = 'Syncing...';
            progressLog.style.display = 'block';
            progressLog.innerHTML = '';

            // Build form data for force sync
            const formData = new FormData();
            if (forceSyncCheckbox.checked) {
                formData.append('force_limit', forceLimitInput.value);
            }

            // Use fetch with streaming for Server-Sent Events
            fetch('?action=sync', {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'text/event-stream',
                },
            }).then(response => {
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                function processText({ done, value }) {
                    if (done) {
                        syncBtn.disabled = false;
                        syncBtn.textContent = 'Start Sync';
                        addLog('Stream ended', 'info');
                        return;
                    }

                    buffer += decoder.decode(value, { stream: true });
     // DEBUG
                    
                    const lines = buffer.split('\n\n');
                    buffer = lines.pop(); // Keep incomplete message in buffer

                    lines.forEach(block => {
                        // Each block can contain multiple lines (event:, data:, etc.)
                        const eventLines = block.split('\n');
                        let eventData = null;
                        
                        eventLines.forEach(line => {
                            if (line.startsWith('data: ')) {
                                const data = line.substring(6);
                                try {
                                    eventData = JSON.parse(data);
                                } catch (e) {
                                    console.error('Failed to parse SSE data:', data, e);
                                    addLog('Parse error: ' + e.message, 'error');
                                }
                            }
                        });
                        
                        // Process the parsed event
                        if (eventData) {
                            if (eventData.type === 'complete') {
                                syncBtn.disabled = false;
                                syncBtn.textContent = 'Start Sync';
                                addLog('✅ Sync complete!', 'success');
                            } else if (eventData.message) {
                                addLog(eventData.message, eventData.type || 'info');
                            }
                        }
                    });

                    return reader.read().then(processText);
                }

                return reader.read().then(processText);
            }).catch(error => {
                syncBtn.disabled = false;
                syncBtn.textContent = 'Start Sync';
                addLog('❌ Error: ' + error.message, 'error');
            });
        }

        function addLog(message, type = 'info') {
            const entry = document.createElement('div');
            entry.className = 'log-entry ' + type;
            entry.textContent = message;
            progressLog.appendChild(entry);
            progressLog.scrollTop = progressLog.scrollHeight;
        }
    </script>
</body>
</html>
