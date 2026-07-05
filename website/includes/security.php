<?php
/**
 * Coinor Chronicles — Security helpers
 * Session hardening, CSRF tokens, admin auth, cron key derivation.
 */

/**
 * Start a session with hardened cookie settings.
 * Call before any output on pages that need a session.
 */
function secure_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('cc_session');
    session_start();
}

/** True when the admin password has been changed from the shipped defaults. */
function admin_password_is_configured(): bool {
    return defined('ADMIN_PASS')
        && ADMIN_PASS !== ''
        && ADMIN_PASS !== 'changeme123';
}

/**
 * Verify a login attempt. Supports both a plain passphrase and a
 * password_hash() value (set ADMIN_PASS to the output of
 * `php -r "echo password_hash('yourpass', PASSWORD_DEFAULT);"` for the latter).
 */
function admin_password_verify(string $attempt): bool {
    if (!admin_password_is_configured()) {
        return false;
    }
    if (str_starts_with(ADMIN_PASS, '$2y$') || str_starts_with(ADMIN_PASS, '$argon2')) {
        return password_verify($attempt, ADMIN_PASS);
    }
    return hash_equals(ADMIN_PASS, $attempt);
}

/**
 * Secret key for cron endpoints (pipeline trigger, newsletter).
 * Derived from the admin password; 32 hex chars.
 */
function cron_key(): string {
    return substr(hash('sha256', 'cc-cron:' . ADMIN_PASS), 0, 32);
}

/** Require a valid ?key= on a cron endpoint, or die with 403. */
function require_cron_key(): void {
    if (!admin_password_is_configured()) {
        http_response_code(503);
        exit('Admin password not configured. Set ADMIN_PASSWORD before using cron endpoints.');
    }
    $provided = (string)($_GET['key'] ?? '');
    if (!hash_equals(cron_key(), $provided)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

/** Get (creating if needed) the CSRF token for this session. */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input carrying the CSRF token — echo inside every <form method="POST">. */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '"/>';
}

/** True when the submitted CSRF token matches the session token. */
function csrf_check(): bool {
    $sent = (string)($_POST['csrf_token'] ?? '');
    return $sent !== ''
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $sent);
}

/**
 * Simple login throttle: after 5 failed attempts, force a growing wait.
 * Session-scoped — good enough to stop casual brute force on shared hosting.
 */
function login_throttle_seconds_left(): int {
    $fails = (int)($_SESSION['login_fails'] ?? 0);
    if ($fails < 5) {
        return 0;
    }
    $wait  = min(300, 2 ** ($fails - 4) * 15); // 30s, 60s, 120s, 240s, capped 5min
    $since = time() - (int)($_SESSION['login_last_fail'] ?? 0);
    return max(0, $wait - $since);
}

function login_record_failure(): void {
    $_SESSION['login_fails']     = (int)($_SESSION['login_fails'] ?? 0) + 1;
    $_SESSION['login_last_fail'] = time();
}

function login_record_success(): void {
    unset($_SESSION['login_fails'], $_SESSION['login_last_fail']);
    session_regenerate_id(true);
}

/**
 * Sanitize AI/DB-sourced story HTML for output.
 * Allows narrative markup only — strips scripts, event handlers, and iframes.
 */
function sanitize_story_html(string $html): string {
    $html = strip_tags($html, '<p><em><strong><i><b><br><blockquote><h2><h3><h4><ul><ol><li><hr>');
    // Remove any surviving event-handler or javascript: attributes.
    $html = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace('/\s+(href|src)\s*=\s*("javascript:[^"]*"|\'javascript:[^\']*\')/i', '', $html);
    return $html;
}
