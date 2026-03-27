# Newsletter Archive

> A self-hosted platform to archive and display your email campaigns from MailerLite, with one-time import support for Mailchimp migrations.

Turn your newsletter archive into a beautiful, searchable website that you fully control. No third-party services, no monthly fees—just your campaigns, your way.

[![License: MPL 2.0](https://img.shields.io/badge/License-MPL%202.0-brightgreen.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%5E8.3-blue)](https://www.php.net/)

---

## 🌱 Origin

This project began as the campaign archive for [**Cómo funcionan las cosas**](https://comofuncionanlascos.as), a newsletter published since April 2017—first via Mailchimp (2017–2023) and then via MailerLite (2023–present). The live archive runs at [museo.curiosidad.club](https://museo.curiosidad.club) and includes additional custom features tailored to that publication.

This repository is a generalized, stripped-down version of that original codebase, adapted so that any newsletter author can deploy their own archive without modification.

---

## ✨ Features

### Core Functionality
- 📧 **Automatic MailerLite Sync** - Connect your MailerLite account and sync campaigns with one click
- 📥 **Mailchimp Migration** - One-time import from Mailchimp ZIP exports for easy platform transitions
- 🔍 **Smart Full-Text Search** - SQLite FTS5 with diacritic-insensitive matching and phrase search
- 📱 **Responsive Design** - Inbox-style layout that works beautifully on mobile, tablet, and desktop
- 🌐 **Clean URLs** - SEO-friendly routes like `/search/query` and `/{campaign-id}`

### Admin Experience
- 🔐 **Secure Admin Panel** - Database-backed authentication with rate limiting and remember-me tokens
- ⚙️ **Easy Configuration** - Customize your site through the admin interface—no file editing required
- 🎨 **Brand Control** - Set your site title, description, social images, and welcome page content
- 📈 **Google Analytics 4** - Optional GA4 integration with automatic event tracking for campaigns and searches
- 🔄 **Cron Support** - Automated daily syncs via scheduled tasks
- 🌍 **Internationalization** - Built-in English and Spanish (Argentina) translations

### Technical Highlights
- 🗄️ **SQLite Database** - Zero-configuration database, no MySQL/PostgreSQL needed
- 🎯 **No Dependencies** - Self-contained with PHP's built-in server for development
- 🔒 **Security First** - CSRF protection, rate limiting, secure sessions, and proper output escaping
- 📊 **Developer Friendly** - Clean codebase with PHPDoc comments and logical architecture

---

## 📋 Requirements

- **PHP 8.3+** with SQLite extension enabled
- **Composer** for dependency management
- **Web server** (Apache, Nginx) or PHP's built-in server for development

---

## 🚀 Installation

### 1. Get the Code

```bash
git clone https://github.com/valenzine/newsletter-archive.git
cd newsletter-archive
composer install
```

### 2. Configure Your Environment

```bash
cp .env.example .env
nano .env  # or use your preferred editor
```

**Minimum required settings:**

```env
SITE_BASE_URL=http://localhost:8182
MAILERLITE_API_KEY=your_api_key_here
```

Get your MailerLite API key from: [MailerLite → Settings → Integrations → API](https://dashboard.mailerlite.com/integrations/api)

### 3. Start the Application

**Development:**
```bash
php -S localhost:8182 router.php
```

**Production:** Configure your web server to serve the project directory with `router.php` as the entry point.

### 4. Complete First-Time Setup

1. Visit `http://localhost:8182`
2. Follow the setup wizard to create your admin account
3. Customize your site in **Settings**
4. Sync your campaigns in **Dashboard**

That's it! Your newsletter archive is live.

---

## 📖 Usage

### Syncing Campaigns

**Automatic Sync:**
1. Go to `/setup/setup.php` (admin dashboard)
2. Click **"Sync MailerLite Campaigns"**
3. Watch the progress as campaigns are imported

**Automated Daily Sync (Cron):**
1. Generate a secure cron token in **Settings**
2. Add this to your crontab:
   ```bash
   0 2 * * * curl -s "https://yourdomain.com/setup/mailerlite.php?token=YOUR_TOKEN&json=1" > /dev/null
   ```

### Migrating from Mailchimp

If you're switching from Mailchimp to MailerLite:

1. **Export from Mailchimp:**
   - Go to Account → Settings → Manage my data
   - Click "Export data" → Select "Emails" → Download ZIP

2. **Import via Admin:**
   - Go to `/setup/import_mailchimp.php`
   - Upload the ZIP file
   - Review import results

3. **Future campaigns sync automatically** from MailerLite

### Customizing Your Site

Visit `/setup/settings.php` to configure:
- Site title and description
- Social sharing image (Open Graph)
- Twitter attribution
- Welcome page content
- Language preference

---

## ⚙️ Configuration

### Hybrid Configuration System

This project uses a **flexible two-tier configuration**:

1. **`.env` file** → Deployment settings (API keys, base URL)
2. **Database settings** → User-facing customization (site title, images, content)

Database values override `.env` defaults. This means:
- ✅ Version control your `.env.example` with sensible defaults
- ✅ Customize branding via admin UI without touching files
- ✅ Different environments share the same codebase

### Environment Variables

Create a `.env` file in the project root:

| Variable | Required | Description |
|----------|----------|-------------|
| `SITE_BASE_URL` | Yes | Full URL to your site (no trailing slash) |
| `MAILERLITE_API_KEY` | Yes | Your MailerLite API key |
| `SITE_NAME` | No | Default site name |
| `SITE_DESCRIPTION` | No | Default meta description |
| `GOOGLE_ANALYTICS_ID` | No | Google Analytics 4 Measurement ID (format: G-XXXXXXXXXX) |
| `LOCALE` | No | Language code (`en`, `es`) |
| `APP_ENV` | No | `development` or `production` |
| `TIMEZONE` | No | PHP timezone identifier |

**All of these can be overridden** in `/setup/settings.php` except `SITE_BASE_URL` and `MAILERLITE_API_KEY`.

---

## 🎨 Customization

### Translations

Add new languages:

1. Copy [lang/en.php](lang/en.php) to `lang/your_locale.php`
2. Translate all strings
3. Set locale in admin settings or `.env`

Included languages: English (`en`), Spanish Argentina (`es`)

### Branding & Images

**Social sharing image** (1200×630px recommended):
- Upload to `/img/share.jpg`, or
- Set custom URL in admin settings

**Favicons** - Replace in `/img/`:
- `favicon.ico`, `favicon-16x16.png`, `favicon-32x32.png`
- `apple-touch-icon.png` (180×180px)
- `android-chrome-192x192.png`, `android-chrome-512x512.png`

### Styling

Compile SCSS to CSS:

```bash
npm install
npm run sass        # Compile main styles
npm run sass:admin  # Compile admin styles
npm run sass:all    # Compile both
```

Source files: [css/styles.scss](css/styles.scss), [css/admin.scss](css/admin.scss)

---

## 🗺️ Roadmap

The following features exist in the original [museo.curiosidad.club](https://museo.curiosidad.club) archive and are planned for eventual inclusion in this open-source version:

| Feature | Description |
|---------|-------------|
| **Campaign management UI** | Admin interface to override individual campaign metadata (subject, visibility, date) without touching the campaigns |
| **Campaign categorization** | Define custom categories using regex patterns matched against campaign names, then filter and browse the archive by category |
| **Member-only content gating** | Mark individual campaigns as subscriber-only and restrict public access to logged-in readers |
| **Reader authentication (magic link)** | Passwordless email login for paid subscribers to access gated content |
| **SSO (Discourse, Google, etc.)** | Allow readers to log in using an existing Discourse forum account, Google account, etc. |
| **Subscriber management** | Create, import, and manage the list of registered reader accounts from the admin panel |

---

## 🏗️ Project Structure

```
newsletter-archive/
├── api/                      # JSON API endpoints
│   ├── campaigns.php         # List campaigns (paginated)
│   └── search.php            # Full-text search
├── css/                      # SCSS source files
├── db/                       # SQLite database (auto-created)
├── inc/                      # PHP core
│   ├── bootstrap.php         # Config loader
│   ├── database.inc.php      # Database layer
│   ├── functions.php         # Helper functions
│   ├── i18n.php              # Translation system
│   └── schema.sql            # Database schema
├── js/                       # JavaScript
│   ├── main.js               # Archive SPA
│   └── search.js             # Search interface
├── lang/                     # Translation files
├── setup/                    # Admin interface
│   ├── setup.php             # Dashboard
│   ├── settings.php          # Configuration
│   ├── mailerlite.php        # Sync tool
│   └── import_mailchimp.php  # Import tool
├── source/                   # Campaign storage
│   ├── mailchimp_campaigns/  # Mailchimp HTML
│   └── mailerlite_campaigns/ # MailerLite HTML
├── index.php                 # Public archive
├── search.php                # Search page
├── router.php                # URL routing
└── .env                      # Environment config (gitignored)
```

---

## 🔌 API Reference

### List Campaigns

```http
GET /api/campaigns.php?page=1&limit=20&sort=desc
```

**Parameters:**
- `page` - Page number (default: 1)
- `limit` - Items per page (default: 20, max: 100)
- `sort` - Sort order: `asc` or `desc` (default: `desc`)
- `search` - Search term (optional)

**Response:**
```json
{
  "success": true,
  "data": [...],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 150,
    "totalPages": 8
  }
}
```

### Search Campaigns

```http
GET /api/search.php?q=newsletter&from=2025-01-01&to=2025-12-31
```

**Parameters:**
- `q` - Search query (supports phrase search with quotes)
- `from`, `to` - Date range filters (YYYY-MM-DD)
- `page`, `per_page` - Pagination
- `sort` - `relevance`, `date_desc`, or `date_asc`

**Response:**
```json
{
  "success": true,
  "total": 42,
  "results": [
    {
      "id": "abc123...",
      "subject": "Weekly Newsletter #42",
      "excerpt": "...matching <mark>newsletter</mark> content...",
      "sent_at": "2025-06-15 10:00:00",
      "url": "/view_campaign.php?id=abc123..."
    }
  ]
}
```

---

## 🛠️ Development

### Local Development

```bash
# Clone and install
git clone https://github.com/valenzine/newsletter-archive.git
cd newsletter-archive
composer install

# Configure
cp .env.example .env
# Edit .env and set APP_ENV=development

# Start server
php -S localhost:8182 router.php
```

Visit `http://localhost:8182` to begin setup.

### Database

- **Location:** `db/archive.sqlite`
- **Schema:** Auto-created on first run
- **Source:** [inc/schema.sql](inc/schema.sql)
- **Migrations:** Not needed for v0.1.0 (first release)

### Building CSS

Watch mode for development:
```bash
npm run sass:watch        # Main styles
npm run sass:watch:admin  # Admin styles
```

---

## 📄 License

This project is licensed under the **Mozilla Public License 2.0**. See [LICENSE](LICENSE) for details.

---

## 🙏 Acknowledgements

- [MailerLite PHP SDK](https://github.com/mailerlite/mailerlite-php) - Official API client
- [Flaticon](https://www.flaticon.com/free-icons/box) - Box icon by Maxim Basinski

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit issues or pull requests.

### Guidelines

1. Follow existing code style (PSR-12 for PHP)
2. Add PHPDoc comments for new functions
3. Test on PHP 8.3+
4. Update CHANGELOG.md with your changes

---

## 💬 Support

- **Issues:** [GitHub Issue Tracker](https://github.com/valenzine/newsletter-archive/issues)
- **Discussions:** Use GitHub Discussions for questions and ideas

---

## 🔐 Security Note

**Never commit `.env` to version control!** It contains sensitive API keys. The `.env.example` file is provided as a template.
