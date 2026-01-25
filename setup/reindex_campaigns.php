<?php
/**
 * Reindex All Campaigns
 * 
 * Rebuilds the full-text search index for all campaigns.
 * Extracts text content from HTML files and indexes into campaigns_fts table.
 */

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/database.inc.php';

echo "=== Reindex All Campaigns ===\n\n";

// Progress callback
$progressCallback = function($message) {
    echo "$message\n";
    flush();
};

// Run reindexing
$results = reindex_all_campaigns($progressCallback);

echo "\n=== Indexing Complete ===\n";
echo "Total campaigns: {$results['total']}\n";
echo "Successfully indexed: {$results['success']}\n";
echo "Failed: {$results['failed']}\n";

if ($results['failed'] > 0) {
    echo "\n⚠ Some campaigns failed to index. Check error log for details.\n";
    exit(1);
}

echo "\n✓ All campaigns indexed successfully!\n";
echo "You can now search the full content of all campaigns.\n";
