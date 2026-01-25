<?php
/**
 * API Endpoint: Single Campaign
 * 
 * Returns details for a specific campaign.
 * 
 * Query Parameters:
 * - id: Campaign ID (required)
 * 
 * Response Format:
 * {
 *   "success": true,
 *   "data": {
 *     "id": "...",
 *     "subject": "...",
 *     "preview_text": "...",
 *     "content_path": "...",
 *     ...
 *   }
 * }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/database.inc.php';

try {
    // Get campaign ID
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Missing required parameter: id',
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    $campaign_id = trim($_GET['id']);
    
    // Find campaign
    $campaign = get_campaign($campaign_id);
    
    // Check if campaign exists and is not hidden
    if (!$campaign || $campaign['hidden']) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Campaign not found',
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    // Format response
    $response = [
        'success' => true,
        'data' => [
            'id' => $campaign['id'],
            'name' => $campaign['name'] ?? '',
            'subject' => $campaign['subject'] ?? '',
            'preview_text' => $campaign['preview_text'] ?? '',
            'date' => [
                'formatted' => date('M j, Y', strtotime($campaign['sent_at'])),
                'time' => date('g:i A', strtotime($campaign['sent_at'])),
                'timestamp' => strtotime($campaign['sent_at']),
                'iso' => $campaign['sent_at'],
            ],
            'source' => $campaign['source'],
            'content_path' => $campaign['content_path'],
            'url' => '/view_campaign.php?id=' . $campaign['id'],
        ],
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage(),
    ], JSON_PRETTY_PRINT);
}
