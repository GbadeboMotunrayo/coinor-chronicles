"""
Database layer — MySQL via mysql-connector-python.
All functions accept a live connection; callers manage the connection lifecycle.
"""
import re
import mysql.connector
from config import DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS


def connect() -> mysql.connector.MySQLConnection:
    return mysql.connector.connect(
        host=DB_HOST, port=DB_PORT,
        database=DB_NAME, user=DB_USER, password=DB_PASS,
        charset='utf8mb4', collation='utf8mb4_unicode_ci',
        autocommit=False,
    )


# ── Clan rotation ──────────────────────────────────────────────────────────────

def get_recent_clan_runs(conn, limit: int = 4) -> list[str]:
    cur = conn.cursor()
    cur.execute('SELECT clan FROM clan_rotation ORDER BY ran_at DESC LIMIT %s', (limit,))
    return [r[0] for r in cur.fetchall()]


def log_clan_rotation(conn, clan: str, story_id: int) -> None:
    cur = conn.cursor()
    cur.execute('INSERT INTO clan_rotation (clan, story_id) VALUES (%s, %s)', (clan, story_id))
    conn.commit()


# ── Story memory ───────────────────────────────────────────────────────────────

def get_clan_memory(conn, clan: str) -> dict:
    cur = conn.cursor(dictionary=True)
    cur.execute('SELECT * FROM story_memory WHERE clan = %s', (clan,))
    row = cur.fetchone()
    return row or {'last_summary': 'The chronicle begins.', 'story_direction': ''}


def update_clan_memory(conn, clan: str, summary: str) -> None:
    cur = conn.cursor()
    cur.execute(
        'UPDATE story_memory SET last_summary = %s, last_updated = NOW() WHERE clan = %s',
        (summary, clan),
    )
    conn.commit()


# ── Creator override ───────────────────────────────────────────────────────────

def get_creator_override(conn, clan: str) -> dict | None:
    cur = conn.cursor(dictionary=True)
    cur.execute(
        'SELECT * FROM creator_control '
        'WHERE is_active = 1 AND (target_clan = %s OR target_clan IS NULL) '
        'ORDER BY priority DESC, created_at ASC LIMIT 1',
        (clan,),
    )
    return cur.fetchone()


def mark_override_used(conn, override_id: int) -> None:
    cur = conn.cursor()
    cur.execute(
        'UPDATE creator_control SET is_active = 0, used_at = NOW() WHERE id = %s',
        (override_id,),
    )
    conn.commit()


# ── Character / Heaven positions ───────────────────────────────────────────────

def get_heaven_positions(conn, coins: list[str]) -> dict[str, dict]:
    if not coins:
        return {}
    placeholders = ','.join(['%s'] * len(coins))
    cur = conn.cursor(dictionary=True)
    cur.execute(
        f'SELECT coin_ticker, character_name, gate_number, heaven_number, current_price '
        f'FROM character_status WHERE coin_ticker IN ({placeholders})',
        coins,
    )
    return {r['coin_ticker']: r for r in cur.fetchall()}


def update_heaven_positions(conn, prices: dict[str, dict], gate_heavens: dict[str, tuple]) -> None:
    cur = conn.cursor()
    for ticker, (gate, heaven) in gate_heavens.items():
        price = prices.get(ticker, {}).get('price')
        if price is None:
            continue
        cur.execute(
            'UPDATE character_status '
            'SET gate_number = %s, heaven_number = %s, current_price = %s, last_updated = NOW() '
            'WHERE coin_ticker = %s',
            (gate, heaven, price, ticker),
        )
    conn.commit()


# ── Milestones ─────────────────────────────────────────────────────────────────

def was_milestone_recorded(conn, coin: str, m_type: str, m_value: int) -> bool:
    cur = conn.cursor()
    cur.execute(
        'SELECT id FROM milestones WHERE coin_ticker = %s AND milestone_type = %s AND milestone_value = %s',
        (coin, m_type, m_value),
    )
    return cur.fetchone() is not None


def record_milestone(conn, coin: str, character: str, m_type: str, m_value: int, price: float) -> None:
    cur = conn.cursor()
    cur.execute(
        'INSERT INTO milestones (coin_ticker, character_name, milestone_type, milestone_value, price_at_crossing) '
        'VALUES (%s, %s, %s, %s, %s)',
        (coin, character, m_type, m_value, price),
    )
    conn.commit()


# ── Stories ────────────────────────────────────────────────────────────────────

def get_total_story_count(conn) -> int:
    cur = conn.cursor()
    cur.execute("SELECT setting_value FROM site_settings WHERE setting_key = 'total_story_count'")
    row = cur.fetchone()
    return int(row[0]) if row else 0


def save_story(conn, data: dict) -> int:
    slug = _slugify(data['title']) + '-' + str(data['episode_number'])
    cur = conn.cursor()
    cur.execute(
        '''INSERT INTO chronicles
           (episode_number, title, excerpt, body, season, clan, character_name,
            slug, published, published_at)
           VALUES (%s, %s, %s, %s, %s, %s, %s, %s, 1, NOW())''',
        (
            data['episode_number'],
            data['title'],
            data['excerpt'],
            data['body'],
            data['season'],
            data['clan_name'],
            data.get('character_name', 'Aragorn'),
            slug,
        ),
    )
    conn.commit()
    return cur.lastrowid


# ── Site settings ──────────────────────────────────────────────────────────────

def update_setting(conn, key: str, value) -> None:
    cur = conn.cursor()
    cur.execute(
        'INSERT INTO site_settings (setting_key, setting_value) VALUES (%s, %s) '
        'ON DUPLICATE KEY UPDATE setting_value = %s',
        (key, str(value), str(value)),
    )
    conn.commit()


# ── Pipeline log ───────────────────────────────────────────────────────────────

def log_pipeline(conn, clan: str | None, status: str, story_id: int | None,
                 error: str | None, duration_ms: int = 0) -> None:
    cur = conn.cursor()
    cur.execute(
        'INSERT INTO pipeline_logs (clan, status, story_id, error_msg, duration_ms) '
        'VALUES (%s, %s, %s, %s, %s)',
        (clan, status, story_id, error, duration_ms),
    )
    conn.commit()


# ── Helpers ────────────────────────────────────────────────────────────────────

def _slugify(text: str) -> str:
    text = text.lower()
    text = re.sub(r'[^\w\s-]', '', text)
    text = re.sub(r'[\s_-]+', '-', text)
    return text.strip('-')[:180]
