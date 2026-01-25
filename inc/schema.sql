-- Newsletter Archive Database Schema
-- SQLite database for storing campaign metadata

-- Campaigns table: stores metadata for all campaigns (MailerLite and Mailchimp)
CREATE TABLE IF NOT EXISTS campaigns (
    id TEXT PRIMARY KEY,              -- Unique hash/ID for the campaign
    name TEXT,                        -- Internal campaign name (optional)
    subject TEXT NOT NULL,            -- Email subject line
    preview_text TEXT,                -- Preview/preheader text
    sent_at DATETIME NOT NULL,        -- When the campaign was sent
    source TEXT NOT NULL,             -- 'mailerlite' or 'mailchimp'
    source_id TEXT,                   -- Original ID from the source platform
    content_path TEXT,                -- Relative path to HTML content file
    hidden INTEGER DEFAULT 0,         -- 1 = hidden from public view
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for common queries
CREATE INDEX IF NOT EXISTS idx_campaigns_sent_at ON campaigns(sent_at);
CREATE INDEX IF NOT EXISTS idx_campaigns_hidden ON campaigns(hidden);
CREATE INDEX IF NOT EXISTS idx_campaigns_source ON campaigns(source);

-- Search index table: stores searchable text for full-text search
-- Note: This is a standalone FTS5 table (not using external content)
-- remove_diacritics 2: Removes diacritics from both query AND content, so "Valentín" = "Valentin"
CREATE VIRTUAL TABLE IF NOT EXISTS campaigns_fts USING fts5(
    id UNINDEXED,
    subject,
    preview_text,
    content,
    tokenize = 'unicode61 remove_diacritics 2'
);

-- Note: We manage FTS indexing programmatically via index_campaign_content() function
-- Called automatically during sync in import_mailerlite_campaign() and import_mailchimp_campaigns()
-- No database triggers - content extraction from HTML requires custom processing

-- Admin authentication table (single admin account)
CREATE TABLE IF NOT EXISTS admin (
    id INTEGER PRIMARY KEY CHECK (id = 1),  -- Only one admin allowed
    username TEXT UNIQUE NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME
);

-- Login attempts for brute force protection
CREATE TABLE IF NOT EXISTS login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_address TEXT NOT NULL,
    attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    success INTEGER DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip_address, attempted_at);

-- Remember me tokens for persistent login
CREATE TABLE IF NOT EXISTS remember_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token_hash TEXT UNIQUE NOT NULL,
    admin_id INTEGER NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_remember_tokens_hash ON remember_tokens(token_hash);
CREATE INDEX IF NOT EXISTS idx_remember_tokens_expires ON remember_tokens(expires_at);

-- Settings table: stores application configuration
-- These override .env values when set (hybrid approach)
CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert default settings (NULL means use .env value)
INSERT OR IGNORE INTO settings (key, value) VALUES ('schema_version', '1.0.0');
INSERT OR IGNORE INTO settings (key, value) VALUES ('last_sync', NULL);

-- Site configuration (NULL = use .env, set value to override)
INSERT OR IGNORE INTO settings (key, value) VALUES ('site_title', NULL);
INSERT OR IGNORE INTO settings (key, value) VALUES ('site_description', NULL);
INSERT OR IGNORE INTO settings (key, value) VALUES ('og_image', NULL);
INSERT OR IGNORE INTO settings (key, value) VALUES ('twitter_site', NULL);
INSERT OR IGNORE INTO settings (key, value) VALUES ('twitter_creator', NULL);

-- Automation settings
INSERT OR IGNORE INTO settings (key, value) VALUES ('cron_token', NULL);

-- Localization settings
INSERT OR IGNORE INTO settings (key, value) VALUES ('locale', NULL);

-- Welcome page settings
INSERT OR IGNORE INTO settings (key, value) VALUES ('welcome_enabled', '1');
INSERT OR IGNORE INTO settings (key, value) VALUES ('welcome_title', 'Welcome to Our Newsletter Archive');
INSERT OR IGNORE INTO settings (key, value) VALUES ('welcome_description', 'Explore our complete collection of newsletters. Browse past editions, search for specific topics, and never miss an insight.');
INSERT OR IGNORE INTO settings (key, value) VALUES ('welcome_subscribe_url', '');
INSERT OR IGNORE INTO settings (key, value) VALUES ('welcome_subscribe_text', 'Subscribe to Newsletter');
INSERT OR IGNORE INTO settings (key, value) VALUES ('welcome_archive_button_text', 'Browse Archive');
