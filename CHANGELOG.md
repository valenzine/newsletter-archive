# Changelog

All notable changes to this project will be documented in this file.

This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Entries are organized by type (Refactor, Fix, Enhancement, Add, Code Quality) for quick scanning, rather than category sections.

## [0.2.3] - 2026-02-01
- Fix: Sidebar is no longer limited to 100 entries; now shows all campaigns in archive (up to 1000).
- Fix: Mailchimp merge tags (`*|IF:xxx|*`, `*|REWARDS|*`, etc.) are now removed from campaign display
- Enhancement: Campaign viewer scroll behavior improved across all devices:
  - Content panel automatically resets to top when navigating between campaigns (desktop & mobile)
  - Sidebar scrolls to center selected campaign on direct URL access
  - Mobile back-to-list returns to last viewed campaign position (instant scroll)
- Fix: Mobile loading indicator visual glitches resolved by loading into properly sized parent container
- Code Quality: Include `inc/functions.php` in bootstrap to make utility functions available globally

## [0.2.2] - 2026-01-31
- Fix: GA4 page titles now consistent across all page views; always use full format `{Title} | {Site Name}` to prevent duplicate entries in analytics

## [0.2.1] - 2026-01-31
- Refactor: Centralized session initialization in `ensure_admin_session()` helper; removed duplicate session code from `login.php`, `logout.php`, `setup.php`
- Fix: Session now auto-initialized in `is_admin_authenticated()` and `require_admin()`, fixing CSRF "Invalid request" errors on settings page

## [0.2.0] - 2026-01-31

- Refactor: Unified page head template (`inc/page_head.inc.php`) replaces `inc/head.inc.php`; all pages now use `$page_config` array for metadata, favicons, fonts, CSS, and analytics
- Refactor: Admin styles moved to modular SCSS blocks (`admin.scss`): login, setup, sync-controls, progress-log, stats-grid, import-form, onboarding cards, danger-action-box
- Refactor: Public styles (`styles.scss`) consolidated with new responsive components
- Code Quality: Remove legacy `inc/session.inc.php`; admin auth handled via `inc/admin_auth.php`
- Code Quality: Remove unused diagnostic tools (`setup/diagnose_campaigns.php`, `js/diagnose-campaigns.js`, `setup/mailerlite-archiver.js`)
- Code Quality: Remove orphaned helper functions from `inc/functions.php`: `trailingslashit()`, `fetch_url_with_user_agent()`, `get_filename()`, `match_campaign_to_file()`
- Fix: `index_campaign_content()` call in `inc/mailchimp_import.php` now uses correct single-parameter signature
- Fix: CSRF token verification in admin pages uses correct `verify_csrf_token()` function
- Fix: Search page properly initializes i18n strings via `window.searchCfg`
- Enhancement: MailerLite sync UI improved with semantic CSS classes, force-sync checkbox, and collapsible progress log
- Enhancement: Setup pages use `inline-form` and `danger-action-box` patterns for better UX
- Add: New i18n keys for 404 page, setup prompts, and search query titles in `lang/en.php` and `lang/es.php`

## [0.1.1] - 2026-01-25
- Add: Google Analytics 4 (GA4) integration for user behavior tracking
- Add: GA4 Measurement ID configuration in admin settings
- Add: Support for GOOGLE_ANALYTICS_ID environment variable (.env fallback)
- Add: GA4 tracking on all public pages (campaign list, individual campaigns, search)
- Add: Virtual page view tracking for SPA navigation (tracks every campaign view)
- Add: Campaign metadata in GA4 events (campaign_id, campaign_subject, campaign_sent_at)
- Add: Search event tracking with search terms and result counts
- Add: Dynamic browser title updates for better analytics and UX
- Enhancement: Clean URLs for campaigns - now `/{campaign_id}` instead of `/?id={campaign_id}`
- Enhancement: All internal links and APIs updated to use clean URL format
- Enhancement: Better SEO, shareability, and professional appearance
- Enhancement: Select dropdown styling in admin settings (consistent with other form inputs)
- Fix: Router regex pattern to correctly match 16-character campaign IDs
- Fix: GA4 conditional loading - GA4 only loads when ID is configured

## [0.1.0] - 2025-01-24
Initial public release of Newsletter Archive.

- Add: MailerLite API integration for automatic campaign syncing
- Add: One-time Mailchimp import from ZIP exports (for migrations)
- Add: Full-text search with SQLite FTS5 (diacritic-insensitive, literal phrase support)
- Add: Responsive inbox-style layout with mobile, tablet, and desktop support
- Add: Clean URL routing (`/search/{query}`, `/{campaign_id}`)
- Add: Database-backed admin authentication with rate limiting
- Add: Remember me tokens (14-day duration)
- Add: CSRF protection on all admin forms
- Add: First-time setup wizard for admin account creation
- Add: Admin dashboard for sync operations and configuration
- Add: Settings page for customizing site without editing files
- Add: Campaign diagnostics tool
- Add: Automated sync support via cron jobs with secure token authentication
- Add: Hybrid configuration system (.env + database settings)
- Add: Configurable social metadata (Open Graph, Twitter Cards)
- Add: Welcome page with customizable content
- Add: Environment-based configuration via .env files
- Add: i18n support with translation system
- Add: English (default) and Spanish (Argentina) translations
- Add: Locale setting configurable via admin settings
- Add: Date range filtering for search results
- Add: Sort by relevance, newest, or oldest for search
- Add: Search result highlighting with excerpts
- Add: Back to search functionality with query preservation
- Code Quality: SQLite database with auto-initialization
- Code Quality: Modern Sass/SCSS build system
- Code Quality: PHP 8.3+ with type hints
- Code Quality: Composer-based dependency management

