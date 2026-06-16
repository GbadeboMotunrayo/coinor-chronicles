#!/usr/bin/env python3
"""
CoinorChronicles — Main Pipeline
Runs every 6 hours via Hostinger cron job.

Cron command (hPanel → Advanced → Cron Jobs):
  0 0,6,12,18 * * *  cd /home/YOUR_USER/coinor-chronicles && \
    /usr/bin/python3 python_scripts/pipeline.py >> logs/pipeline.log 2>&1

Flow:
  1. Fetch live prices from CoinGecko
  2. Determine market condition
  3. Select which clan runs this episode
  4. Pull clan memory + creator override from DB
  5. Calculate Heaven/Gate positions for each coin
  6. Generate story via Claude API (with review gate)
  7. Check for milestone crossings → celebration episode if needed
  8. Save chronicle to DB
  9. Update memory, Heaven positions, settings
"""
import sys
import time
from datetime import datetime

from config import CLANS
from fetch_prices import fetch_prices, determine_market_condition
from heavens import get_gate_and_heaven
from generate_chronicle import generate_episode, generate_celebration_episode, summarise_for_memory
import db


def _log(msg: str) -> None:
    print(f'[{datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")} UTC] {msg}', flush=True)


def _select_clan(conn, prices: dict) -> str:
    recent = db.get_recent_clan_runs(conn, limit=4)
    all_clans = list(CLANS.keys())

    # If any coin moved >12 % in 24 h, that clan leads the episode
    for clan_key, clan_data in CLANS.items():
        for ticker in clan_data['coins']:
            if abs(prices.get(ticker, {}).get('change_24h', 0)) > 12:
                return clan_key

    # Round-robin: pick the clan that has gone longest without running
    for clan in all_clans:
        if clan not in recent:
            return clan

    # All ran recently — pick least recent
    for clan in reversed(recent):
        if clan in all_clans:
            return clan

    return all_clans[0]


def _check_milestones(conn, prices: dict, clan_coins: list[str]) -> list[dict]:
    milestones: list[dict] = []
    for ticker in clan_coins:
        if ticker not in prices:
            continue
        price = prices[ticker]['price']
        gate, heaven = get_gate_and_heaven(ticker, price)

        # Check gate crossing
        if not db.was_milestone_recorded(conn, ticker, 'gate', gate):
            milestones.append({
                'coin': ticker,
                'character': prices[ticker]['character'],
                'type': 'gate',
                'value': gate,
                'price': price,
            })

        # Check heaven crossing
        if not db.was_milestone_recorded(conn, ticker, 'heaven', heaven):
            milestones.append({
                'coin': ticker,
                'character': prices[ticker]['character'],
                'type': 'heaven',
                'value': heaven,
                'price': price,
            })

    return milestones[:1]  # handle at most one milestone per run


def run() -> None:
    t_start = time.monotonic()
    _log('CoinorChronicles Pipeline starting...')

    conn = db.connect()
    clan_key = None
    story_id = None

    try:
        # ── 1. Prices ──────────────────────────────────────────────────────────
        _log('Fetching prices from CoinGecko...')
        prices = fetch_prices()
        _log(f'Got prices for {len(prices)} coins.')

        # ── 2. Market condition ────────────────────────────────────────────────
        condition = determine_market_condition(prices)
        _log(f'Market condition: {condition}')

        if condition == 'waiting_plains':
            _log('Waiting Plains — flat market, skipping episode.')
            db.log_pipeline(conn, None, 'skipped', None, 'Waiting Plains', 0)
            return

        # ── 3. Clan selection ──────────────────────────────────────────────────
        clan_key = _select_clan(conn, prices)
        clan = CLANS[clan_key]
        _log(f'Selected clan: {clan["name"]}')

        # ── 4. Memory + override ───────────────────────────────────────────────
        memory = db.get_clan_memory(conn, clan_key)
        override_row = db.get_creator_override(conn, clan_key)
        override_text = override_row['override_text'] if override_row else None

        episode_number = db.get_total_story_count(conn) + 1
        _log(f'Episode number: {episode_number}')

        # ── 5. Heaven positions ────────────────────────────────────────────────
        heaven_positions: dict[str, dict] = {}
        gate_heavens: dict[str, tuple] = {}
        for ticker in clan['coins']:
            if ticker in prices:
                g, h = get_gate_and_heaven(ticker, prices[ticker]['price'])
                heaven_positions[ticker] = {'gate': g, 'heaven': h}
                gate_heavens[ticker] = (g, h)

        # ── 6. Milestone check ─────────────────────────────────────────────────
        milestones = _check_milestones(conn, prices, clan['coins'])
        is_celebration = bool(milestones)

        # ── 7. Generate story ──────────────────────────────────────────────────
        _log('Generating story...')
        if is_celebration:
            _log(f'Milestone detected: {milestones[0]}')
            parsed = generate_celebration_episode(milestones[0], prices)
        else:
            parsed = generate_episode(
                clan=clan,
                prices=prices,
                condition=condition,
                memory=memory,
                heaven_positions=heaven_positions,
                episode_number=episode_number,
                creator_override=override_text,
            )

        word_count = len(parsed['body'].split())
        _log(f'Story generated: "{parsed["title"]}" ({word_count} words)')

        # ── 8. Save to DB ──────────────────────────────────────────────────────
        season_map = {'golden_season': 'golden', 'dark_siege': 'dark', 'waiting_plains': 'flat'}
        story_data = {
            'episode_number': episode_number,
            'title':          parsed['title'],
            'excerpt':        parsed['excerpt'],
            'body':           parsed['body'],
            'season':         season_map.get(condition, 'golden'),
            'clan_name':      clan['name'],
            'character_name': prices.get(clan['coins'][0], {}).get('character', 'Aragorn'),
        }
        story_id = db.save_story(conn, story_data)
        _log(f'Story saved — DB id: {story_id}')

        # ── 9. Record milestone if any ─────────────────────────────────────────
        if is_celebration:
            m = milestones[0]
            db.record_milestone(conn, m['coin'], m['character'], m['type'], m['value'], m['price'])

        # ── 10. Update memory + positions ──────────────────────────────────────
        summary = summarise_for_memory(parsed['body'])
        db.update_clan_memory(conn, clan_key, summary)
        db.update_heaven_positions(conn, prices, gate_heavens)
        db.update_setting(conn, 'total_story_count', episode_number)
        db.update_setting(conn, 'last_run', datetime.utcnow().isoformat())
        db.log_clan_rotation(conn, clan_key, story_id)

        if override_row:
            db.mark_override_used(conn, override_row['id'])

        duration_ms = int((time.monotonic() - t_start) * 1000)
        db.log_pipeline(conn, clan_key, 'success', story_id, None, duration_ms)

        _log(f'Done. Episode {episode_number} live. Clan: {clan["name"]}. Duration: {duration_ms}ms.')
        _log('The fellowship endures.')

    except Exception as exc:
        duration_ms = int((time.monotonic() - t_start) * 1000)
        _log(f'ERROR: {exc}')
        try:
            db.log_pipeline(conn, clan_key, 'failed', story_id, str(exc), duration_ms)
        except Exception:
            pass
        sys.exit(1)

    finally:
        conn.close()


if __name__ == '__main__':
    run()
