"""
Central config — reads from environment variables.
On VPS: set these in /etc/environment or a .env file loaded by the cron command.
"""
import os
from dotenv import load_dotenv

# Load .env if present (dev only — never commit .env)
load_dotenv(dotenv_path=os.path.join(os.path.dirname(__file__), '..', '.env'))

ANTHROPIC_API_KEY = os.environ['ANTHROPIC_API_KEY']
COINGECKO_BASE    = 'https://api.coingecko.com/api/v3'

DB_HOST = os.environ.get('DB_HOST', 'localhost')
DB_PORT = int(os.environ.get('DB_PORT', 3306))
DB_NAME = os.environ['DB_NAME']
DB_USER = os.environ['DB_USER']
DB_PASS = os.environ['DB_PASS']

STORY_MODEL  = os.environ.get('STORY_MODEL',  'claude-sonnet-4-6')
REVIEW_MODEL = os.environ.get('REVIEW_MODEL', 'claude-haiku-4-5-20251001')

# ── Coin universe ──────────────────────────────────────────────────────────────
# CoinGecko ID → ticker symbol
COIN_MAP: dict[str, str] = {
    'bitcoin':              'BTC',
    'ethereum':             'ETH',
    'litecoin':             'LTC',
    'binancecoin':          'BNB',
    'ripple':               'XRP',
    'solana':               'SOL',
    'the-open-network':     'TON',
    'avalanche-2':          'AVAX',
    'stellar':              'XLM',
    'tron':                 'TRX',
    'cardano':              'ADA',
    'jasmycoin':            'JASMY',
    'pepe':                 'PEPE',
    'shiba-inu':            'SHIB',
    'dogecoin':             'DOGE',
    'floki':                'FLOKI',
    'notcoin':              'NOT',
    'book-of-meme':         'BOME',
    'bonk':                 'BONK',
    'sui':                  'SUI',
    'uniswap':              'UNI',
}

# ticker → character name
CHARACTER_MAP: dict[str, str] = {
    'BTC':   'Aragorn',
    'ETH':   'Gandalf',
    'SOL':   'Legolas',
    'XRP':   'Boromir',
    'LTC':   'Samwise',
    'BNB':   'Elrond',
    'SHIB':  'Merry',
    'DOGE':  'Pippin',
    'PEPE':  'Tom Bombadil',
    'NOT':   'Frodo',
    'BOME':  'Bilbo',
    'BONK':  'Gollum',
    'FLOKI': 'Theoden',
    'ADA':   'Treebeard',
    'SUI':   'Gimli',
    'JASMY': 'Galadriel',
    'AVAX':  'Eowyn',
    'XLM':   'Faramir',
    'TRX':   'Saruman',
    'TON':   'Eomer',
    'UNI':   'Denethor',
}

CLANS: dict[str, dict] = {
    'ancients': {
        'name':      'The Ancients',
        'territory': 'The Eternal Peaks',
        'coins':     ['BTC', 'ETH', 'LTC', 'BNB', 'XRP'],
    },
    'swift': {
        'name':      'The Swift',
        'territory': 'The Shifting Currents',
        'coins':     ['SOL', 'TON', 'AVAX', 'XLM', 'TRX'],
    },
    'meme_lords': {
        'name':      'The Meme Lords',
        'territory': 'The Laughing Wastes',
        'coins':     ['PEPE', 'SHIB', 'DOGE', 'FLOKI', 'NOT', 'BOME', 'BONK'],
    },
    'builders': {
        'name':      'The Builders',
        'territory': 'The Forges of Tomorrow',
        'coins':     ['ADA', 'SUI', 'JASMY', 'UNI'],
    },
}

# ── Golden Gates ───────────────────────────────────────────────────────────────
# Each list: [gate1_price, gate2_price, ...]
GOLDEN_GATES: dict[str, list[float]] = {
    'BTC':   [10_000, 20_000, 30_000, 50_000, 75_000, 100_000, 150_000, 200_000, 300_000, 500_000],
    'ETH':   [500,    1_000,  2_000,  3_000,  5_000,  7_500,   10_000,  15_000,  20_000,  30_000],
    'SOL':   [10,     25,     50,     100,    150,    200,     300,     400,     500,     1_000],
    'XRP':   [0.25,   0.50,   1.0,    2.0,    3.0,    5.0,     7.5,     10.0,    15.0,    25.0],
    'LTC':   [25,     50,     100,    150,    200,    300,     400,     500,     750,     1_000],
    'BNB':   [10,     50,     100,    200,    300,    500,     750,     1_000,   1_500,   2_000],
    'TON':   [0.50,   1.0,    2.0,    3.0,    5.0,    7.5,     10.0,    15.0,    20.0,    30.0],
    'AVAX':  [2.0,    5.0,    10.0,   20.0,   30.0,   50.0,    75.0,    100.0,   150.0,   200.0],
    'XLM':   [0.05,   0.10,   0.20,   0.30,   0.50,   0.75,    1.0,     1.5,     2.0,     3.0],
    'TRX':   [0.01,   0.02,   0.05,   0.10,   0.15,   0.20,    0.30,    0.40,    0.50,    1.0],
    'ADA':   [0.10,   0.25,   0.50,   1.0,    1.5,    2.0,     3.0,     4.0,     5.0,     10.0],
    'SUI':   [0.25,   0.50,   1.0,    2.0,    3.0,    5.0,     7.5,     10.0,    15.0,    20.0],
    'JASMY': [0.001,  0.005,  0.01,   0.02,   0.05,   0.10,    0.20,    0.30,    0.50,    1.0],
    'PEPE':  [1e-7,   5e-7,   1e-6,   5e-6,   1e-5,   5e-5,    1e-4,    5e-4,    1e-3,    0.01],
    'SHIB':  [1e-6,   5e-6,   1e-5,   5e-5,   1e-4,   5e-4,    1e-3,    5e-3,    0.01,    0.10],
    'DOGE':  [0.01,   0.05,   0.10,   0.20,   0.30,   0.50,    0.75,    1.0,     1.5,     2.0],
    'FLOKI': [1e-5,   5e-5,   1e-4,   5e-4,   1e-3,   5e-3,    0.01,    0.05,    0.10,    0.50],
    'NOT':   [0.001,  0.005,  0.01,   0.02,   0.05,   0.10,    0.20,    0.30,    0.50,    1.0],
    'BOME':  [0.001,  0.005,  0.01,   0.02,   0.05,   0.10,    0.20,    0.30,    0.50,    1.0],
    'BONK':  [1e-6,   5e-6,   1e-5,   5e-5,   1e-4,   5e-4,    1e-3,    5e-3,    0.01,    0.10],
    'UNI':   [1.0,    2.0,    5.0,    10.0,   15.0,   20.0,    30.0,    40.0,    50.0,    100.0],
}
