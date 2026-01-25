<?php
/**
 * API Endpoint: Campaign List
 * 
 * Returns a paginated list of campaigns with filtering options.
 * 
 * Query Parameters:
 * - page: Page number (default: 1)
 * - limit: Items per page (default: 20)
 * - sort: Sort order ("asc" or "desc", default: "desc")
 * - search: Search term for subject/preview text
 * 
 * Response Format:
 * {
 *   "success": true,
 *   "data": [...],
 *   "pagination": {
 *     "page": 1,
 *     "limit": 20,
 *     "total": 150,
 *     "totalPages": 8
 *   },
 *   "filters": {
 *     "sort": "desc",
 *     "search": null
 *   }
 * }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/database.inc.php';

try {
    // Get query parameters
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 20;
    $sort_order = isset($_GET['sort']) && $_GET['sort'] === 'asc' ? 'asc' : 'desc';
    $search_term = isset($_GET['search']) ? trim($_GET['search']) : null;
    
    // Get campaigns from database
    if ($search_term) {
        // Use full-text search with proper signature
        $search_results = search_campaigns($search_term, [], 1, 1000);
        $all_results = $search_results['results'];
        $total_count = $search_results['total'];
    } else {
        // Get total count
        $total_count = get_campaign_count(false);
        
        // Get paginated results
        $all_results = get_campaigns([
            'hidden' => false,
            'limit' => $limit,
            'offset' => ($page - 1) * $limit,
            'order' => $sort_order,
        ]);
    }
    
    $total_pages = ceil($total_count / $limit);
    
    // For search results, apply pagination manually
    if ($search_term) {
        $offset = ($page - 1) * $limit;
        $all_results = array_slice($all_results, $offset, $limit);
    }
    
    // Format campaigns for API response
    $formatted_campaigns = array_map(function($campaign) {
        return [
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
            'url' => '/' . $campaign['id'],
        ];
    }, $all_results);
    
    // Build response
    $response = [
        'success' => true,
        'data' => $formatted_campaigns,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total_count,
            'totalPages' => $total_pages,
        ],
        'filters' => [
            'sort' => $sort_order,
            'search' => $search_term,
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
