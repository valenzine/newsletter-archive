# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.1] - 2026-01-25

### Analytics
- ADDED: Google Analytics 4 (GA4) integration for user behavior tracking
- ADDED: GA4 Measurement ID configuration in admin settings
- ADDED: Support for GOOGLE_ANALYTICS_ID environment variable (.env fallback)
- ADDED: Conditional loading - GA4 only loads when ID is configured
- ADDED: GA4 tracking on all public pages (campaign list, individual campaigns, search)
- ADDED: Virtual page view tracking for SPA navigation (tracks every campaign view)
- ADDED: Campaign metadata in GA4 events (campaign_id, campaign_subject, campaign_sent_at)
- ADDED: Search event tracking with search terms and result counts
- ADDED: Dynamic browser title updates for better analytics and UX

### URLs & Routing
- IMPROVED: Clean URLs for campaigns - now `/{campaign_id}` instead of `/?id={campaign_id}`
- IMPROVED: All internal links and APIs updated to use clean URL format
- IMPROVED: Better SEO, shareability, and professional appearance
- FIXED: Router regex pattern to correctly match 16-character campaign IDs

### UI/UX
- IMPROVED: Select dropdown styling in admin settings (consistent with other form inputs)

## [0.1.0] - 2025-01-24

Initial public release of Newsletter Archive.

### Core Features
- ADDED: MailerLite API integration for automatic campaign syncing
- ADDED: One-time Mailchimp import from ZIP exports (for migrations)
- ADDED: Full-text search with SQLite FTS5 (diacritic-insensitive, literal phrase support)
- ADDED: Responsive inbox-style layout with mobile, tablet, and desktop support
- ADDED: Clean URL routing (`/search/{query}`, `/{campaign_id}`)

### Admin Interface
- ADDED: Database-backed admin authentication with rate limiting
- ADDED: Remember me tokens (14-day duration)
- ADDED: CSRF protection on all admin forms
- ADDED: First-time setup wizard for admin account creation
- ADDED: Admin dashboard for sync operations and configuration
- ADDED: Settings page for customizing site without editing files
- ADDED: Campaign diagnostics tool
- ADDED: Automated sync support via cron jobs with secure token authentication

### Configuration
- ADDED: Hybrid configuration system (.env + database settings)
- ADDED: Configurable social metadata (Open Graph, Twitter Cards)
- ADDED: Welcome page with customizable content
- ADDED: Environment-based configuration via .env files

### Internationalization
- ADDED: i18n support with translation system
- ADDED: English (default) and Spanish (Argentina) translations
- ADDED: Locale setting configurable via admin settings

### Search Features
- ADDED: Full-text search across subject, preview text, and content
- ADDED: Date range filtering
- ADDED: Sort by relevance, newest, or oldest
- ADDED: Search result highlighting with excerpts
- ADDED: Back to search functionality with query preservation

### Technical
- TECHNICAL: SQLite database with auto-initialization
- TECHNICAL: Modern Sass/SCSS build system
- TECHNICAL: PHP 8.3+ with type hints
- TECHNICAL: Composer-based dependency management

