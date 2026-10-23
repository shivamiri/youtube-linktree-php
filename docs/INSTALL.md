# Installation Guide

Step-by-step install on free shared hosting (InfinityFree example). Any hosting with PHP 8+ and MySQL works the same way.

## What you need

- A hosting account with PHP 8+ and one MySQL database (the InfinityFree free plan works)
- This repository's files
- 10 minutes

## Step 1: Create the database

1. Open your hosting control panel (InfinityFree: client area > your account > Control Panel).
2. Go to **MySQL Databases**.
3. Create a new database. Note down:
   - MySQL Host Name (like `sql123.infinityfree.com`)
   - MySQL User Name
   - MySQL Password
   - MySQL Database Name

## Step 2: Import database.sql

1. In the control panel open **phpMyAdmin**.
2. Select your database from the left sidebar.
3. Click **Import** in the top menu.
4. Choose the `database.sql` file from this repo and press **Go**.
5. You should see 3 tables: `admin`, `channel_settings`, `videos`.

## Step 3: Upload the files

1. Open **File Manager** (or an FTP client like FileZilla).
2. Go to the `htdocs` folder.
3. Upload ALL files and folders from this repo:
   - `index.php`
   - `common/` (header.php, bottom.php)
   - `admin/` (the whole folder)
   - `upload/` (with its `.htaccess`)
4. Do NOT upload: `README.md`, `LICENSE`, `CHANGELOG.md`, `CONTRIBUTING.md`, `SECURITY.md`, `docs/`, `.github/`, `.gitignore`, `database.sql` (these are only for the repo). They cause no harm, they are just not needed.

## Step 4: Create config.php

1. In `htdocs/common/`, make a copy of `config.sample.php` and rename it to `config.php`.
   (Or: edit `config.sample.php` and use "Save As" `config.php`.)
2. Open `config.php` and fill in your Step 1 database details:
   - `DB_HOST` = MySQL Host Name
   - `DB_USER` = MySQL User Name
   - `DB_PASS` = MySQL Password
   - `DB_NAME` = MySQL Database Name
3. Optional: put your Google Analytics 4 ID in `GA_MEASUREMENT_ID`, or leave it empty.
4. Save.

**Never upload this `config.php` to GitHub.** It has your database password.

## Step 5: First login

1. Open `yourdomain.com/admin` in your browser.
2. Log in with:
   - Username: `admin`
   - Password: `admin123`
3. Immediately go to **Settings > Security** and change the username and password.

## Step 6: Turn on HTTPS

1. In the InfinityFree client area: your account > **Force HTTPS** / SSL settings.
2. Enable Force HTTPS. The app detects HTTPS and marks session cookies Secure automatically.

## Done

Open your domain. Your link page is live. Add videos from the admin panel: paste a YouTube link, the title and thumbnail fill in automatically. Save your channel URL in Settings and the channel logo auto-fetches (or upload/paste one manually).

## Troubleshooting

- **"Site is temporarily unavailable"**: DB details in config.php are wrong. Recheck Step 4 against Step 1.
- **Image upload fails on InfinityFree**: free hosting sometimes blocks multipart uploads temporarily. Use the Image URL option until it is back, and check the InfinityFree status page.
- **Logo auto-fetch fails**: some networks block outgoing requests. Use manual upload or image URL.
- **Forgot admin password**: in phpMyAdmin, delete the row in the `admin` table and re-import `database.sql` (this resets it to `admin` / `admin123`), then change it again.
