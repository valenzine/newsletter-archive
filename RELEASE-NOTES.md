# Release Notes

User-facing changes and improvements for Newsletter Archive.

## v0.1.0 — 2025-01-24

**First public release!** 🎉

### What's New
- Archive your MailerLite email campaigns automatically
- Import past campaigns from Mailchimp exports
- Search your newsletter archive with smart full-text search
- **Customize everything via admin interface** - no file editing needed!
  - Site title and description
  - Social sharing images
  - Welcome page content
  - Twitter attribution
- Secure admin dashboard with modern authentication
- Works on mobile, tablet, and desktop

### Configuration Made Easy
This release introduces a **hybrid configuration system**:
- Static settings (API keys, URLs) in `.env` file
- Dynamic customization via admin settings page
- No need to edit config files for branding!

### Getting Started
1. Copy `.env.example` to `.env`
2. Add your MailerLite API key
3. Visit `/setup/setup.php` to create your admin account
4. Customize site settings at `/setup/settings.php`
5. Start syncing your campaigns!

See the [README](README.md) for complete installation instructions.
