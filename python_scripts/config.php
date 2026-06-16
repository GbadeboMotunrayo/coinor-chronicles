<?php
/**
 * CoinorChronicles — Central Configuration
 * ⚠️ Never commit this file to GitHub. Add to .gitignore.
 */

// ─── DATABASE ───────────────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'coinor_db');        // Your Hostinger MySQL database name
define('DB_USER', 'coinor_user');      // Your Hostinger MySQL username
define('DB_PASS', 'YOUR_DB_PASSWORD'); // Your Hostinger MySQL password
define('DB_CHARSET', 'utf8mb4');

// ─── API KEYS ────────────────────────────────────────────────────────────────
define('ANTHROPIC_API_KEY', 'sk-ant-YOUR_KEY_HERE');    // console.anthropic.com
define('OPENAI_API_KEY', 'sk-YOUR_OPENAI_KEY_HERE');    // platform.openai.com (optional — for Whisper)
define('ELEVENLABS_API_KEY', 'YOUR_ELEVENLABS_KEY');    // elevenlabs.io (Phase 2)
define('COINGECKO_API_KEY', '');                         // Free tier — leave empty

// ─── ELEVENLABS VOICE ───────────────────────────────────────────────────────
define('ARAGORN_VOICE_ID', 'YOUR_ARAGORN_VOICE_ID');   // Set after ElevenLabs custom voice is made

// ─── GITHUB STYLE GUIDE ─────────────────────────────────────────────────────
// Raw GitHub URLs for the style guide docs (fetched fresh each run)
define('GITHUB_WORLD_BIBLE_URL', 'https://raw.githubusercontent.com/YOUR_USERNAME/coinor-chronicles/main/docs/WORLD_BIBLE.md');
define('GITHUB_CHARACTER_VOICES_URL', 'https://raw.githubusercontent.com/YOUR_USERNAME/coinor-chronicles/main/docs/CHARACTER_VOICES.md');
define('GITHUB_EXTERNAL_REALMS_URL', 'https://raw.githubusercontent.com/YOUR_USERNAME/coinor-chronicles/main/docs/EXTERNAL_REALMS.md');
define('GITHUB_COSMOLOGY_URL', 'https://raw.githubusercontent.com/YOUR_USERNAME/coinor-chronicles/main/docs/COSMOLOGY.md');
define('GITHUB_SCRIPT_PROMPT_URL', 'https://raw.githubusercontent.com/YOUR_USERNAME/coinor-chronicles/main/prompts/script_generator.md');
define('GITHUB_REVIEW_PROMPT_URL', 'https://raw.githubusercontent.com/YOUR_USERNAME/coinor-chronicles/main/prompts/review_agent.md');

// ─── SITE SETTINGS ──────────────────────────────────────────────────────────
define('SITE_URL', 'https://coinorchronicles.com');
define('SITE_NAME', 'CoinorChronicles');
define('ADMIN_PASSWORD', 'YOUR_SECURE_ADMIN_PASSWORD'); // Change this immediately

// ─── DONATION WALLETS ───────────────────────────────────────────────────────
define('WALLET_BTC', 'YOUR_BTC_WALLET_ADDRESS');
define('WALLET_ETH', 'YOUR_ETH_WALLET_ADDRESS');
define('WALLET_USDT', 'YOUR_USDT_WALLET_ADDRESS'); // TRC20 or ERC20
define('WALLET_PEPE', 'YOUR_PEPE_WALLET_ADDRESS'); // ERC20
define('WALLET_SHIB', 'YOUR_SHIB_WALLET_ADDRESS'); // ERC20

// ─── STORY SETTINGS ─────────────────────────────────────────────────────────
define('DONATION_TRIGGER_EVERY', 20);    // Show donation appeal every X stories
define('STORIES_PER_DAY', 4);            // Pipeline runs 4x per day
define('MAX_SCRIPT_WORDS', 500);
define('MIN_SCRIPT_WORDS', 250);

// ─── CLAN CONFIGURATION ─────────────────────────────────────────────────────
define('CLANS', json_encode([
    'ancients' => [
        'name' => 'Clan of the Ancients',
        'territory' => 'The Obsidian Citadel',
        'coins' => ['BTC', 'ETH', 'LTC', 'BNB', 'XRP'],
        'characters' => ['Aragorn', 'Gandalf', 'Samwise', 'Elrond', 'Boromir'],
        'aesthetic' => 'dark stone, candlelight, deep drums',
        'bull_mood' => 'The Citadel doors open. Torches burn brighter.',
        'bear_mood' => 'The Citadel gates close. Ancient walls hold.',
    ],
    'swift' => [
        'name' => 'Clan of the Swift',
        'territory' => 'The Electric Plains',
        'coins' => ['SOL', 'TON', 'AVAX', 'XLM', 'TRX'],
        'characters' => ['Legolas', 'Eomer', 'Eowyn', 'Faramir', 'Saruman'],
        'aesthetic' => 'blue lightning, neon edges, always moving',
        'bull_mood' => 'Lightning strikes across the plains.',
        'bear_mood' => 'The riders scatter. Some are caught in the open.',
    ],
    'meme_lords' => [
        'name' => 'Clan of Meme Lords',
        'territory' => 'Kekiston Bazaar',
        'coins' => ['PEPE', 'SHIB', 'DOGE', 'FLOKI', 'NOT', 'BOME', 'BONK'],
        'characters' => ['Tom Bombadil', 'Merry', 'Pippin', 'Theoden', 'Frodo', 'Bilbo', 'Gollum'],
        'aesthetic' => 'chaotic, colourful, fire and laughter',
        'bull_mood' => 'The Bazaar erupts. Fireworks. Dancing.',
        'bear_mood' => 'The Bazaar goes quiet. Half the stalls shuttered.',
    ],
    'builders' => [
        'name' => 'Clan of the Builders',
        'territory' => 'The Forge of Chains',
        'coins' => ['LINK', 'UNI', 'AAVE', 'INJ', 'ADA', 'JASMY'],
        'characters' => ['Gimli', 'Galadriel', 'Treebeard'],
        'aesthetic' => 'deep forest green, iron and fire',
        'bull_mood' => 'New roads completed. New contracts forged.',
        'bear_mood' => 'The Forge fires dim. But the Builders continue.',
    ],
]));

// ─── GOLDEN GATES (current targets) ─────────────────────────────────────────
define('GOLDEN_GATES', json_encode([
    'BTC'   => ['gate1' => 100000,    'gate2' => 1000000,  'gate3' => 10000000],
    'ETH'   => ['gate1' => 10000,     'gate2' => 100000,   'gate3' => 1000000],
    'SOL'   => ['gate1' => 1000,      'gate2' => 10000,    'gate3' => 100000],
    'XRP'   => ['gate1' => 100,       'gate2' => 1000,     'gate3' => 10000],
    'BNB'   => ['gate1' => 10000,     'gate2' => 100000,   'gate3' => 1000000],
    'LTC'   => ['gate1' => 1000,      'gate2' => 10000,    'gate3' => 100000],
    'TON'   => ['gate1' => 10,        'gate2' => 100,      'gate3' => 1000],
    'AVAX'  => ['gate1' => 500,       'gate2' => 5000,     'gate3' => 50000],
    'XLM'   => ['gate1' => 5,         'gate2' => 50,       'gate3' => 500],
    'TRX'   => ['gate1' => 1,         'gate2' => 10,       'gate3' => 100],
    'ADA'   => ['gate1' => 10,        'gate2' => 100,      'gate3' => 1000],
    'JASMY' => ['gate1' => 1,         'gate2' => 10,       'gate3' => 100],
    'LINK'  => ['gate1' => 1000,      'gate2' => 10000,    'gate3' => 100000],
    'UNI'   => ['gate1' => 100,       'gate2' => 1000,     'gate3' => 10000],
    'AAVE'  => ['gate1' => 10000,     'gate2' => 100000,   'gate3' => 1000000],
    'INJ'   => ['gate1' => 1000,      'gate2' => 10000,    'gate3' => 100000],
    'PEPE'  => ['gate1' => 1,         'gate2' => 10,       'gate3' => 100],
    'SHIB'  => ['gate1' => 0.001,     'gate2' => 0.01,     'gate3' => 0.1],
    'DOGE'  => ['gate1' => 10,        'gate2' => 100,      'gate3' => 1000],
    'FLOKI' => ['gate1' => 1,         'gate2' => 10,       'gate3' => 100],
    'NOT'   => ['gate1' => 0.1,       'gate2' => 1,        'gate3' => 10],
    'BOME'  => ['gate1' => 0.1,       'gate2' => 1,        'gate3' => 10],
    'BONK'  => ['gate1' => 1,         'gate2' => 10,       'gate3' => 100],
]));

// ─── AI MODEL SETTINGS ──────────────────────────────────────────────────────
define('STORY_MODEL', 'claude-sonnet-4-20250514');  // Story generation
define('REVIEW_MODEL', 'claude-sonnet-4-20250514'); // Quality gate
define('MAX_TOKENS', 1500);
define('TEMPERATURE', 0.85); // Creative but controlled

// ─── PATHS ───────────────────────────────────────────────────────────────────
define('ROOT_PATH', dirname(__DIR__));
define('STORIES_PATH', ROOT_PATH . '/stories/');
define('LOGS_PATH', ROOT_PATH . '/logs/');

// ─── ERROR REPORTING (disable on production) ─────────────────────────────────
error_reporting(0);
ini_set('display_errors', 0);
