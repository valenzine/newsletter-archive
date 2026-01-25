<?php

/**
 * Database Connection Layer
 * 
 * Provides SQLite database connection and helper functions.
 * This replaces the previous SleekDB implementation.
 */

/**
 * Get the SQLite database connection (singleton pattern)
 * 
 * @return PDO Database connection
 */
function get_database(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        $db_path = __DIR__ . '/../db/archive.sqlite';
        $db_dir = dirname($db_path);
        
        // Ensure db directory exists
        if (!is_dir($db_dir)) {
            mkdir($db_dir, 0755, true);
        }
        
        $is_new_db = !file_exists($db_path);
        
        $pdo = new PDO('sqlite:' . $db_path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Enable foreign keys
        $pdo->exec('PRAGMA foreign_keys = ON');
        
        // Initialize schema if new database
        if ($is_new_db) {
            initialize_database($pdo);
        }
    }
    
    return $pdo;
}

/**
 * Initialize the database with the schema
 * 
 * @param PDO $pdo Database connection
 */
function initialize_database(PDO $pdo): void {
    $schema_path = __DIR__ . '/schema.sql';
    
    if (!file_exists($schema_path)) {
        throw new RuntimeException('Database schema file not found: ' . $schema_path);
    }
    
    $schema = file_get_contents($schema_path);
    $pdo->exec($schema);
}

/**
 * Check if database is initialized and has the schema
 * 
 * @return bool True if database is ready
 */
function is_database_ready(): bool {
    try {
        $pdo = get_database();
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='campaigns'");
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}

// =============================================================================
// Campaign CRUD Operations
// =============================================================================

/**
 * Get all campaigns with optional filtering
 * 
 * @param array $options Filtering options:
 *   - hidden: bool - Include hidden campaigns (default: false)
 *   - source: string - Filter by source ('mailerlite' or 'mailchimp')
 *   - limit: int - Maximum number of results
 *   - offset: int - Offset for pagination
 *   - order: string - 'asc' or 'desc' (default: 'desc')
 * @return array List of campaigns
 */
function get_campaigns(array $options = []): array {
    $pdo = get_database();
    
    $include_hidden = $options['hidden'] ?? false;
    $source = $options['source'] ?? null;
    $limit = $options['limit'] ?? null;
    $offset = $options['offset'] ?? 0;
    $order = ($options['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    
    $sql = 'SELECT * FROM campaigns WHERE 1=1';
    $params = [];
    
    if (!$include_hidden) {
        $sql .= ' AND hidden = 0';
    }
    
    if ($source !== null) {
        $sql .= ' AND source = ?';
        $params[] = $source;
    }
    
    $sql .= ' ORDER BY sent_at ' . $order;
    
    if ($limit !== null) {
        $sql .= ' LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get a single campaign by ID
 * 
 * @param string $id Campaign ID
 * @return array|null Campaign data or null if not found
 */
function get_campaign(string $id): ?array {
    $pdo = get_database();
    $stmt = $pdo->prepare('SELECT * FROM campaigns WHERE id = ?');
    $stmt->execute([$id]);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Get a campaign by source ID
 * 
 * @param string $source_id Source platform ID
 * @param string $source Source platform ('mailerlite' or 'mailchimp')
 * @return array|null Campaign data or null if not found
 */
function get_campaign_by_source_id(string $source_id, string $source): ?array {
    $pdo = get_database();
    $stmt = $pdo->prepare('SELECT * FROM campaigns WHERE source_id = ? AND source = ?');
    $stmt->execute([$source_id, $source]);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Insert a new campaign
 * 
 * @param array $data Campaign data
 * @return string The campaign ID
 */
function insert_campaign(array $data): string {
    $pdo = get_database();
    
    // Generate ID if not provided
    if (empty($data['id'])) {
        $data['id'] = generate_campaign_id($data);
    }
    
    $stmt = $pdo->prepare('
        INSERT INTO campaigns (id, name, subject, preview_text, sent_at, source, source_id, content_path, hidden)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    
    $stmt->execute([
        $data['id'],
        $data['name'] ?? null,
        $data['subject'],
        $data['preview_text'] ?? null,
        $data['sent_at'],
        $data['source'],
        $data['source_id'] ?? null,
        $data['content_path'] ?? null,
        $data['hidden'] ?? 0,
    ]);
    
    return $data['id'];
}

/**
 * Update an existing campaign
 * 
 * @param string $id Campaign ID
 * @param array $data Fields to update
 * @return bool True if updated, false if not found
 */
function update_campaign(string $id, array $data): bool {
    $pdo = get_database();
    
    $fields = [];
    $params = [];
    
    $allowed_fields = ['name', 'subject', 'preview_text', 'sent_at', 'content_path', 'hidden'];
    
    foreach ($allowed_fields as $field) {
        if (array_key_exists($field, $data)) {
            $fields[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    if (empty($fields)) {
        return false;
    }
    
    $fields[] = 'updated_at = CURRENT_TIMESTAMP';
    $params[] = $id;
    
    $sql = 'UPDATE campaigns SET ' . implode(', ', $fields) . ' WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->rowCount() > 0;
}

/**
 * Delete a campaign
 * 
 * @param string $id Campaign ID
 * @return bool True if deleted, false if not found
 */
function delete_campaign(string $id): bool {
    $pdo = get_database();
    $stmt = $pdo->prepare('DELETE FROM campaigns WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

/**
 * Toggle campaign hidden status
 * 
 * @param string $id Campaign ID
 * @param bool $hidden New hidden status
 * @return bool True if updated
 */
function set_campaign_hidden(string $id, bool $hidden): bool {
    return update_campaign($id, ['hidden' => $hidden ? 1 : 0]);
}

/**
 * Get total campaign count
 * 
 * @param bool $include_hidden Include hidden campaigns in count
 * @return int Total count
 */
function get_campaign_count(bool $include_hidden = false): int {
    $pdo = get_database();
    $sql = 'SELECT COUNT(*) as count FROM campaigns';
    if (!$include_hidden) {
        $sql .= ' WHERE hidden = 0';
    }
    $stmt = $pdo->query($sql);
    return (int) $stmt->fetch()['count'];
}

/**
 * Search campaigns using FTS5 full-text search
 * 
 * Supports:
 * - Diacritic-insensitive search: "Valentín" and "Valentin" both match
 * - Literal search with quotes: "Jorge Luis Borges" matches exact phrase
 * - Normal search: Jorge Luis Borges matches any campaign with all three words
 * 
 * @param string $query Search query
 * @param array $filters Additional filters (from, to)
 * @param int $page Page number (1-indexed)
 * @param int $perPage Results per page
 * @param string $sort Sort order (relevance, date_desc, date_asc)
 * @return array Search results with total count
 */
function search_campaigns(string $query, array $filters = [], int $page = 1, int $perPage = 20, string $sort = 'relevance'): array {
    $pdo = get_database();
    
    // Sanitize and prepare FTS5 query
    $ftsQuery = sanitize_fts_query($query);
    
    if (empty($ftsQuery)) {
        return [
            'total' => 0,
            'page' => $page,
            'per_page' => $perPage,
            'results' => []
        ];
    }
    
    // Build WHERE clause for filters
    $whereClauses = ['c.hidden = 0'];
    $params = [':query' => $ftsQuery];
    
    if (isset($filters['from']) && !empty($filters['from'])) {
        $whereClauses[] = 'c.sent_at >= :from';
        $params[':from'] = $filters['from'] . ' 00:00:00';
    }
    
    if (isset($filters['to']) && !empty($filters['to'])) {
        $whereClauses[] = 'c.sent_at <= :to';
        $params[':to'] = $filters['to'] . ' 23:59:59';
    }
    
    $whereSQL = implode(' AND ', $whereClauses);
    
    // Determine ORDER BY clause
    $orderBy = match($sort) {
        'date_desc' => 'c.sent_at DESC',
        'date_asc' => 'c.sent_at ASC',
        default => 'rank' // relevance
    };
    
    // Get total count
    $countSQL = "
        SELECT COUNT(*) as total
        FROM campaigns c
        JOIN campaigns_fts fts ON c.id = fts.id
        WHERE campaigns_fts MATCH :query AND $whereSQL
    ";
    
    $countStmt = $pdo->prepare($countSQL);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    
    // Get paginated results with FTS5 snippet for highlighting
    $offset = ($page - 1) * $perPage;
    
    $sql = "
        SELECT 
            c.id,
            c.subject,
            c.preview_text,
            c.sent_at,
            c.source,
            snippet(campaigns_fts, 3, '<mark>', '</mark>', '...', 64) as excerpt
        FROM campaigns c
        JOIN campaigns_fts fts ON c.id = fts.id
        WHERE campaigns_fts MATCH :query AND $whereSQL
        ORDER BY $orderBy
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format results for API
    $results = [];
    foreach ($campaigns as $campaign) {
        $results[] = [
            'id' => $campaign['id'],
            'subject' => $campaign['subject'],
            'preview_text' => $campaign['preview_text'] ?? '',
            'sent_at' => $campaign['sent_at'],
            'source' => $campaign['source'],
            'url' => '/view_campaign.php?id=' . urlencode($campaign['id']),
            'excerpt' => $campaign['excerpt'] ?? ''
        ];
    }
    
    return [
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'results' => $results
    ];
}

/**
 * Sanitize and prepare FTS5 query
 * 
 * - Literal search (wrapped in quotes): exact phrase match
 * - Normal search: AND + prefix matching for all words
 * - Normalizes diacritics for consistent matching
 * 
 * @param string $query User query
 * @return string Sanitized FTS5 query
 */
function sanitize_fts_query(string $query): string {
    $query = trim($query);
    
    if (empty($query)) {
        return '';
    }
    
    // Check if query is wrapped in quotes for literal/exact search
    $isLiteralSearch = (strlen($query) >= 2 && $query[0] === '"' && $query[strlen($query) - 1] === '"');
    
    if ($isLiteralSearch) {
        // Literal search: remove outer quotes and keep inner content as-is
        if (strlen($query) <= 2) {
            return ''; // Empty or invalid literal search like ""
        }
        $query = substr($query, 1, -1);
        
        // Escape internal quotes for FTS5
        $query = str_replace('"', '""', $query);
        
        // Return as exact phrase search
        return '"' . $query . '"';
    }
    
    // Normal search: normalize diacritics for consistent matching
    $query = remove_diacritics($query);
    
    // Replace quotes with escaped quotes
    $query = str_replace('"', '""', $query);
    
    // Normalize punctuation that FTS5 treats as token separators
    $normalizedQuery = preg_replace('/[-_\/]+/', ' ', $query);
    
    // Split into words
    $words = preg_split('/\s+/', trim($normalizedQuery));
    $words = array_filter($words); // Remove empty strings
    
    if (empty($words)) {
        return '';
    }
    
    if (count($words) === 1) {
        // Single word: prefix search for better UX
        return $normalizedQuery . '*';
    } else {
        // Multiple words: AND search to find documents containing all words
        return implode(' AND ', array_map(function($word) {
            return $word . '*';
        }, $words));
    }
}

/**
 * Remove diacritics (accents) from text
 * Ensures consistent matching between "Napoleón" and "Napoleon"
 * 
 * @param string $text Text with potential diacritics
 * @return string Text without diacritics
 */
function remove_diacritics(string $text): string {
    // Normalize to NFD (decomposed form) to separate base chars from diacritics
    $normalized = normalizer_normalize($text, Normalizer::NFD);
    
    // Remove combining diacritical marks (Unicode category Mn)
    $withoutDiacritics = preg_replace('/\p{Mn}/u', '', $normalized);
    
    // Normalize back to NFC (composed form)
    return normalizer_normalize($withoutDiacritics, Normalizer::NFC);
}

/**
 * Generate a search excerpt with highlighting
 * 
 * @param string $subject Campaign subject
 * @param string $preview Preview text
 * @param string $query Search query
 * @return string Highlighted excerpt
 */
function generate_excerpt(string $subject, string $preview, string $query): string {
    $text = $subject . ' ' . $preview;
    
    // Simple highlighting - wrap matches in <mark> tags
    $pattern = '/(' . preg_quote($query, '/') . ')/iu';
    $highlighted = preg_replace($pattern, '<mark>$1</mark>', $text);
    
    // Truncate to reasonable length
    if (strlen($highlighted) > 200) {
        $highlighted = substr($highlighted, 0, 197) . '...';
    }
    
    return $highlighted;
}

/**
 * Generate a unique campaign ID based on content
 * 
 * @param array $data Campaign data
 * @return string Generated ID
 */
function generate_campaign_id(array $data): string {
    $unique_string = ($data['source'] ?? '') . '|' . ($data['source_id'] ?? '') . '|' . ($data['sent_at'] ?? '') . '|' . ($data['subject'] ?? '');
    return substr(hash('sha256', $unique_string), 0, 16);
}

// =============================================================================
// Full-Text Search Indexing
// =============================================================================

/**
 * Extract plain text from HTML content
 * 
 * @param string $html HTML content
 * @return string Plain text content
 */
function extract_text_from_html(string $html): string {
    if (empty($html)) {
        return '';
    }
    
    // Remove script and style tags
    $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
    $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
    
    // Remove HTML comments
    $html = preg_replace('/<!--.*?-->/s', '', $html);
    
    // Remove MailChimp/MailerLite merge tags and conditionals
    $html = preg_replace('/\*\|[^|]+\|\*/', '', $html);
    $html = preg_replace('/<!--\s*{%.*?%}\s*-->/s', '', $html);
    
    // Strip remaining HTML tags
    $text = strip_tags($html);
    
    // Decode HTML entities
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Normalize whitespace
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    
    return $text;
}

/**
 * Index campaign content for full-text search
 * 
 * @param string $campaign_id Campaign ID
 * @return bool Success
 */
function index_campaign_content(string $campaign_id): bool {
    try {
        $pdo = get_database();
        
        // Get campaign data
        $campaign = get_campaign($campaign_id);
        if (!$campaign) {
            error_log("Cannot index campaign $campaign_id: not found");
            return false;
        }
        
        // Get HTML content path
        $content_path = __DIR__ . '/../' . $campaign['content_path'];
        
        if (!file_exists($content_path)) {
            error_log("Cannot index campaign $campaign_id: content file not found at $content_path");
            return false;
        }
        
        // Read and extract text from HTML
        $html = file_get_contents($content_path);
        $text = extract_text_from_html($html);
        
        // Delete existing FTS entry
        $stmt = $pdo->prepare('DELETE FROM campaigns_fts WHERE id = ?');
        $stmt->execute([$campaign_id]);
        
        // Insert into FTS table
        $stmt = $pdo->prepare('
            INSERT INTO campaigns_fts (id, subject, preview_text, content)
            VALUES (?, ?, ?, ?)
        ');
        
        $stmt->execute([
            $campaign_id,
            $campaign['subject'],
            $campaign['preview_text'] ?? '',
            $text
        ]);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error indexing campaign $campaign_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Reindex all campaigns for full-text search
 * 
 * @param callable|null $progressCallback Optional callback for progress updates
 * @return array Results with counts
 */
function reindex_all_campaigns(?callable $progressCallback = null): array {
    $results = [
        'success' => 0,
        'failed' => 0,
        'total' => 0
    ];
    
    try {
        $pdo = get_database();
        
        // Get all campaigns
        $campaigns = get_campaigns(['hidden' => true]); // Include hidden for complete index
        $results['total'] = count($campaigns);
        
        if ($progressCallback) {
            $progressCallback("Found {$results['total']} campaigns to index");
        }
        
        foreach ($campaigns as $index => $campaign) {
            $num = $index + 1;
            
            if ($progressCallback) {
                $progressCallback("[$num/{$results['total']}] Indexing: {$campaign['subject']}");
            }
            
            if (index_campaign_content($campaign['id'])) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }
        
        if ($progressCallback) {
            $progressCallback("Indexing complete! Success: {$results['success']}, Failed: {$results['failed']}");
        }
        
    } catch (Exception $e) {
        error_log("Error during reindexing: " . $e->getMessage());
        if ($progressCallback) {
            $progressCallback("Error: " . $e->getMessage());
        }
    }
    
    return $results;
}

/**
 * Get a setting value from database
 * Returns NULL if setting doesn't exist or is explicitly NULL
 * 
 * @param string $key Setting key
 * @return string|null Setting value
 */
function get_setting(string $key): ?string {
    static $cache = [];
    
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    
    $db = get_database();
    $stmt = $db->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    
    $value = $result ? $result['value'] : null;
    $cache[$key] = $value;
    
    return $value;
}

/**
 * Update or insert a setting value
 * 
 * @param string $key Setting key
 * @param string|null $value Setting value (NULL to use .env default)
 * @return bool Success
 */
function update_setting(string $key, ?string $value): bool {
    $db = get_database();
    $stmt = $db->prepare('
        INSERT INTO settings (key, value, updated_at) 
        VALUES (?, ?, datetime("now"))
        ON CONFLICT(key) DO UPDATE SET 
            value = excluded.value,
            updated_at = excluded.updated_at
    ');
    
    return $stmt->execute([$key, $value]);
}

/**
 * Get all settings as associative array
 * 
 * @return array Settings key => value pairs
 */
function get_all_settings(): array {
    $db = get_database();
    $stmt = $db->query('SELECT key, value FROM settings ORDER BY key');
    $settings = [];
    
    while ($row = $stmt->fetch()) {
        $settings[$row['key']] = $row['value'];
    }
    
    return $settings;
}
