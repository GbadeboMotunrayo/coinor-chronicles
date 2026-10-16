# Daily video pipeline (free tools)

**One vertical YouTube Short a day**, made from the day's published chapter. Runs on the VPS at 19:15 Lagos (after the 18:00 chapter) by `coinor-video.timer`. No AI model writes anything: the narration is cut from the chapter that already passed the rule checks.

```
feed (today's chapter, Lagos date) -> narration (first ~125 words + a closing line)
   -> voice: Piper, en_GB-alan-medium, a little lower and slower      (free, offline)
   -> 5 pictures from the chapter's own scene lines                  (Gemini with character reference sheets when GEMINI_API_KEY exists;
                                                                     else Pollinations, free; mirrored reuse if both are busy)
   -> ffmpeg: slow zooms, burned-in captions, labels, a drone made by ffmpeg, loudness-normalised
   -> /var/www/coinor/video/coinor-DATE-epN.mp4 (+ .json with title, description, tags), served at https://coinor.2-24-128-252.sslip.io/video/
   -> Telegram note to the Chairman with the link
   -> YouTube upload, only once YT_CLIENT_ID / YT_CLIENT_SECRET / YT_REFRESH_TOKEN exist in the env file (starts PRIVATE)
```

Command (on the VPS, as hermes): `coinor_video.py make [--episode N] [--date YYYY-MM-DD] [--force] [--no-upload] [--dry-run]`, `coinor_video.py status`.
No chapter that day (a flat market) = no video. Files older than 14 days are deleted.

## Who does what (every digital staff member)

| Staff | Part in the videos |
|---|---|
| **Spielberg** | **In charge of production**: look, pacing, weekly review, change requests |
| Maya | Accountable owner of the channel; decides what goes public |
| Raven | Picture style, channel art, scene prompts |
| Echo | Spoken words, captions, titles and descriptions |
| Prometheus | Pipeline health: timer, free tools, disk, alerts |
| Athena | Builds and maintains the code and the YouTube upload |
| Beacon | Independent weekly quality check |
| Vault | YouTube credentials and security |
| Victoria | Keeps the stack at zero cost |
| Warren | Decides if a paid upgrade is ever worth it |
| Amina | Weekly video tasks; one line in the Chairman's brief |
| Steward | Ledger of videos made and published |
| MO | Video status in the company brief |

## Known limits
- **YouTube**: videos uploaded through an API project that Google has not audited are locked to private. The Chairman can switch each to public in YouTube Studio, or the project can apply for an audit. The Chairman must do the one-time Google sign-in to create the upload key.
- **Pictures**: the free image service sometimes refuses or slows down; the pipeline retries, then reuses a good picture mirrored. Quality varies and is not guaranteed.
- **Likeness**: characters are drawn from plain descriptions, not film designs, but the story names are Tolkien's. That is a legal risk the Chairman accepted for the project; the pictures avoid copying any film.
- **Voice**: Piper is a good free voice, not a studio actor.

## Consistent characters (Gemini)
`video/characters.json` is the **character bible**: one fixed written look for each of the 21 characters (our own designs, not film designs) plus the house style line.
When a scene names a character, the pipeline makes that character's **reference sheet** once (kept in `/opt/coinor-video/characters/`), then hands up to two
reference sheets to Gemini with every scene that character appears in, with the instruction to keep the same face, hair, clothes and colours.
To change a character, edit its line in `characters.json` and delete its file in `/opt/coinor-video/characters/` so a new sheet is drawn.
If Gemini refuses twice in a run (quota, outage) the rest of that run falls back to Pollinations. Image model names are in `gemini_image_models`; the free tier decides which work.

## One-time setup the Chairman does
1. **Gemini key** (free, from aistudio.google.com): add it to the server without showing it:
   `ssh mgvps 'read -s -p "Gemini key: " k; echo; echo "GEMINI_API_KEY=$k" | sudo tee -a /home/hermes/.hermes/.env >/dev/null'`
2. **YouTube**: see `get_youtube_token.py` (run on the laptop). Needs a Google Cloud project with YouTube Data API v3 enabled, an OAuth consent screen
   (External, **published to "In production"**; in "Testing" Google expires the key after 7 days) and a **Desktop app** OAuth client. Then send `yt_keys.env` to the server as the script prints.
