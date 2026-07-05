<?php
// ═══════════════════════════════════════════════════════════
// COINOR CHRONICLES — CONFIGURATION
// Copy this file to config.local.php and fill in real values.
// config.local.php is gitignored and loaded first, so anything
// it defines wins over the defaults below.
// ═══════════════════════════════════════════════════════════

if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

defined('DB_HOST')    || define('DB_HOST',    getenv('DB_HOST') ?: 'localhost');
defined('DB_NAME')    || define('DB_NAME',    getenv('DB_NAME') ?: 'coinor_chronicles');
defined('DB_USER')    || define('DB_USER',    getenv('DB_USER') ?: 'coinor_user');
defined('DB_PASS')    || define('DB_PASS',    getenv('DB_PASS') ?: '');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');

defined('SITE_URL')     || define('SITE_URL', rtrim(getenv('SITE_URL') ?: 'https://coinorchronicles.com', '/'));
defined('SITE_NAME')    || define('SITE_NAME', 'Coinor Chronicles');
defined('SITE_TAGLINE') || define('SITE_TAGLINE', 'The Daily Chronicle of the Realm of Coinor');

defined('CLAUDE_API_KEY') || define('CLAUDE_API_KEY', getenv('CLAUDE_API_KEY') ?: '');
defined('COINGECKO_API')  || define('COINGECKO_API', 'https://api.coingecko.com/api/v3');

// Chronicles generated every N seconds (6 hours)
defined('CHRONICLE_INTERVAL') || define('CHRONICLE_INTERVAL', 21600);

// Admin panel password — set ADMIN_PASSWORD env var or define ADMIN_PASS in
// config.local.php. May be plain text or (better) a password_hash() value:
//   php -r "echo password_hash('yourpass', PASSWORD_DEFAULT);"
// There is intentionally NO default — the admin panel refuses to log anyone
// in until this is set.
defined('ADMIN_PASS') || define('ADMIN_PASS', getenv('ADMIN_PASSWORD') ?: '');

require_once __DIR__ . '/security.php';
