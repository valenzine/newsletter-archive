# Newsletter Archive

A self-hosted, open-source email campaign archive for MailerLite users, with support for exported Mailchimp campaigns. Display your past newsletters in a beautiful, searchable interface.

## Features

- 📧 **MailerLite Integration** - Sync campaigns automatically via API
- 📥 **Mailchimp Import** - One-time import from Mailchimp exports (for migrations)
- 🔍 **Full-text Search** - SQLite FTS5 powered search across all campaigns
- 🌍 **Internationalization** - Multi-language support (English included)
- 📱 **Responsive Design** - Mobile-friendly inbox-style layout
- 🔒 **Admin Interface** - HTTP Basic Auth protected admin dashboard
- 🗄️ **SQLite Database** - No external database required

## Quick Start

### Requirements

- PHP 8.1+ with SQLite extension
- Composer
- Web server (Apache/Nginx) or PHP built-in server

### Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/valenzine/newsletter-archive.git
   cd newsletter-archive
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Configure environment:**
   ```bash
   cp .env.example .env
   nano .env
   ```

   Required settings:
   ```env
   SITE_NAME="My Newsletter Archive"
   SITE_DESCRIPTION="Archive of past campaigns"
   SITE_BASE_URL=https://yourdomain.com
   MAILERLITE_API_KEY=your-api-key-here
   LOCALE=en
   ```

4. **Set up admin authentication:**
   ```bash
   # Create admin password for /setup/ directory
   htpasswd -c setup/.htpasswd admin
   ```

5. **Start the server:**
   ```bash
   # Development
   php -S localhost:8182 router.php
   
   # Production: Configure Apache/Nginx to serve the directory
   ```

6. **Sync your campaigns:**
   - Navigate to `http://localhost:8182/setup/setup.php`
   - Click "Sync MailerLite" to import your campaigns

### First-Time Setup

**Option A: Fresh Start (MailerLite only)**
1. Add your MailerLite API key to `.env`
2. Access admin dashboard
3. Click "Sync MailerLite"

**Option B: Migrate from Mailchimp**
1. Export your campaigns from Mailchimp (as ZIP with HTML files)
2. Place `campaigns.csv` in `/source/`
3. Place HTML files in `/source/campaigns_content/`
4. Access admin dashboard
5. Click "Import Mailchimp"
6. Add MailerLite API key and sync future campaigns

## Project Structure

```
newsletter-archive/
├── api/              # JSON API endpoints
├── css/              # SCSS source files
├── db/               # SQLite database (auto-created)
├── inc/              # PHP includes (bootstrap, database, i18n)
├── js/               # JavaScript application files
├── lang/             # Translation files
├── setup/            # Admin interface (HTTP Auth protected)
├── source/           # Campaign content storage
│   ├── mailchimp_campaigns/     # Mailchimp HTML files
│   └── mailerlite_campaigns/  # MailerLite HTML files
├── index.php         # Main archive page
├── search.php        # Search interface
└── view_campaign.php # Single campaign view
```

## Configuration

### Hybrid Configuration System

Configuration uses a **hybrid approach** for flexibility:

1. **`.env` file** - Deployment-specific settings (API keys, URLs, environment type)
2. **Database settings** - User-customizable settings (site title, social images, welcome text)
3. **Admin UI** - Edit database settings via `/setup/settings.php`

**Database settings override `.env` values when set.** This allows:
- Static configuration in `.env` for deployment
- Dynamic customization via admin interface without editing files
- Easy version control of defaults (`.env.example`)

### Environment Variables (.env)

| Variable | Description | Default |
|----------|-------------|---------|
| `SITE_NAME` | Site title | "Newsletter Archive" |
| `SITE_TITLE` | Page title (og:title) | Same as SITE_NAME |
| `SITE_DESCRIPTION` | Meta description | "Archive of past email campaigns" |
| `SITE_BASE_URL` | Full site URL | - |
| `OG_IMAGE` | Social sharing image | /img/share.jpg |
| `TWITTER_SITE` | Twitter handle (site) | - |
| `TWITTER_CREATOR` | Twitter handle (creator) | - |
| `MAILERLITE_API_KEY` | MailerLite API key | - |
| `LOCALE` | Language (en, es, etc.) | en |
| `APP_ENV` | development or production | production |
| `TIMEZONE` | Timezone for dates | UTC |

**Note:** Values in `.env` are defaults. Override them via `/setup/settings.php` without editing files.

### Admin Customization

After first-time setup, visit `/setup/settings.php` to customize:

- Site title and description
- Social sharing image (Open Graph)
- Twitter handles for attribution
- Welcome page content and buttons

**All settings can be edited via the admin interface** - no need to touch config files!

## Customization

### Adding Translations

1. Create `/lang/your_locale.php` based on `/lang/en.php`
2. Translate all strings
3. Set `LOCALE=your_locale` in `.env`

### Branding & Images

Replace the default placeholder images with your own:

- **Social sharing image**: `/img/share.jpg` (1200x630px recommended)
  - Upload your image to `/img/share.jpg` OR
  - Set custom path in admin settings (`/setup/settings.php`)
  - Can be relative path or full URL

- **Favicon**: Replace files in `/img/`:
  - `favicon.ico` (32x32px)
  - `favicon-16x16.png` and `favicon-32x32.png`
  - `apple-touch-icon.png` (180x180px)
  - `android-chrome-192x192.png` and `android-chrome-512x512.png`

### Styling

Styles are in SCSS format in `/css/` directory:
- `styles.scss` - Main site styles
- `admin.scss` - Admin interface styles

Compile with:
```bash
npm install
npm run sass:all
```

## Development

### Testing with Sample Data

```bash
# Import test data (uses existing /source/ files)
php setup/import_test_data.php

# Start development server
php -S localhost:8182 router.php
```

### Database

SQLite database location: `/db/archive.sqlite`

Schema is auto-created on first run. See `/inc/schema.sql` for structure.

## API Endpoints

- `GET /api/campaigns.php` - List campaigns (paginated)
  - `?page=1` - Page number
  - `?limit=20` - Results per page
  - `?sort=asc|desc` - Sort order

- `GET /api/campaign.php?id=ID` - Get single campaign

- `GET /api/search.php` - Search campaigns
  - `?q=query` - Search term
  - `?from=YYYY-MM-DD` - Date filter
  - `?to=YYYY-MM-DD` - Date filter

## License

[Mozilla Public License 2.0](LICENSE)

## Acknowledgements

- [Box icons](https://www.flaticon.com/free-icons/box) created by Maxim Basinski on Flaticon.

## Contributing

Contributions welcome! Please open an issue or pull request.

## Support

For issues and questions, please use the [GitHub issue tracker](https://github.com/valenzine/newsletter-archive/issues).

**Important:** The `.env` file must exist on production but should NEVER be committed to git.
