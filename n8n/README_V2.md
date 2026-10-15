# CoinorChronicles v2 — Draft for Review

Built 15 Oct 2026. Rebuilt with `python3 build_v2.py` (writes the two JSON files).
v1 (`coinor_chronicles_pipeline.json`) is superseded and was never run.

## What changed from v1, and why

| v1 | v2 |
|---|---|
| Published automatically to YouTube / TikTok | **Nothing publishes.** Each episode is drafted, reviewed by a second model call and sent to the Chairman on Telegram. Posting is by hand (house rule: every publish passes a human gate) |
| MySQL on Hostinger + Airtable | No external database. State lives in the workflow's static data (inside n8n's own DB, so covered by the nightly encrypted backup) |
| Anthropic API (credit exhausted in July) | OpenRouter `openrouter/free` through the existing n8n credential |
| Checked for milestones AFTER writing an episode, then threw the episode away | Checks first; celebration OR story, never both |
| Gates never advanced (stored gate stayed 1) | Gates advance; heavens are detected as upward crossings and never re-celebrated |
| First run would have flooded with false "crossings" | First run records a silent baseline |
| Wallet addresses injected every 20th story | Removed (no wallets; compliance question open) |
| ffmpeg video, ElevenLabs narration, TikTok upload | Removed for now (Phase 0: one episode by hand first) |
| 4 runs a day, 5-6 model calls each | 2 runs a day (06:00, 18:00 Lagos), 2-3 calls each, to stay inside the shared free tier |
| Silent failures | A separate Error Alert workflow messages Telegram when any node fails |

## Lore decisions made (change in `build_v2.py`)

- Characters follow `docs/CHARACTER_VOICES.md`. **Gimli is SUI**, not Chainlink.
- **LINK, UNI, AAVE, INJ are not tracked**: the style guide gives them no character. Assign characters, then add them.
- **SUI gate prices are an assumption** ($10 / $100 / $1,000). Confirm.

## Before activating (n8n runs only through `ssh -N n8n`, then http://localhost:5678)

1. Open **CoinorChronicles - Error Alert** and activate it first. Then open **CoinorChronicles v2 - Draft for Review**.
2. The credentials are already mapped (OpenRouter, Telegram). Check the two Telegram nodes and the three OpenRouter nodes show no red warning.
3. Click **Manual Test**. Manual runs do NOT save state, so this is safe to repeat. Read the draft that arrives on Telegram.
4. Only if you are happy, activate. State starts saving on the first scheduled run.
5. Budget: about 5 model requests a day, from a free tier shared with your agents.

## Not built yet (Phase 2, after one episode has been posted and measured)

Website on `coinorchonicles.space` (owned; the docs say `coinorchronicles.com`, which is not), voice, video, publishing, Creator Override.
