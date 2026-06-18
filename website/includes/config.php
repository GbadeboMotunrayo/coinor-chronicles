<?php
// ═══════════════════════════════════════════════════════════
// COINOR CHRONICLES — CONFIGURATION
// Copy this file to config.local.php and fill in real values.
// config.local.php is gitignored.
// ═══════════════════════════════════════════════════════════

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'coinor_chronicles');
define('DB_USER', getenv('DB_USER') ?: 'coinor_user');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

define('SITE_URL',   rtrim(getenv('SITE_URL') ?: 'https://coinorchronicles.com', '/'));
define('SITE_NAME',  'Coinor Chronicles');
define('SITE_TAGLINE', 'The Daily Chronicle of the Realm of Coinor');

define('CLAUDE_API_KEY', getenv('CLAUDE_API_KEY') ?: '');
define('COINGECKO_API',  'https://api.coingecko.com/api/v3');

// Chronicles generated every N seconds (6 hours)
define('CHRONICLE_INTERVAL', 21600);

// Admin panel password — override in config.local.php
define('ADMIN_PASS', getenv('ADMIN_PASSWORD') ?: 'changeme123');

// Load local overrides if present
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}
