# CoinorChronicles — n8n Pipeline

`coinor_chronicles_pipeline.json` is a single importable n8n workflow that replaces `python_scripts/run_pipeline.php` end to end: price fetch → clan selection → story generation/review → milestone/celebration check → save → voice narration → video render → YouTube/TikTok publish.

## Import

n8n → Workflows → Import from File → select `coinor_chronicles_pipeline.json`.

It has **61 nodes**, grouped into sections A–I with sticky notes explaining each one inline on the canvas. Read the sticky note in the bottom-left corner first — it's the setup checklist.

## Required setup before it will run

1. **Database** — run `python_scripts/database_setup.sql` on the MySQL instance you'll point this at (Hostinger, as originally designed).
2. ~~**Airtable base**~~ — **done.** Created via the Airtable connector: base `CoinorChronicles` (`appnSqAoHqVbGXDtg`) in your "Pepe" workspace, with `Story Memory` (`tblMEtwBNBI9BScWN`, seeded with the 4 clan rows from `database_setup.sql`) and `Creator Control` (`tblVx9yl52YOuSjYZ`, empty — add a row there whenever you want to inject a Creator Override). All 4 Airtable nodes in the JSON already point at these real IDs — nothing to fill in.
3. **Credentials** — create these in n8n and map them when you import (names are just suggestions, n8n will prompt you to assign real credentials to each node that needs one):
   - Anthropic API Key — Header Auth, header `x-api-key`
   - ElevenLabs API Key — Header Auth, header `xi-api-key`
   - Hostinger MySQL — standard MySQL credential
   - Airtable — Personal Access Token
   - YouTube — OAuth2 (YouTube Data API v3, `youtube.upload` scope)
   - TikTok — Header Auth (Bearer token) — see node note, this needs Content Posting API approval from TikTok first
4. **ElevenLabs voice** — replace `YOUR_ARAGORN_VOICE_ID` in the *Generate Aragorn Narration* node's URL.
5. **Donation wallets** — replace the placeholder addresses in *Inject Wallet Addresses*.
6. **ffmpeg** — the *Render Episode Video* node shells out to `ffmpeg`. The default n8n Docker image does not include it — build a custom image with it installed, or swap that node for a rendering API (Shotstack/Creatomate) call.
7. **Background images** — drop one image per clan × market-condition at `/data/coinor/backgrounds/<clan>_<condition>.jpg` (e.g. `ancients_golden_season.jpg`, `meme_lords_dark_siege.jpg`) — 4 clans × 3 conditions = 12 images. Adjust the path in *Select Background & Build Overlay Text* to wherever you actually mount storage in your n8n container.
8. **Error workflow** — this workflow only logs failures that happen *inside* its own logic (the twice-failed-review path). For crashes in any node (API timeout, bad credential, etc.), set a Workflow → Settings → Error Workflow pointing at a small separate logging workflow, so those don't fail silently.

## Known simplifications vs. the PHP reference

- `Upload to TikTok` is a single representative node. TikTok's real Content Posting API is init → PUT video bytes to a returned upload URL → poll status. Fill that in once your TikTok developer app is approved.
- SQL writes use inline-escaped string interpolation (via small `esc()` helpers in the Code nodes before each MySQL node) rather than the MySQL node's native parameter binding, since all input is pipeline-controlled rather than public user input. Fine for this use case; flagged in case you want to harden it later.
- `Execute State Updates` runs several `;`-separated `UPDATE`/`INSERT` statements in one call. If your MySQL credential doesn't have multi-statement execution enabled, split that Code node's output into one `Execute Query` node per statement.
