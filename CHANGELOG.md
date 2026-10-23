# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2026-10-23

First public open source release.

### Added
- CSRF tokens on all admin forms, including video delete
- Login brute-force protection: 5 failed attempts = 5 minute lock
- Hardened image upload: real-image check (getimagesize), server-side extension, random filenames
- `upload/.htaccess` blocks PHP execution in the uploads folder
- Channel logo auto-fetch from YouTube channel page (og:image, host allowlist, image validation), manual upload and image URL kept as fallback
- Secure session cookies: HttpOnly, SameSite=Lax, Secure on HTTPS
- Google Analytics 4 support via `GA_MEASUREMENT_ID` in config (empty = off)
- `database.sql` installer, `config.sample.php`, docs, issue and PR templates

### Changed
- Video delete now uses POST + CSRF token instead of a GET link
- Database connection errors are logged, visitors see a generic message
- Output escaping moved to display points

### Removed
- `install.php` (security risk on live sites, replaced by `database.sql`)

## [0.1.0] - 2026-06-26

Initial version: mobile-first dark theme, admin panel, video manager, oEmbed title fetch, image uploads.
