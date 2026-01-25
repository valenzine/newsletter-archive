<?php

/**
 * API Endpoint: Search
 * 
 * Full-text search for campaigns with filters.
 * Public endpoint - no authentication required.
 * 
 * Query Parameters:
 * - q: Search query (required to execute search; empty returns total=0)
 * - page: Page number (default: 1)
 * - per_page: Items per page (default: 20, max: 100)
 * - from: Filter by date from (YYYY-MM-DD)
 * - to: Filter by date to (YYYY-MM-DD)
 * - sort: Sort order (relevance, date_desc, date_asc)
 * 
 * Response Format:
 * {
 *   "success": true,
 *   "total": 150,
 *   "page": 1,
 *   "per_page": 20,
 *   "results": [
 *     {
 *       "id": "abc123",
 *       "subject": "Email Subject",
 *       "preview_text": "Preview text",
 *       "sent_at": "2023-01-01",
 *       "source": "mailerlite",
 *       "url": "/view_campaign.php?id=abc123",
 *       "excerpt": "...matching <mark>text</mark>..."
 *     }
 *   ]
 * }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/database.inc.php';

try {
    // Get and validate query parameters
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $perPage = isset($_GET['per_page']) ? min(100, max(1, intval($_GET['per_page']))) : 20;
    
    // If query is empty, return empty results
    if (empty($query)) {
        echo json_encode([
            'success' => true,
            'total' => 0,
            'page' => $page,
            'per_page' => $perPage,
            'results' => [],
            'message' => 'No search query provided. Enter a search term to begin.'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Build filters
    $filters = [];
    
    if (isset($_GET['from']) && !empty(trim($_GET['from']))) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($_GET['from']))) {
            $filters['from'] = trim($_GET['from']);
        }
    }
    
    if (isset($_GET['to']) && !empty(trim($_GET['to']))) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($_GET['to']))) {
            $filters['to'] = trim($_GET['to']);
        }
    }
    
    // Get sort parameter (default to relevance for search)
    $sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'relevance';
    $validSorts = ['relevance', 'date_desc', 'date_asc'];
    if (!in_array($sort, $validSorts)) {
        $sort = 'relevance';
    }
    
    // Perform search using database.inc.php function
    $results = search_campaigns($query, $filters, $page, $perPage, $sort);
    
    // Build response
    $response = [
        'success' => true,
        'total' => $results['total'],
        'page' => $results['page'],
        'per_page' => $results['per_page'],
        'results' => $results['results']
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => 'An error occurred while processing your search request.'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    // Log the actual error for debugging
    error_log("Search API error: " . $e->getMessage());
}
