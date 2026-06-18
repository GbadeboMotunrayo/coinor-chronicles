# Deploying Coinor Chronicles to Hostinger

## What you need
- Hostinger shared hosting plan (Business or higher — needs Python 3)
- Your domain: `coinorchronicles.com` (already purchased)
- Anthropic Claude API key
- CoinGecko free API (no key needed)

---

## Step 1 — Create the MySQL Database

1. Go to **hPanel → Databases → MySQL Databases**
2. Create a database: `coinor_chronicles`
3. Create a user: `coinor_user` with a strong password
4. Assign the user to the database with **All Privileges**
5. Note the **host** (usually `localhost` on shared hosting)

---

## Step 2 — Run the Schema

1. Go to **hPanel → Databases → phpMyAdmin**
2. Select `coinor_chronicles`
3. Click **Import**, upload `website/schema.sql`
4. Click **Go**

---

## Step 3 — Upload the Files

**Option A — File Manager (easiest)**
1. Go to **hPanel → Files → File Manager**
2. Navigate to `public_html/`
3. Upload all files from the `website/` folder into `public_html/`
4. Upload `python_scripts/` into the root of your hosting account (one level above `public_html/`), so the path is `/home/YOUR_USER/python_scripts/`

**Option B — Git (faster for updates)**
```bash
# SSH into your Hostinger account
ssh YOUR_USER@coinorchronicles.com

# Clone the repo
cd ~
git clone https://github.com/GbadeboMotunrayo/coinor-chronicles.git

# Symlink the website folder into public_html
ln -s ~/coinor-chronicles/website/* ~/public_html/
```

---

## Step 4 — Create config.local.php

Create `/public_html/includes/config.local.php` with your real credentials:

```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'coinor_chronicles');
define('DB_USER', 'coinor_user');
define('DB_PASS', 'YOUR_STRONG_DB_PASSWORD');

define('SITE_URL', 'https://coinorchronicles.com');

define('CLAUDE_API_KEY', 'sk-ant-YOUR_REAL_CLAUDE_KEY');

// Admin panel password — change this!
define('ADMIN_PASS', 'YOUR_ADMIN_PASSWORD_HERE');
```

> **Important:** This file is in `.gitignore` and will never be pushed to GitHub.

---

## Step 5 — Create the Python config

Create `/home/YOUR_USER/python_scripts/config.local.py`:

```python
DB_HOST     = "localhost"
DB_NAME     = "coinor_chronicles"
DB_USER     = "coinor_user"
DB_PASS     = "YOUR_STRONG_DB_PASSWORD"
CLAUDE_KEY  = "sk-ant-YOUR_REAL_CLAUDE_KEY"
```

Then edit `python_scripts/config.py` so it reads from `config.local.py` if present (it's already set up to use environment variables as fallback).

---

## Step 6 — Set Up Cron Jobs

Go to **hPanel → Advanced → Cron Jobs** and add these:

### Pipeline (runs every 6 hours — generates the story)
```
0 0,6,12,18 * * *
```
Command:
```
cd /home/YOUR_USER && /usr/bin/python3 python_scripts/pipeline.py >> logs/pipeline.log 2>&1
```

### Newsletter (runs 1 hour after each pipeline run)
```
0 1,7,13,19 * * *
```
Command (replace YOUR_KEY with the first 16 chars of `sha256(ADMIN_PASS)`):
```
wget -q -O /dev/null "https://coinorchronicles.com/admin/send-newsletter.php?key=YOUR_KEY"
```

> **Find your key:** Log into `/admin/`, it's shown in the Pipeline Trigger section.

---

## Step 7 — Test Everything

1. Visit `https://coinorchronicles.com` — homepage should load
2. Visit `https://coinorchronicles.com/admin/` — log in with your `ADMIN_PASS`
3. Click **Run Now** in the Pipeline Trigger section — wait ~30 seconds
4. Refresh admin — you should see Episode 001 in Recent Chronicles
5. Click **Publish** to make it live
6. Visit `https://coinorchronicles.com/chronicle.php` to see it

---

## Step 8 — Configure Email (for newsletters)

1. Go to **hPanel → Emails → Email Accounts**
2. Create `noreply@coinorchronicles.com`
3. Hostinger's PHP `mail()` function will use this automatically

For better deliverability later, consider adding Brevo (free plan) or Mailgun and swapping `mail()` in `admin/send-newsletter.php` for their API.

---

## File Structure on the Server

```
/home/YOUR_USER/
├── public_html/          ← everything in website/ goes here
│   ├── index.html
│   ├── the-fellowship.php
│   ├── chronicle.php
│   ├── story.php
│   ├── lore.html
│   ├── 404.php
│   ├── .htaccess
│   ├── admin/
│   │   ├── index.php
│   │   ├── run-pipeline.php
│   │   └── send-newsletter.php
│   ├── api/
│   │   └── subscribe.php
│   ├── assets/css/main.css
│   └── includes/
│       ├── config.php
│       ├── config.local.php    ← YOU create this (not in git)
│       └── db.php
├── python_scripts/
│   ├── pipeline.py
│   ├── config.py
│   ├── config.local.py         ← YOU create this (not in git)
│   └── ...
└── logs/
    └── pipeline.log
```

---

## Phase 2 (When You Get the VPS)

Phase 2 adds video generation for YouTube/TikTok:
- ElevenLabs API for voice narration
- FFmpeg to assemble video with voice + visuals
- Auto-upload to YouTube via API
- Auto-upload to TikTok via API

None of this requires code changes to Phase 1 — the VPS will just read chronicles from the same database.
