# Coinor Chronicles

> *AI-generated crypto-fantasy media empire.*
> Cryptocurrency prices told as a Lord of the Rings epic saga — every 6 hours, forever.

---

## What this is

**Coinor Chronicles** maps 21 crypto coins to LOTR characters. Price moves become battles, milestones become Gates, and the 10 Heavens between each Gate mark the journey's progress. No mention of "crypto", "price", or "market" — only provisions, gold units, the road, and the siege.

| Character | Coin | Clan |
|-----------|------|------|
| Aragorn | BTC | Ancients |
| Gandalf | ETH | Ancients |
| Legolas | SOL | Swift |
| Boromir | XRP | Ancients |
| Samwise | LTC | Ancients |
| Elrond | BNB | Ancients |
| Merry | SHIB | Meme Lords |
| Pippin | DOGE | Meme Lords |
| Tom Bombadil | PEPE | Meme Lords |
| Frodo | NOT | Meme Lords |
| Bilbo | BOME | Meme Lords |
| Gollum | BONK | Meme Lords |
| Theoden | FLOKI | Meme Lords |
| Treebeard | ADA | Builders |
| Gimli | SUI | Builders |
| Galadriel | JASMY | Builders |
| Eowyn | AVAX | Swift |
| Faramir | XLM | Swift |
| Saruman | TRX | Swift |
| Eomer | TON | Swift |
| Denethor | UNI | Builders |

---

## Tech stack

| Layer | Choice |
|-------|--------|
| Frontend | Pure HTML/CSS/JS (no framework) |
| Backend | PHP 8+ / MySQL on Hostinger VPS |
| Animations | GSAP 3.12.5 |
| AI pipeline | Python 3.11+ + Anthropic API |
| Price data | CoinGecko free API |

---

## Directory layout

```
coinor-chronicles/
├── website/                 ← Deploy this folder to public_html/
│   ├── index.html           ← Landing page
│   ├── chronicle.php        ← Episode archive (paginated)
│   ├── story.php            ← Single episode reader
│   ├── the-fellowship.php   ← All 21 companions
│   ├── lore.html            ← World bible & cosmology
│   ├── 404.php              ← Custom error page
│   ├── schema.sql           ← Run once on MySQL
│   ├── .htaccess            ← Security + rewrites + caching
│   ├── api/
│   │   └── subscribe.php    ← Email subscribe endpoint
│   ├── assets/
│   │   ├── css/main.css
│   │   └── images/          ← Add og-card.jpg here (1200×630)
│   └── includes/
│       ├── config.php       ← Env-based config
│       ├── db.php           ← PDO helpers
│       ├── header.php       ← Shared HTML head + nav
│       └── footer.php       ← Shared footer + subscribe section
├── python_scripts/          ← AI pipeline (runs on VPS via cron)
│   ├── pipeline.py          ← Main orchestrator — run this
│   ├── config.py            ← Reads env vars
│   ├── fetch_prices.py      ← CoinGecko fetcher
│   ├── heavens.py           ← Gate/Heaven calculator
│   ├── generate_chronicle.py← Claude API story generator
│   ├── db.py                ← MySQL helpers
│   └── requirements.txt
├── docs/                    ← Lore documents (read by AI pipeline)
│   ├── WORLD_BIBLE.md
│   ├── CHARACTER_VOICES.md
│   ├── COSMOLOGY.md
│   └── EXTERNAL_REALMS.md
└── prompts/                 ← Prompt templates
    ├── script_generator.md
    ├── review_agent.md
    └── celebration_episode.md
```

---

## Hostinger VPS — first deployment

### 1. Upload website files

Upload the contents of `website/` to your `public_html/` directory via SFTP or hPanel File Manager.

### 2. Create the MySQL database

In hPanel → Databases → MySQL Databases:
1. Create database: `coinor_chronicles`
2. Create user and assign full privileges
3. Import `website/schema.sql` via phpMyAdmin

### 3. Configure PHP secrets

Create `website/includes/config.local.php` on the server (never committed — blocked by `.gitignore` and `.htaccess`):

```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'coinor_chronicles');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('CLAUDE_API_KEY', 'sk-ant-...');
define('SITE_URL', 'https://coinorchronicles.com');
```

### 4. Install Python pipeline

SSH into the VPS, then:

```bash
cd /home/YOUR_USER/coinor-chronicles
pip3 install -r python_scripts/requirements.txt
```

Create a `.env` file in the repo root (gitignored):

```
ANTHROPIC_API_KEY=sk-ant-...
DB_HOST=localhost
DB_NAME=coinor_chronicles
DB_USER=your_db_user
DB_PASS=your_db_password
```

Test a single pipeline run:

```bash
python3 python_scripts/pipeline.py
```

### 5. Set up the cron job

In hPanel → Advanced → Cron Jobs, add:

```
0 0,6,12,18 * * *   cd /home/YOUR_USER/coinor-chronicles && /usr/bin/python3 python_scripts/pipeline.py >> logs/pipeline.log 2>&1
```

Create the logs directory first:

```bash
mkdir -p logs
```

### 6. DNS + SSL

In hPanel → DNS Zone, point `coinorchronicles.com` A record to your VPS IP.
Enable the free SSL in hPanel → SSL. The `.htaccess` handles www → non-www and HTTP → HTTPS automatically.

---

## Local development

```bash
git clone https://github.com/GbadeboMotunrayo/coinor-chronicles.git
cd coinor-chronicles

# PHP config for local DB
cp website/includes/config.php website/includes/config.local.php
# Edit config.local.php with your local credentials

# Python pipeline
cd python_scripts
pip install -r requirements.txt
python pipeline.py
```

---

## Creator overrides

To steer the next episode, insert a row into `creator_control`:

```sql
INSERT INTO creator_control (target_clan, override_text, priority)
VALUES ('meme_lords', 'Gollum must confront Frodo about the Precious at the summit of the Third Heaven.', 10);
```

The pipeline picks it up on the next run and marks it used.

---

## License

Story content is original creative work. Code is MIT.
