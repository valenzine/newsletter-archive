<?php

/**
 * Mailchimp Import Logic
 * 
 * Handles one-time import of Mailchimp campaigns from a ZIP export.
 * 
 * Expected ZIP structure (Mailchimp exports are nested):
 * - {numeric_id}/{list_id}/campaigns.csv (metadata)
 * - {numeric_id}/{list_id}/campaigns_content/*.html (campaign HTML files)
 * 
 * Imported campaigns are stored in: /source/mailchimp_campaigns/{campaign_id}.html
 */

require_once __DIR__ . '/database.inc.php';
require_once __DIR__ . '/functions.php';

/**
 * Process Mailchimp ZIP import
 * 
 * @param array $uploaded_file $_FILES array for the uploaded ZIP
 * @param string $destination_directory Directory to store HTML files (/source/mailchimp_campaigns)
 * @return array ['success' => bool, 'message' => string, 'stats' => array]
 */
function process_mailchimp_import($uploaded_file, $destination_directory) {
    $stats = [
        'total' => 0,
        'imported' => 0,
        'skipped' => 0,
        'errors' => 0,
        'unmatched' => 0,
        'unmatched_list' => []
    ];
    
    // Validate upload
    if (!isset($uploaded_file['tmp_name']) || !file_exists($uploaded_file['tmp_name'])) {
        return [
            'success' => false,
            'message' => 'No file uploaded or file not found.',
            'stats' => $stats
        ];
    }
    
    // Check if it's a ZIP file
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $uploaded_file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, ['application/zip', 'application/x-zip-compressed'])) {
        return [
            'success' => false,
            'message' => 'File must be a ZIP archive.',
            'stats' => $stats
        ];
    }
    
    // Create temp extraction directory
    $temp_dir = sys_get_temp_dir() . '/mailchimp_import_' . uniqid();
    if (!mkdir($temp_dir, 0755, true)) {
        return [
            'success' => false,
            'message' => 'Failed to create temporary directory.',
            'stats' => $stats
        ];
    }
    
    try {
        // Extract ZIP
        $zip = new ZipArchive();
        if ($zip->open($uploaded_file['tmp_name']) !== TRUE) {
            throw new Exception('Failed to open ZIP archive.');
        }
        
        $zip->extractTo($temp_dir);
        $zip->close();
        
        // Find campaigns.csv (may be nested in subdirectories)
        $csv_path = find_file_recursive($temp_dir, 'campaigns.csv');
        if (!$csv_path) {
            throw new Exception('campaigns.csv not found in ZIP archive. Please ensure your Mailchimp export is complete.');
        }
        
        $csv_dir = dirname($csv_path);
        
        // Find campaigns_content directory (same level as CSV)
        $html_source_dir = $csv_dir . '/campaigns_content';
        if (!is_dir($html_source_dir)) {
            throw new Exception('campaigns_content directory not found. Expected at: ' . $html_source_dir);
        }
        
        // Build inventory of available HTML files
        $html_inventory = build_html_file_inventory($html_source_dir);
        
        if (empty($html_inventory)) {
            throw new Exception('No HTML files found in campaigns_content directory.');
        }
        
        // Parse CSV and import campaigns
        $result = import_campaigns_from_csv($csv_path, $html_inventory, $destination_directory);
        $stats = $result['stats'];
        
        // Clean up temp directory
        delete_directory($temp_dir);
        
        $message = "Import complete: {$stats['imported']} imported, {$stats['skipped']} skipped, {$stats['errors']} errors";
        if ($stats['unmatched'] > 0) {
            $message .= ", {$stats['unmatched']} unmatched (metadata only)";
        }
        $message .= ".";
        
        return [
            'success' => true,
            'message' => $message,
            'stats' => $stats
        ];
        
    } catch (Exception $e) {
        // Clean up on error
        if (is_dir($temp_dir)) {
            delete_directory($temp_dir);
        }
        
        return [
            'success' => false,
            'message' => 'Import failed: ' . $e->getMessage(),
            'stats' => $stats
        ];
    }
}

/**
 * Find a file recursively in a directory
 * 
 * @param string $dir Directory to search
 * @param string $filename Filename to find
 * @return string|null Full path to file or null if not found
 */
function find_file_recursive($dir, $filename) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getFilename() === $filename) {
            return $file->getPathname();
        }
    }
    
    return null;
}

/**
 * Build inventory of HTML files in campaigns_content directory
 * 
 * @param string $dir Directory containing HTML files
 * @return array Array of file info: [['filename' => '...', 'mailchimp_id' => '...', 'slug' => '...', 'path' => '...'], ...]
 */
function build_html_file_inventory($dir) {
    $inventory = [];
    
    $files = scandir($dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) !== 'html') {
            continue;
        }
        
        $full_path = $dir . '/' . $file;
        
        // Parse filename: {mailchimp_id}_{slug}.html or {mailchimp_id}_{title}.html
        // Example: 12020101_-24-george-michael-amigos.html
        $parts = explode('_', $file, 2);
        $mailchimp_id = $parts[0] ?? '';
        $slug = isset($parts[1]) ? pathinfo($parts[1], PATHINFO_FILENAME) : '';
        
        $inventory[] = [
            'filename' => $file,
            'mailchimp_id' => $mailchimp_id,
            'slug' => $slug,
            'path' => $full_path
        ];
    }
    
    return $inventory;
}

/**
 * Import campaigns from CSV file
 * 
 * @param string $csv_path Path to campaigns.csv
 * @param array $html_inventory Array of available HTML files
 * @param string $destination_dir Directory to copy HTML files to (/source/mailchimp_campaigns)
 * @return array ['stats' => array]
 */
function import_campaigns_from_csv($csv_path, $html_inventory, $destination_dir) {
    $stats = [
        'total' => 0,
        'imported' => 0,
        'skipped' => 0,
        'errors' => 0,
        'unmatched' => 0,
        'unmatched_list' => []
    ];
    
    $pdo = get_database();
    
    // Ensure destination directory exists
    if (!is_dir($destination_dir)) {
        mkdir($destination_dir, 0755, true);
    }
    
    // Open CSV
    if (($handle = fopen($csv_path, 'r')) === FALSE) {
        throw new Exception('Failed to open campaigns.csv');
    }
    
    // Read header row
    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        throw new Exception('Invalid CSV format - no header row');
    }
    
    // Expected Mailchimp columns: Title, Subject, Send Date, Unique Id
    $required_columns = ['Subject', 'Send Date'];
    foreach ($required_columns as $col) {
        if (!in_array($col, $header)) {
            fclose($handle);
            throw new Exception("Missing required CSV column: $col");
        }
    }
    
    // Create column index mapping
    $col_map = array_flip($header);
    
    // Process each row
    while (($row = fgetcsv($handle)) !== FALSE) {
        $stats['total']++;
        
        try {
            // Extract CSV data using actual Mailchimp column names
            $title = isset($col_map['Title']) ? ($row[$col_map['Title']] ?? '') : '';
            $subject = $row[$col_map['Subject']] ?? '';
            $send_date_raw = $row[$col_map['Send Date']] ?? '';
            $unique_id = isset($col_map['Unique Id']) ? ($row[$col_map['Unique Id']] ?? '') : '';
            
            // Validate required fields
            if (empty($subject) || empty($send_date_raw)) {
                $stats['errors']++;
                continue;
            }
            
            // Clean and parse date (Mailchimp format: "May 19, 2019 06:00 pm" with quotes)
            $send_date_clean = trim($send_date_raw, '"');
            $timestamp = strtotime($send_date_clean);
            if ($timestamp === false) {
                error_log("Mailchimp import: Failed to parse date: $send_date_raw");
                $stats['errors']++;
                continue;
            }
            $sent_at_formatted = date('Y-m-d H:i:s', $timestamp);
            
            // Generate unique campaign ID
            $campaign_id = generate_campaign_id([
                'source' => 'mailchimp',
                'source_id' => $unique_id,
                'sent_at' => $sent_at_formatted,
                'subject' => $subject
            ]);
            
            // Check if campaign already exists
            $stmt = $pdo->prepare('SELECT id FROM campaigns WHERE id = ?');
            $stmt->execute([$campaign_id]);
            if ($stmt->fetch()) {
                $stats['skipped']++;
                continue;
            }
            
            // Try to match CSV row to HTML file
            $matched_html = match_html_file($subject, $title, $unique_id, $html_inventory);
            
            $content_path = null;
            
            if ($matched_html) {
                // Copy HTML file to destination
                $destination_html = $destination_dir . '/' . $campaign_id . '.html';
                if (copy($matched_html['path'], $destination_html)) {
                    // Calculate relative path for database
                    $content_path = str_replace(dirname(__DIR__) . '/', '', $destination_html);
                } else {
                    error_log("Mailchimp import: Failed to copy HTML file: {$matched_html['path']}");
                }
            } else {
                // No matching HTML file found - import metadata only
                $stats['unmatched']++;
                $stats['unmatched_list'][] = [
                    'subject' => $subject,
                    'title' => $title,
                    'date' => $sent_at_formatted,
                    'unique_id' => $unique_id
                ];
            }
            
            // Use subject as name if title is empty or just a number pattern
            $name = (!empty($title) && !preg_match('/^#?\d+$/', $title)) ? $title : $subject;
            
            // Insert into database
            $stmt = $pdo->prepare('
                INSERT INTO campaigns (id, name, subject, preview_text, sent_at, source, content_path, hidden, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ');
            
            $stmt->execute([
                $campaign_id,
                $name,
                $subject,
                '', // Mailchimp export doesn't include preview text
                $sent_at_formatted,
                'mailchimp',
                $content_path
            ]);
            
            // Index for full-text search if we have content
            if ($content_path && file_exists($destination_dir . '/' . $campaign_id . '.html')) {
                index_campaign_content($campaign_id);
            }
            
            $stats['imported']++;
            
        } catch (Exception $e) {
            $stats['errors']++;
            error_log('Mailchimp import row error: ' . $e->getMessage());
        }
    }
    
    fclose($handle);
    
    return ['stats' => $stats];
}

/**
 * Match a CSV row to an HTML file from inventory
 * 
 * @param string $subject Campaign subject
 * @param string $title Campaign title
 * @param string $unique_id Mailchimp unique ID
 * @param array $html_inventory Array of HTML file data
 * @return array|null Matched HTML file data or null
 */
function match_html_file($subject, $title, $unique_id, $html_inventory) {
    // Strategy 1: Try exact match by Mailchimp unique ID (if it appears in filename)
    if (!empty($unique_id)) {
        foreach ($html_inventory as $html_file) {
            if (strpos($html_file['mailchimp_id'], $unique_id) !== false || 
                strpos($html_file['filename'], $unique_id) !== false) {
                return $html_file;
            }
        }
    }
    
    // Strategy 2: Match by subject slug similarity
    $subject_slug = normalize_for_matching($subject);
    $title_slug = normalize_for_matching($title);
    
    $best_match = null;
    $best_score = 0;
    
    foreach ($html_inventory as $html_file) {
        $file_slug = normalize_for_matching($html_file['slug']);
        
        // Calculate similarity with both subject and title
        $subject_similarity = calculate_similarity($subject_slug, $file_slug);
        $title_similarity = $title ? calculate_similarity($title_slug, $file_slug) : 0;
        
        $score = max($subject_similarity, $title_similarity);
        
        // Require at least 70% similarity to consider it a match
        if ($score > $best_score && $score >= 0.7) {
            $best_score = $score;
            $best_match = $html_file;
        }
    }
    
    return $best_match;
}

/**
 * Normalize string for matching (remove accents, lowercase, remove special chars)
 * 
 * @param string $str String to normalize
 * @return string Normalized string
 */
function normalize_for_matching($str) {
    // Remove accents
    $str = remove_diacritics($str);
    
    // Lowercase
    $str = mb_strtolower($str, 'UTF-8');
    
    // Remove special characters, keep only alphanumeric and spaces
    $str = preg_replace('/[^a-z0-9\s]/', '', $str);
    
    // Normalize whitespace
    $str = preg_replace('/\s+/', ' ', trim($str));
    
    return $str;
}

/**
 * Calculate similarity between two strings
 * 
 * @param string $str1 First string
 * @param string $str2 Second string
 * @return float Similarity score (0-1)
 */
function calculate_similarity($str1, $str2) {
    if (empty($str1) || empty($str2)) {
        return 0;
    }
    
    // Use similar_text for percentage similarity
    similar_text($str1, $str2, $percent);
    
    return $percent / 100;
}

/**
 * Recursively delete a directory
 * 
 * @param string $dir Directory path
 */
function delete_directory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? delete_directory($path) : unlink($path);
    }
    
    rmdir($dir);
}
