<?php

/**
 * Newsletter Archive - Utility Functions
 * 
 * Minimal set of helper functions for the archive application.
 */

use MailerLite\MailerLite;

/**
 * Quick sync for cron: Only check for new campaigns
 * 
 * This is optimized for daily cron jobs:
 * - Fetches ONLY page 1 (most recent campaigns)
 * - Stops immediately if latest campaign already exists
 * - Never updates existing campaigns
 * - Fast and lightweight
 * 
 * @param string $apiKey MailerLite API key
 * @return array Result with count and any errors
 */
function sync_new_campaigns_only(string $apiKey): array {
    $imported = 0;
    $errors = [];
    
    // Create content directory if needed
    $content_dir = __DIR__ . '/../source/mailerlite_campaigns';
    $json_dir = $content_dir . '/json';
    if (!is_dir($content_dir)) {
        mkdir($content_dir, 0755, true);
    }
    if (!is_dir($json_dir)) {
        mkdir($json_dir, 0755, true);
    }
    
    try {
        $mailerLite = new MailerLite(['api_key' => $apiKey]);
        
        // Fetch ONLY page 1 (most recent campaigns)
        $response = $mailerLite->campaigns->get([
            'page' => 1,
            'filter' => ['status' => 'sent']
        ]);
        
        if (!isset($response['body']['data']) || empty($response['body']['data'])) {
            return ['imported' => 0, 'skipped' => 0, 'already_synced' => true, 'errors' => []];
        }
        
        $campaigns = $response['body']['data'];
        
        // Check the very first campaign (latest)
        $latest_campaign = $campaigns[0];
        $latest_id = $latest_campaign['id'] ?? null;
        
        if (!$latest_id) {
            return ['imported' => 0, 'skipped' => 0, 'already_synced' => false, 'errors' => ['Latest campaign missing ID']];
        }
        
        // If latest campaign already exists, we're done - nothing new
        if (get_campaign_by_source_id($latest_id, 'mailerlite')) {
            update_setting('last_sync', date('Y-m-d H:i:s'));
            return [
                'imported' => 0,
                'skipped' => count($campaigns),
                'already_synced' => true,
                'message' => 'Latest campaign already synced',
                'errors' => []
            ];
        }
        
        // Import only NEW campaigns (ones we don't have yet)
        foreach ($campaigns as $campaign) {
            $campaign_id = $campaign['id'] ?? null;
            if (!$campaign_id) {
                continue;
            }
            
            // Skip if already exists
            if (get_campaign_by_source_id($campaign_id, 'mailerlite')) {
                break; // Stop here - rest are old
            }
            
            try {
                if (import_mailerlite_campaign($campaign, $content_dir)) {
                    $imported++;
                }
            } catch (Exception $e) {
                $errors[] = "Failed to import campaign {$campaign_id}: " . $e->getMessage();
            }
        }
        
        update_setting('last_sync', date('Y-m-d H:i:s'));
        
        return [
            'imported' => $imported,
            'skipped' => 0,
            'already_synced' => false,
            'errors' => $errors
        ];
        
    } catch (Exception $e) {
        return [
            'imported' => 0,
            'skipped' => 0,
            'already_synced' => false,
            'errors' => [$e->getMessage()]
        ];
    }
}

/**
 * Sync campaigns from MailerLite API
 * 
 * @param string $apiKey MailerLite API key
 * @param int|null $limit Maximum campaigns to fetch (null for all)
 * @param callable|null $progressCallback Progress callback function
 * @return array Result with count and any errors
 */
function sync_mailerlite_campaigns(string $apiKey, ?int $limit = null, ?callable $progressCallback = null): array {
    // Extend time limit for large syncs
    set_time_limit(300);
    
    $imported = 0;
    $skipped = 0;
    $updated = 0;
    $errors = [];
    $page = 1;
    $api_delay = 2; // Seconds between API calls to avoid rate limiting (429)
    
    // Create content directory if needed
    $content_dir = __DIR__ . '/../source/mailerlite_campaigns';
    $json_dir = $content_dir . '/json';
    if (!is_dir($content_dir)) {
        mkdir($content_dir, 0755, true);
        if ($progressCallback) {
            $progressCallback(['message' => "✓ Created content directory", 'type' => 'info']);
        }
    }
    if (!is_dir($json_dir)) {
        mkdir($json_dir, 0755, true);
    }
    
    if ($progressCallback) {
        $progressCallback(['message' => '→ Initializing MailerLite SDK...', 'type' => 'info']);
    }
    
    try {
        $mailerLite = new MailerLite(['api_key' => $apiKey]);
        if ($progressCallback) {
            $progressCallback(['message' => '✓ SDK initialized successfully', 'type' => 'success']);
        }
    } catch (Exception $e) {
        $error = "Failed to initialize MailerLite SDK: " . $e->getMessage();
        if ($progressCallback) {
            $progressCallback(['message' => "✗ $error", 'type' => 'error']);
        }
        return ['imported' => 0, 'skipped' => 0, 'updated' => 0, 'errors' => [$error]];
    }
    
    if ($progressCallback) {
        $progressCallback(['message' => '→ Fetching campaigns from API...', 'type' => 'info']);
    }
    
    do {
        try {
            if ($progressCallback) {
                $progressCallback(['message' => "→ Requesting page $page...", 'type' => 'info']);
            }
            
            // Use correct MailerLite SDK parameter format (like museo)
            $response = $mailerLite->campaigns->get([
                'page' => $page,
                'filter' => ['status' => 'sent']
            ]);
            
            if (!isset($response['body']['data'])) {
                if ($progressCallback) {
                    $progressCallback(['message' => '✗ API response missing expected structure', 'type' => 'error']);
                }
                break;
            }
            
            $campaigns = $response['body']['data'];
            
            if (empty($campaigns)) {
                if ($progressCallback) {
                    $progressCallback(['message' => ($page === 1 ? '⚠ No sent campaigns found in your MailerLite account' : '✓ Reached end of campaigns'), 'type' => ($page === 1 ? 'warning' : 'success')]);
                }
                break;
            }
            
            $campaignCount = count($campaigns);
            if ($progressCallback) {
                $progressCallback(['message' => "✓ Found $campaignCount campaigns on page $page", 'type' => 'success']);
            }
            
            foreach ($campaigns as $campaign) {
                try {
                    $campaign_name = $campaign['name'] ?? $campaign['subject'] ?? 'Unnamed';
                    $campaign_id = $campaign['id'] ?? 'unknown';
                    
                    // Check if campaign exists and is unchanged
                    $existing = get_campaign_by_source_id($campaign_id, 'mailerlite');
                    $existing_json = $json_dir . '/' . $campaign_id . '.json';
                    
                    if ($existing && file_exists($existing_json)) {
                        $old_meta = json_decode(file_get_contents($existing_json), true);
                        $old_name = $old_meta['name'] ?? '';
                        $old_subject = $old_meta['subject'] ?? '';
                        $new_name = $campaign['name'] ?? '';
                        $new_subject = $campaign['subject'] ?? '';
                        
                        // Skip if name and subject are unchanged
                        if ($old_name === $new_name && $old_subject === $new_subject) {
                            $skipped++;
                            if ($progressCallback) {
                                $progressCallback(['message' => "  - Skipped (unchanged): '$campaign_name'", 'type' => 'info']);
                            }
                            continue;
                        } else {
                            if ($progressCallback) {
                                $progressCallback(['message' => "  → Updating: '$campaign_name' (changed)", 'type' => 'info']);
                            }
                        }
                    } else {
                        if ($progressCallback) {
                            $progressCallback(['message' => "  → Processing: '$campaign_name' (ID: $campaign_id)", 'type' => 'info']);
                        }
                    }
                    
                    $result = import_mailerlite_campaign($campaign, $content_dir);
                    if ($result) {
                        if ($existing) {
                            $updated++;
                            if ($progressCallback) {
                                $progressCallback([
                                    'type' => 'success',
                                    'message' => "    ✓ Updated successfully",
                                    'count' => $imported + $updated,
                                ]);
                            }
                        } else {
                            $imported++;
                            if ($progressCallback) {
                                $progressCallback([
                                    'type' => 'success',
                                    'message' => "    ✓ Imported successfully (#$imported)",
                                    'count' => $imported,
                                ]);
                            }
                        }
                    } else {
                        $skipped++;
                        if ($progressCallback) {
                            $progressCallback([
                                'type' => 'warning',
                                'message' => "    - Skipped (no content or error)",
                            ]);
                        }
                    }
                    
                    if ($limit && ($imported + $updated) >= $limit) {
                        if ($progressCallback) {
                            $progressCallback(['message' => "⚠ Reached import limit of $limit campaigns", 'type' => 'warning']);
                        }
                        break 2;
                    }
                } catch (Exception $e) {
                    $error_msg = "Failed to import '{$campaign_name}': " . $e->getMessage();
                    $errors[] = $error_msg;
                    if ($progressCallback) {
                        $progressCallback(['message' => "    ✗ ERROR: $error_msg", 'type' => 'error']);
                    }
                }
            }
            
            $page++;
            
            // Add delay between pages to avoid rate limiting
            if (!empty($campaigns)) {
                sleep($api_delay);
            }
            
        } catch (Exception $e) {
            $error_msg = "API error on page $page: " . $e->getMessage();
            $errors[] = $error_msg;
            if ($progressCallback) {
                $progressCallback(['message' => $error_msg, 'type' => 'error']);
            }
            
            // If rate limited (429), wait and retry
            if (strpos($e->getMessage(), '429') !== false) {
                if ($progressCallback) {
                    $progressCallback(['message' => "⚠ Rate limited. Waiting 10 seconds...", 'type' => 'warning']);
                }
                sleep(10);
                continue; // Retry the same page
            }
            
            break;
        }
        
    } while (count($campaigns) > 0);
    
    // Update last sync time
    update_setting('last_sync', date('Y-m-d H:i:s'));
    
    if ($progressCallback) {
        $progressCallback(['message' => "════════════════════════════════════", 'type' => 'info']);
        $progressCallback(['message' => "✓ SYNC COMPLETE", 'type' => 'success']);
        $progressCallback(['message' => "  → Imported: $imported", 'type' => 'success']);
        $progressCallback(['message' => "  → Updated: $updated", 'type' => 'info']);
        $progressCallback(['message' => "  → Skipped: $skipped", 'type' => 'info']);
        $progressCallback(['message' => "  → Errors: " . count($errors), 'type' => (count($errors) > 0 ? 'error' : 'info')]);
        $progressCallback(['message' => "════════════════════════════════════", 'type' => 'info']);
    }
    
    return [
        'imported' => $imported,
        'updated' => $updated,
        'skipped' => $skipped,
        'errors' => $errors,
    ];
}

/**
 * Import a single MailerLite campaign (saves HTML + JSON like museo)
 * 
 * @param array $campaign Campaign data from API
 * @param string $content_dir Directory to save HTML content
 * @return bool True if imported/updated
 */
function import_mailerlite_campaign(array $campaign, string $content_dir): bool {
    global $mailerlite_api_key;
    
    $source_id = $campaign['id'];
    
    // Check if already exists
    $existing = get_campaign_by_source_id($source_id, 'mailerlite');
    
    // Create JSON directory if needed
    $json_dir = $content_dir . '/json';
    if (!is_dir($json_dir)) {
        mkdir($json_dir, 0755, true);
    }
    
    // Fetch campaign HTML content using MailerLite SDK
    try {
        $mailerlite = new \MailerLite\MailerLite(['api_key' => $mailerlite_api_key]);
        $campaign_details = $mailerlite->campaigns->find($source_id);
        
        // Get HTML from response (museo pattern)
        $html = $campaign_details['body']['data']['html'] ?? 
                $campaign_details['body']['data']['emails'][0]['html'] ?? 
                $campaign_details['body']['data']['emails'][0]['content'] ?? '';
        
        if (empty($html)) {
            error_log("No HTML content in MailerLite response for campaign {$source_id}. Response structure: " . print_r($campaign_details, true));
            return false;
        }
    } catch (Exception $e) {
        error_log("EXCEPTION fetching campaign HTML for ID {$source_id}: " . $e->getMessage() . "\nStack: " . $e->getTraceAsString());
        return false;
    }
    
    // Save HTML file (just ID like museo: {id}.html)
    $html_filename = $source_id . '.html';
    file_put_contents($content_dir . '/' . $html_filename, $html);
    
    // Save JSON metadata
    $meta = $campaign;
    $meta['archived_at'] = date('c');
    file_put_contents($json_dir . '/' . $source_id . '.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Filename for database record - must include full path from doc root
    $content_path = 'source/mailerlite_campaigns/' . $html_filename;
    
    // Prepare campaign data
    $campaign_data = [
        'name' => $campaign['name'] ?? null,
        'subject' => $campaign['subject'] ?? $campaign['name'],
        'preview_text' => $campaign['settings']['preview_text'] ?? null,
        'sent_at' => $campaign['finished_at'] ?? $campaign['scheduled_at'] ?? date('Y-m-d H:i:s'),
        'source' => 'mailerlite',
        'source_id' => $source_id,
        'content_path' => $content_path,
    ];
    
    if ($existing) {
        // Update existing
        update_campaign($existing['id'], $campaign_data);
        $campaign_id = $existing['id'];
    } else {
        // Insert new
        $campaign_id = insert_campaign($campaign_data);
    }
    
    // Index campaign content for full-text search
    try {
        index_campaign_content($campaign_id);
    } catch (Exception $e) {
        error_log("Failed to index campaign {$campaign_id} for search: " . $e->getMessage());
        // Don't fail the import if indexing fails
    }
    
    return true;
}

/**
 * Sanitize a string for use as a filename
 * 
 * @param string $name Original name
 * @return string Sanitized filename
 */
function sanitize_filename(string $name): string {
    // Remove or replace problematic characters
    $name = preg_replace('/[^\w\s\-_]/u', '', $name);
    $name = preg_replace('/\s+/', '_', $name);
    $name = substr($name, 0, 100); // Limit length
    return strtolower(trim($name, '_'));
}

/**
 * Import campaigns from Mailchimp CSV export
 * 
 * Note: This function is kept for potential future use with Mailchimp API imports.
 * The current active Mailchimp importer (inc/mailchimp_import.php) handles ZIP exports.
 * This CSV-based function could be useful when campaigns are fetched via API.
 * 
 * @param string $csv_path Path to campaigns.csv
 * @param string $content_dir Directory containing HTML files
 * @param callable|null $progressCallback Progress callback
 * @return array Import result
 * 
 * @see process_mailchimp_import() in inc/mailchimp_import.php for ZIP-based import
 */
function import_mailchimp_campaigns(string $csv_path, string $content_dir, ?callable $progressCallback = null): array {
    if (!file_exists($csv_path)) {
        throw new Exception("CSV file not found: $csv_path");
    }
    
    $handle = fopen($csv_path, 'r');
    if (!$handle) {
        throw new Exception("Could not open CSV file");
    }
    
    $imported = 0;
    $skipped = 0;
    $errors = [];
    
    // Read header
    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        throw new Exception("Empty CSV file");
    }
    
    // Find column indices
    $cols = array_flip($header);
    $subject_col = $cols['Subject'] ?? $cols['subject'] ?? null;
    $date_col = $cols['Send Date'] ?? $cols['send_date'] ?? $cols['Date'] ?? null;
    
    if ($subject_col === null) {
        fclose($handle);
        throw new Exception("CSV missing required 'Subject' column");
    }
    
    while (($row = fgetcsv($handle)) !== false) {
        try {
            $subject = $row[$subject_col] ?? '';
            if (empty($subject)) {
                $skipped++;
                continue;
            }
            
            // Find matching HTML file
            $html_file = find_mailchimp_html($content_dir, $subject);
            if (!$html_file) {
                $skipped++;
                continue;
            }
            
            // Parse date
            $sent_at = $date_col !== null && isset($row[$date_col]) 
                ? date('Y-m-d H:i:s', strtotime($row[$date_col])) 
                : date('Y-m-d H:i:s');
            
            // Generate ID
            $content_path = basename($html_file);
            
            // Check for existing
            $id = generate_campaign_id([
                'source' => 'mailchimp',
                'sent_at' => $sent_at,
                'subject' => $subject,
            ]);
            
            $existing = get_campaign($id);
            if ($existing) {
                $skipped++;
                continue;
            }
            
            // Insert campaign
            insert_campaign([
                'id' => $id,
                'subject' => $subject,
                'sent_at' => $sent_at,
                'source' => 'mailchimp',
                'content_path' => $content_path,
            ]);
            
            // Index campaign content for full-text search
            try {
                index_campaign_content($id);
            } catch (Exception $e) {
                error_log("Failed to index Mailchimp campaign {$id} for search: " . $e->getMessage());
                // Don't fail the import if indexing fails
            }
            
            $imported++;
            
            if ($progressCallback) {
                $progressCallback([
                    'type' => 'progress',
                    'message' => "Imported: $subject",
                    'count' => $imported,
                ]);
            }
            
        } catch (Exception $e) {
            $errors[] = "Row error: " . $e->getMessage();
        }
    }
    
    fclose($handle);
    
    return [
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => $errors,
    ];
}

/**
 * Find HTML file matching a Mailchimp campaign subject
 * 
 * @param string $content_dir Content directory
 * @param string $subject Campaign subject
 * @return string|null Path to HTML file or null
 */
function find_mailchimp_html(string $content_dir, string $subject): ?string {
    // Try exact filename match first
    $sanitized = sanitize_filename($subject);
    $exact_path = $content_dir . '/' . $sanitized . '.html';
    if (file_exists($exact_path)) {
        return $exact_path;
    }
    
    // Scan directory for partial match
    $files = glob($content_dir . '/*.html');
    foreach ($files as $file) {
        $filename = basename($file, '.html');
        if (stripos($filename, $sanitized) !== false || stripos($sanitized, $filename) !== false) {
            return $file;
        }
    }
    
    return null;
}

/**
 * Send Server-Sent Event
 * 
 * @param string $message Message to send
 * @param string $type Event type (info, progress, error, complete)
 */
function send_sse_event(string $message, string $type = 'info'): void {
    echo "event: $type\n";
    echo "data: " . json_encode(['message' => $message, 'type' => $type]) . "\n\n";
    ob_flush();
    flush();
}

/**
 * Check if welcome page is enabled
 * 
 * @return bool True if welcome page is enabled
 */
function is_welcome_enabled(): bool {
    return get_setting('welcome_enabled', '1') === '1';
}

/**
 * Get welcome page configuration
 * 
 * @return array Welcome page configuration
 */
function get_welcome_config(): array {
    return [
        'enabled' => is_welcome_enabled(),
        'title' => get_setting('welcome_title', 'Welcome to Our Newsletter Archive'),
        'description' => get_setting('welcome_description', 'Explore our complete collection of newsletters. Browse past editions, search for specific topics, and never miss an insight.'),
        'subscribe_url' => get_setting('welcome_subscribe_url', ''),
        'subscribe_text' => get_setting('welcome_subscribe_text', 'Subscribe to Newsletter'),
        'archive_button_text' => get_setting('welcome_archive_button_text', 'Browse Archive'),
    ];
}

