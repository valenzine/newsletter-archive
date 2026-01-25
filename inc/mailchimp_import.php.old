<?php

/**
 * Mailchimp Import Logic
 * 
 * Handles one-time import of Mailchimp campaigns from a ZIP export.
 * Expected ZIP structure:
 * - campaigns.csv (metadata)
 * - *.html (campaign HTML files)
 */

require_once __DIR__ . '/database.inc.php';
require_once __DIR__ . '/functions.php';

/**
 * Process Mailchimp ZIP import
 * 
 * @param array $uploaded_file $_FILES array for the uploaded ZIP
 * @param string $campaigns_content_directory Directory to store HTML files
 * @return array ['success' => bool, 'message' => string, 'stats' => array]
 */
function process_mailchimp_import($uploaded_file, $campaigns_content_directory) {
    $stats = [
        'total' => 0,
        'imported' => 0,
        'skipped' => 0,
        'errors' => 0
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
        
        // Look for campaigns.csv
        $csv_path = $temp_dir . '/campaigns.csv';
        if (!file_exists($csv_path)) {
            throw new Exception('campaigns.csv not found in ZIP archive.');
        }
        
        // Parse CSV and import campaigns
        $result = import_campaigns_from_csv($csv_path, $temp_dir, $campaigns_content_directory);
        $stats = $result['stats'];
        
        // Clean up temp directory
        delete_directory($temp_dir);
        
        return [
            'success' => true,
            'message' => "Import complete: {$stats['imported']} imported, {$stats['skipped']} skipped, {$stats['errors']} errors.",
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
 * Import campaigns from CSV file
 * 
 * @param string $csv_path Path to campaigns.csv
 * @param string $source_dir Directory containing HTML files
 * @param string $destination_dir Directory to copy HTML files to
 * @return array ['stats' => array]
 */
function import_campaigns_from_csv($csv_path, $source_dir, $destination_dir) {
    $stats = [
        'total' => 0,
        'imported' => 0,
        'skipped' => 0,
        'errors' => 0
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
    
    // Expected columns: name, subject, sent_at, html_file
    $required_columns = ['subject', 'sent_at', 'html_file'];
    foreach ($required_columns as $col) {
        if (!in_array($col, $header)) {
            fclose($handle);
            throw new Exception("Missing required column: $col");
        }
    }
    
    // Create column index mapping
    $col_map = array_flip($header);
    
    // Process each row
    while (($row = fgetcsv($handle)) !== FALSE) {
        $stats['total']++;
        
        try {
            $subject = $row[$col_map['subject']] ?? '';
            $sent_at = $row[$col_map['sent_at']] ?? '';
            $html_file = $row[$col_map['html_file']] ?? '';
            $name = isset($col_map['name']) ? ($row[$col_map['name']] ?? '') : '';
            $preview_text = isset($col_map['preview_text']) ? ($row[$col_map['preview_text']] ?? '') : '';
            
            // Validate required fields
            if (empty($subject) || empty($sent_at) || empty($html_file)) {
                $stats['errors']++;
                continue;
            }
            
            // Parse date (support multiple formats)
            $timestamp = strtotime($sent_at);
            if ($timestamp === false) {
                $stats['errors']++;
                continue;
            }
            $sent_at_formatted = date('Y-m-d H:i:s', $timestamp);
            
            // Generate unique ID (hash of subject + date)
            $campaign_id = generate_campaign_id([
                'source' => 'mailchimp',
                'source_id' => '', // Mailchimp CSV doesn't have campaign IDs
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
            
            // Copy HTML file to destination
            $source_html = $source_dir . '/' . $html_file;
            if (!file_exists($source_html)) {
                $stats['errors']++;
                continue;
            }
            
            $destination_html = $destination_dir . '/' . $campaign_id . '.html';
            if (!copy($source_html, $destination_html)) {
                $stats['errors']++;
                continue;
            }
            
            // Calculate relative path for database
            $content_path = str_replace(dirname(__DIR__) . '/', '', $destination_html);
            
            // Insert into database
            $stmt = $pdo->prepare('
                INSERT INTO campaigns (id, name, subject, preview_text, sent_at, source, content_path, hidden, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ');
            
            $stmt->execute([
                $campaign_id,
                $name ?: $subject,
                $subject,
                $preview_text,
                $sent_at_formatted,
                'mailchimp',
                $content_path
            ]);
            
            $stats['imported']++;
            
        } catch (Exception $e) {
            $stats['errors']++;
            error_log('Mailchimp import error: ' . $e->getMessage());
        }
    }
    
    fclose($handle);
    
    return ['stats' => $stats];
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
