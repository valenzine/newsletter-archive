# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2025-01-24

### Initial Release

- IMPROVED: Settings page UI with comprehensive form styling
- IMPROVED: Visual hierarchy with section accent bars and better spacing
- IMPROVED: Form inputs with focus states and hover effects
- IMPROVED: Helper text styling with monospace code blocks
- FIXED: Duplicate generate_campaign_id() function removed from mailchimp_import.php
- FIXED: Mailchimp import updated to use generate_campaign_id() with source tracking
- FIXED: Authentication function calls in mailerlite.php and import_mailchimp.php (was using undefined is_admin(), now uses is_admin_authenticated())
- FIXED: Missing session.inc.php require in settings.php causing CSRF token validation failure
- FIXED: Site title from database settings now displays correctly everywhere (replaced all $site_name with $site_title)
- FIXED: Page titles in all setup pages (login, settings, setup, mailerlite, import_mailchimp) now use configured site_title
- FIXED: Main header <h1> in index.php now shows configured site_title instead of hardcoded "Newsletter Archive"
- FIXED: JavaScript configs (archiveCfg, searchCfg) now pass site_title for dynamic titles
- FIXED: Search page document.title updates now use configured site name from settings
- TECHNICAL: Modern Sass with color.adjust() instead of deprecated darken()/lighten()
- TECHNICAL: Zero Sass deprecation warnings
- ADDED: MailerLite API integration for syncing campaigns
- ADDED: One-time Mailchimp CSV import for migrations
- ADDED: Full-text search with SQLite FTS5 (diacritic-insensitive)
- ADDED: Literal search support with quoted phrases
- ADDED: Responsive inbox-style layout with mobile support
- ADDED: Database-backed admin authentication with rate limiting
- ADDED: Remember me tokens (14-day duration)
- ADDED: CSRF protection on admin forms
- ADDED: First-time setup flow for admin creation
- ADDED: Hybrid configuration system (.env + database settings)
- ADDED: Admin settings UI for customizing site without editing files
- ADDED: Configurable social metadata (Open Graph, Twitter Cards)
- ADDED: Welcome page with customizable content
- ADDED: Admin dashboard for sync and configuration
- ADDED: Campaign diagnostics tool
- ADDED: Internationalization support (English default)
- ADDED: Automatic FTS indexing during sync
- ADDED: Date range filtering for search
- ADDED: Campaign hiding/unhiding
- ADDED: Environment-based configuration via .env files
