"""
Calls the Anthropic API to generate a chronicle episode.
Reads the lore docs from the local docs/ folder so no GitHub fetch is needed.
"""
import os
import re
import anthropic
from config import ANTHROPIC_API_KEY, STORY_MODEL, REVIEW_MODEL

_client = anthropic.Anthropic(api_key=ANTHROPIC_API_KEY)
_DOCS_DIR = os.path.join(os.path.dirname(__file__), '..', 'docs')
_PROMPTS_DIR = os.path.join(os.path.dirname(__file__), '..', 'prompts')

_BANNED_WORDS = [
    'crypto', 'cryptocurrency', ' market ', 'percent', ' buy ', ' sell ',
    'chart', 'trading', 'investor', 'token', 'blockchain', 'wallet',
]


def _load_file(path: str) -> str:
    try:
        with open(path, encoding='utf-8') as f:
            return f.read()
    except FileNotFoundError:
        return ''


def _build_system_context() -> str:
    parts = [
        _load_file(os.path.join(_DOCS_DIR, 'WORLD_BIBLE.md')),
        _load_file(os.path.join(_DOCS_DIR, 'CHARACTER_VOICES.md')),
        _load_file(os.path.join(_DOCS_DIR, 'EXTERNAL_REALMS.md')),
        _load_file(os.path.join(_DOCS_DIR, 'COSMOLOGY.md')),
        _load_file(os.path.join(_PROMPTS_DIR, 'script_generator.md')),
    ]
    return '\n\n---\n\n'.join(p for p in parts if p)


def _call_claude(system: str, user: str, model: str, max_tokens: int = 2000) -> str:
    msg = _client.messages.create(
        model=model,
        max_tokens=max_tokens,
        system=system,
        messages=[{'role': 'user', 'content': user}],
    )
    return msg.content[0].text


def generate_episode(
    clan: dict,
    prices: dict[str, dict],
    condition: str,
    memory: dict,
    heaven_positions: dict,
    episode_number: int,
    creator_override: str | None = None,
) -> dict:
    """
    Returns {'title': str, 'body': str, 'excerpt': str}.
    Raises RuntimeError if the story fails review twice.
    """
    system = (
        'You are the official story narrator of CoinorChronicles — '
        'an AI-generated crypto-fantasy media empire where cryptocurrency '
        'prices are told as a Lord of the Rings epic saga. '
        'Never say crypto, price, market, percent, buy, sell, chart, '
        'trading, investor, blockchain, or wallet. '
        'Use only in-world language: provisions, gold units, the road, '
        'the Heavens, the siege, the fellowship.\n\n'
        + _build_system_context()
    )

    condition_names = {
        'golden_season':  'The Golden Season',
        'dark_siege':     'The Dark Siege',
        'waiting_plains': 'The Waiting Plains',
    }

    coin_lines = '\n'.join(
        f"  - {ticker} ({prices[ticker]['character']}): "
        f"{prices[ticker]['gold_units']} gold units {prices[ticker]['direction']} | "
        f"Gate {heaven_positions.get(ticker, {}).get('gate', '?')}, "
        f"Heaven {heaven_positions.get(ticker, {}).get('heaven', '?')}"
        for ticker in clan['coins']
        if ticker in prices
    )

    override_text = creator_override or 'None — generate naturally from market data'

    user_prompt = f"""SELECTED_CLAN: {clan['name']} — {clan['territory']}
MARKET_CONDITION: {condition_names.get(condition, condition)}
EPISODE_NUMBER: {episode_number}

MARKET_DATA (today's journey across the Heavens):
{coin_lines}

LAST_EPISODE_SUMMARY: {memory.get('last_summary', 'The chronicle begins.')}
STORY_DIRECTION: {memory.get('story_direction', '')}

CREATOR_OVERRIDE: {override_text}

Write the full episode now. Begin with the episode title on the first line, then the story body.
"""
    story_raw = _call_claude(system, user_prompt, STORY_MODEL, max_tokens=2400)

    # ── Quality gate ──────────────────────────────────────────────────────────
    review_result = _review_story(story_raw)
    if review_result != 'PASS':
        retry_prompt = user_prompt + (
            f'\n\n## PREVIOUS ATTEMPT FAILED REVIEW:\n{review_result}\n\n'
            'Fix every issue listed above in your next attempt.'
        )
        story_raw = _call_claude(system, retry_prompt, STORY_MODEL, max_tokens=2400)
        review_result = _review_story(story_raw)
        if review_result != 'PASS':
            raise RuntimeError(f'Story failed review twice: {review_result}')

    return _parse_story(story_raw)


def generate_celebration_episode(milestone: dict, prices: dict[str, dict]) -> dict:
    """
    A special episode for Gate or Heaven crossing milestones.
    """
    system = (
        'You are the official story narrator of CoinorChronicles.\n\n'
        + _load_file(os.path.join(_PROMPTS_DIR, 'celebration_episode.md'))
        + '\n\n'
        + _build_system_context()
    )

    ticker  = milestone['coin']
    char    = milestone.get('character', ticker)
    m_type  = milestone['type'].upper()
    m_value = milestone['value']

    user_prompt = (
        f'MILESTONE_TYPE: {m_type}_CROSSING\n'
        f'CHARACTER: {char}\n'
        f'COIN: {ticker}\n'
        f'MILESTONE: {milestone["type"].title()} {m_value}\n'
        f'PRICE_AT_MILESTONE: {prices.get(ticker, {}).get("price", "unknown")}\n\n'
        'Write the full celebration episode now.'
    )
    story_raw = _call_claude(system, user_prompt, STORY_MODEL, max_tokens=2400)
    return _parse_story(story_raw)


def summarise_for_memory(story_body: str) -> str:
    prompt = (
        'Summarise this story in 2 sentences as a memory note for the next episode. '
        'Focus on what happened to the characters and the road ahead:\n\n'
        + story_body
    )
    return _call_claude('You are a concise story summariser.', prompt, REVIEW_MODEL, max_tokens=200)


# ── Internal helpers ───────────────────────────────────────────────────────────

def _review_story(story: str) -> str:
    """Returns 'PASS' or a string describing the failure."""
    lower = story.lower()
    for word in _BANNED_WORDS:
        if word in lower:
            return f"Banned word found: '{word.strip()}'"

    review_prompt = _load_file(os.path.join(_PROMPTS_DIR, 'review_agent.md'))
    if review_prompt:
        result = _call_claude(
            review_prompt,
            f'Review this story:\n\n---\n{story}\n---',
            REVIEW_MODEL,
            max_tokens=400,
        )
        if 'REVIEW RESULT: PASS' in result.upper():
            return 'PASS'
        return result

    return 'PASS'


def _parse_story(raw: str) -> dict:
    lines = raw.strip().splitlines()

    # First non-empty line is the title
    title = ''
    body_start = 0
    for i, line in enumerate(lines):
        stripped = line.strip().lstrip('#').strip().strip('*').strip()
        if stripped:
            title = stripped[:255]
            body_start = i + 1
            break

    body = '\n'.join(lines[body_start:]).strip()

    # Remove optional [SCENE NOTES] section
    if '[SCENE NOTES]' in body:
        body = body[:body.index('[SCENE NOTES]')].strip()

    # Auto-generate excerpt: first 280 chars of body text
    plain = re.sub(r'<[^>]+>', '', body)
    excerpt = plain[:280].rsplit(' ', 1)[0] + '…' if len(plain) > 280 else plain

    return {'title': title, 'body': body, 'excerpt': excerpt}
