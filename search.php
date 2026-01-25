<?php

/**
 * Search Page
 * 
 * Full-text search interface for campaigns with filters and results display.
 * Public access - no authentication required.
 */

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/database.inc.php';
require_once __DIR__ . '/inc/i18n.php';

load_language(get_locale());

// Get search query from URL
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';

// Get total campaign count for display
$total_campaigns = get_campaign_count(false); // Only count visible campaigns

// Pass config to JavaScript
$search_config = [
    'apiBase' => '',
    'initialQuery' => $search_query,
    'siteName' => $site_title,
    'i18n' => get_translations()
];

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(get_locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('search.page_title'); ?> | <?= htmlspecialchars($site_title) ?></title>
    
    <!-- Styles -->
    <link rel="stylesheet" href="/css/styles.css?ver=<?= htmlspecialchars(get_composer_version()) ?>" />
    
    <!-- Social Metadata -->
    <?php require_once __DIR__ . '/inc/head.inc.php'; ?>
    
    <!-- Config -->
    <script>
        window.searchCfg = <?= json_encode($search_config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    </script>
    
    <!-- Scripts -->
    <script src="/js/search.js?ver=<?= htmlspecialchars(get_composer_version()) ?>" defer></script>
</head>
<body class="search-page">
    
    <div class="search-container">
        <a href="/" class="back-link"><?php _e('nav.back_to_list'); ?></a>
        
        <div class="search-header-sticky">
            <div class="search-header">
                <h1><?php _e('search.title'); ?></h1>
                <p><?= sprintf(__('search.subtitle'), number_format($total_campaigns)) ?></p>
            </div>
            
            <form class="search-form" id="searchForm">
                <div class="search-input-wrapper">
                    <input 
                        type="text" 
                        class="search-input" 
                        id="searchInput" 
                        name="q" 
                        placeholder="<?php _e('search.placeholder'); ?>"
                        autocomplete="off"
                        value="<?= htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8') ?>"
                    />
                    <button type="button" class="search-clear <?= empty($search_query) ? 'hidden' : '' ?>" id="searchClear" aria-label="<?php _e('search.clear'); ?>">
                        ×
                    </button>
                </div>
                <button type="submit" class="search-button" id="searchButton">
                    <?php _e('search.button'); ?>
                </button>
            </form>
        </div>
        
        <div class="search-layout">
            <aside class="search-filters">
                <div class="filters-header">
                    <h3><?php _e('search.filters'); ?></h3>
                    <button type="button" class="filters-toggle" id="filtersToggle" aria-label="Toggle filters">
                        <span class="toggle-icon">▼</span>
                    </button>
                </div>
                
                <div class="filters-content" id="filtersContent">
                    <div class="filter-group">
                        <label><?php _e('search.date_range'); ?></label>
                        <div class="date-inputs">
                            <input type="date" id="dateFrom" name="from" placeholder="<?php _e('search.date_from'); ?>" />
                            <input type="date" id="dateTo" name="to" placeholder="<?php _e('search.date_to'); ?>" />
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label for="sortOrder"><?php _e('search.sort_by'); ?></label>
                        <select id="sortOrder" name="sort">
                            <option value="relevance"><?php _e('search.sort_relevance'); ?></option>
                            <option value="date_desc"><?php _e('search.sort_newest'); ?></option>
                            <option value="date_asc"><?php _e('search.sort_oldest'); ?></option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="perPageSelect"><?php _e('search.per_page'); ?></label>
                        <select id="perPageSelect" name="per_page">
                            <option value="10">10</option>
                            <option value="20" selected>20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="button" class="clear-filters" id="clearFilters">
                            <?php _e('search.clear_filters'); ?>
                        </button>
                    </div>
                </div>
            </aside>
            
            <div class="search-results-wrapper">
                <div class="search-status" id="searchStatus">
                    <?php _e('search.enter_query'); ?>
                </div>
                
                <div id="searchResults"></div>
                
                <div class="pagination hidden" id="pagination"></div>
            </div>
        </div>
    </div>
</body>
</html>
