<?php

/**
 * English Language File
 * 
 * Default language for the Newsletter Archive application.
 */

return [
    // Site
    'site' => [
        'title' => 'Newsletter Archive',
        'description' => 'Archive of past email campaigns',
    ],
    
    // Navigation
    'nav' => [
        'back_to_list' => 'Back to list',
        'back_to_search' => 'Back to search',
        'previous' => 'Previous',
        'next' => 'Next',
        'search' => 'Search',
        'admin' => 'Admin',
    ],
    
    // Sorting
    'sort' => [
        'newest_first' => 'Sort: Newest first',
        'oldest_first' => 'Sort: Oldest first',
    ],
    
    // Campaign list
    'list' => [
        'loading' => 'Loading campaigns...',
        'no_campaigns' => 'No campaigns found',
        'select_campaign' => 'Select an email from the list to read it',
    ],
    
    // Campaign view
    'campaign' => [
        'loading' => 'Loading campaign...',
        'not_found' => 'Campaign not found',
        'load_error' => 'Failed to load campaign content',
    ],
    
    // Search
    'search' => [
        'page_title' => 'Search Archive',
        'title' => 'Search Archive',
        'subtitle' => 'Search through %s campaigns',
        'placeholder' => 'What are you looking for?',
        'button' => 'Search',
        'clear' => 'Clear search',
        'no_results' => 'No results found for "{query}"',
        'results_count' => '{count} results found',
        'searching' => 'Searching...',
        'enter_query' => 'Enter a search term to begin',
        'filters' => 'Filters',
        'date_range' => 'Date range',
        'date_from' => 'From',
        'date_to' => 'To',
        'sort_by' => 'Sort by',
        'sort_relevance' => 'Relevance',
        'sort_newest' => 'Newest first',
        'sort_oldest' => 'Oldest first',
        'per_page' => 'Results per page',
        'clear_filters' => 'Clear filters',
    ],
    
    // Admin
    'admin' => [
        'title' => 'Admin Dashboard',
        'sync' => 'Sync Campaigns',
        'import' => 'Import Campaigns',
        'settings' => 'Settings',
        'last_sync' => 'Last sync: {date}',
        'never_synced' => 'Never synced',
        'sync_now' => 'Sync Now',
        'syncing' => 'Syncing...',
        'sync_success' => 'Sync completed successfully',
        'sync_error' => 'Sync failed: {error}',
        'campaigns_total' => 'Total campaigns: {count}',
        'campaigns_hidden' => 'Hidden campaigns: {count}',
    ],
    
    // Import
    'import' => [
        'title' => 'Import Mailchimp Campaigns',
        'description' => 'Import campaigns from a Mailchimp export ZIP file.',
        'select_file' => 'Select ZIP file',
        'select_csv' => 'Select campaigns.csv',
        'importing' => 'Importing...',
        'success' => 'Successfully imported {count} campaigns',
        'error' => 'Import failed: {error}',
        'no_file' => 'Please select a file to import',
    ],
    
    // Errors
    'error' => [
        'generic' => 'An error occurred',
        'not_found' => 'Page not found',
        'server_error' => 'Internal server error',
        'api_error' => 'API request failed',
    ],
    
    // Dates (for JavaScript)
    'date' => [
        'months' => ['January', 'February', 'March', 'April', 'May', 'June', 
                     'July', 'August', 'September', 'October', 'November', 'December'],
        'months_short' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                          'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    ],
];
