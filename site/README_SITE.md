# The Book of Meme: coinorchonicles.space

A static site (no server, no database). It reads a feed of episodes that an
autonomous engine on the VPS writes. **Nobody has to touch it for episodes to appear.**
Live: https://coinorchonicles.space  ·  Built 15 Oct 2026.

## How it works (no human in the loop)

```
n8n schedule 00:00, 06:00, 12:00 and 18:00 (Africa/Lagos)
   -> SSH to VPS: python3 /usr/local/lib/coinor/coinor.py run
        - fetches the day's market facts, picks the clan, writes the episode
        - deterministic safety checks in code (banned words, real names, length, lore rules)
        - an advisory AI review (never blocks, only noted in the log)
        - if it passes: writes /var/www/coinor/feed/episodes.json, then state
        - Telegram "Chronicle published" notice to the Chairman
   -> nginx serves the feed at https://coinor.2-24-128-252.sslip.io/feed/episodes.json (GET only, CORS for this site)
   -> this site fetches the feed on load and every 5 minutes (cached copy if the feed is down)
```

**Rate:** at most 4 episodes a day (cap in code, changed from 2 on 15 Oct 2026), at least 4 hours apart, and none when the realm is flat.
It is a script on a schedule, not an AI agent that decides things; the AI only writes the text.

## Kill switch and removal (on the VPS)

| Do this | Command |
|---|---|
| Stop all publishing | `sudo touch /etc/coinor/PAUSE` (or `coinor.py pause on`) |
| Resume | `rm /etc/coinor/PAUSE` (or `coinor.py pause off`) |
| Take an episode down | `python3 /usr/local/lib/coinor/coinor.py unpublish N` |
| See state | `python3 /usr/local/lib/coinor/coinor.py status` |
| Dry run (writes nothing) | `python3 /usr/local/lib/coinor/coinor.py run --dry-run` |

Logs: `/var/log/coinor/run.log`. Failures send a Telegram alert (n8n error workflow and the 15-minute watchdog,
which also alerts if the engine has not run for 14 hours, or the feed or this site is unreachable).

## Files

| File | Public? | Purpose |
|---|---|---|
| `index.html`, `styles.css`, `app.js` | yes | the home page (aged-scroll design and map; text-only DOM insertion) |
| `lib.php`, `episode.php`, `clan.php`, `realm.php`, `about.php`, `sitemap.php`, `.htaccess`, `favicon.svg` | yes (`lib.php` blocked from the web) | **SEO pages**: real URLs and real HTML for every episode (`/episode/N`), clan (`/clan/KEY`), `/realm`, `/about` and `/sitemap.xml` |
| `router_test.php` | **no** | local test helper for `php -S` |
| `episodes.json` | yes | fallback copy, empty; the live feed is on the VPS |
| `robots.txt` | yes | allow search engines |
| `episodes.sample.json` | **no, laptop only** | unapproved sample, visible locally with `?preview=1` |
| `publish_episode.py` | **no** | old manual publisher, kept for emergencies |

## SEO (added 15 Oct 2026)

- Each episode has its own URL, `<title>`, description (first lines of the story), canonical link, Open Graph tags and `Article` structured data. The pages are PHP on the shared host: they fetch the public feed from the VPS, cache it for 5 minutes, and keep serving the last copy if the VPS is down. New episodes get a page and a sitemap entry without anyone touching the site.
- `robots.txt` points to `/sitemap.xml`. **One step is the owner's**: add the site in Google Search Console and submit the sitemap. That cannot be automated here.
- Keywords belong in page titles, descriptions and the realm and clan pages. They never go inside an episode: the story rules (no real coins or places, no finance words) are unchanged.
- Limit: the home page (`index.html`) builds its list in the browser. Search engines that run JavaScript see it, and the sitemap lists every episode regardless.

Deploy: upload the changed files to `public_html` (Hostinger TUS upload). `episodes.json` need not change.

## Honest-by-design

- Footer and About say plainly: fiction, not financial advice, written by AI and published automatically after checks; a person can remove any episode.
- The earlier "human approves every publish" rule was lifted by the Chairman's explicit instruction for this project, and replaced by the in-code checks above plus the kill switch and alerts.
- Episode text is inserted as plain text, never HTML. No analytics, cookies or trackers.

## Not built yet

Voice and video, a mailing list, an RSS feed, a proper domain (`coinorchronicles.com` is not registered to you; the site uses `coinorchonicles.space`).
