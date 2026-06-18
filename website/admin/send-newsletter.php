<?php
/**
 * Coinor Chronicles — Newsletter Sender
 * Sends the latest published chronicle to all active subscribers.
 *
 * Call this after the pipeline runs, or add it to the pipeline cron:
 *   0 1,7,13,19 * * *  wget -q -O /dev/null "https://coinorchronicles.com/admin/send-newsletter.php?key=YOUR_KEY"
 *
 * Uses PHP's built-in mail() — configure Hostinger SMTP in hPanel first.
 * Or swap the send_email() function below for PHPMailer/SMTP if you need delivery guarantees.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: (defined('ADMIN_PASS') ? ADMIN_PASS : 'changeme123'));

$expected_key = substr(hash('sha256', ADMIN_PASSWORD), 0, 16);
$provided_key = $_GET['key'] ?? '';

if (!hash_equals($expected_key, $provided_key)) {
    http_response_code(403);
    exit('Forbidden');
}

// ── Fetch latest unsent chronicle ─────────────────────────────────────────────
try {
    $pdo = get_db();
} catch (Throwable $e) {
    exit(json_encode(['ok'=>false,'error'=>'DB connection failed: '.$e->getMessage()]));
}

// Find most recent published chronicle that hasn't been emailed yet
// We track this via a simple site_settings key: last_emailed_episode
$last_emailed = (int)($pdo->query("SELECT setting_value FROM site_settings WHERE setting_key='last_emailed_episode'")->fetchColumn() ?: 0);

$stmt = $pdo->prepare("SELECT * FROM chronicles WHERE published=1 AND episode_number > ? ORDER BY episode_number ASC LIMIT 1");
$stmt->execute([$last_emailed]);
$chronicle = $stmt->fetch();

if (!$chronicle) {
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true,'message'=>'No new chronicles to send.']);
    exit;
}

// ── Fetch active subscribers ──────────────────────────────────────────────────
$subscribers = $pdo->query("SELECT email FROM subscribers WHERE active=1")->fetchAll(PDO::FETCH_COLUMN);

if (empty($subscribers)) {
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true,'message'=>'No subscribers yet.']);
    exit;
}

// ── Build email ───────────────────────────────────────────────────────────────
$ep_num   = str_pad((int)$chronicle['episode_number'], 3, '0', STR_PAD_LEFT);
$title    = strip_tags($chronicle['title']);
$excerpt  = strip_tags($chronicle['excerpt']);
$url      = SITE_URL . '/story.php?id=' . (int)$chronicle['id'];
$season   = $chronicle['season'] === 'golden' ? '⭐ The Golden Season' : ($chronicle['season'] === 'dark' ? '⚔ Dark Siege' : '— The Waiting Plains');
$date_fmt = date('j F Y', strtotime($chronicle['published_at'] ?? 'now'));

$subject = "Coinor Chronicles — Episode {$ep_num}: {$title}";

$plain_body = <<<TXT
COINOR CHRONICLES — Episode {$ep_num}
{$season} · {$date_fmt}

{$title}

{$excerpt}

Read the full chronicle:
{$url}

—
You are receiving this because you joined the fellowship at coinorchronicles.com.
To leave the fellowship, reply with "unsubscribe" in the subject line.
TXT;

$html_body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<style>
  body{margin:0;padding:0;background:#0d0c0a;font-family:Georgia,serif;color:#f0e6cc;}
  .wrap{max-width:600px;margin:0 auto;padding:40px 20px;}
  .header{text-align:center;padding:40px 0 32px;border-bottom:1px solid rgba(201,168,76,.15);}
  .crown{font-size:2rem;color:#c9a84c;display:block;margin-bottom:8px;}
  .brand{font-size:.65rem;letter-spacing:.35em;text-transform:uppercase;color:#c9a84c;opacity:.7;}
  .body{padding:40px 0;}
  .eyebrow{font-size:.62rem;letter-spacing:.3em;text-transform:uppercase;color:#c9a84c;opacity:.65;margin-bottom:10px;}
  .ep-title{font-size:1.4rem;color:#f0e6cc;line-height:1.35;margin-bottom:6px;}
  .meta{font-size:.72rem;color:#bfae8e;opacity:.6;margin-bottom:24px;}
  .excerpt{font-size:.95rem;line-height:1.85;color:#bfae8e;opacity:.85;margin-bottom:32px;}
  .cta{display:inline-block;background:#c9a84c;color:#0d0c0a;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:.78rem;letter-spacing:.14em;text-transform:uppercase;}
  .divider{height:1px;background:linear-gradient(90deg,transparent,rgba(201,168,76,.2),transparent);margin:40px 0;}
  .footer{text-align:center;font-size:.68rem;color:#bfae8e;opacity:.35;line-height:1.7;}
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <span class="crown">♚</span>
    <span class="brand">Coinor Chronicles · The Daily Scroll</span>
  </div>
  <div class="body">
    <div class="eyebrow">Episode {$ep_num} · {$season} · {$date_fmt}</div>
    <div class="ep-title">{$title}</div>
    <div class="meta">Narrated by Aragorn, Returned King · Clan of the Ancients</div>
    <div class="excerpt">{$excerpt}</div>
    <a href="{$url}" class="cta">Read the full chronicle →</a>
    <div class="divider"></div>
    <div class="footer">
      You walk with the fellowship of coinorchronicles.com<br/>
      To leave the fellowship, reply "unsubscribe" to this message.
    </div>
  </div>
</div>
</body>
</html>
HTML;

// ── Send emails ───────────────────────────────────────────────────────────────
$from_name    = 'Coinor Chronicles';
$from_email   = 'noreply@coinorchronicles.com';
$sent_count   = 0;
$failed_count = 0;

foreach ($subscribers as $email) {
    $headers = implode("\r\n", [
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "From: {$from_name} <{$from_email}>",
        "Reply-To: {$from_email}",
        "X-Mailer: CoinorChronicles/1.0",
    ]);

    $ok = mail(
        $email,
        $subject,
        $html_body,
        $headers
    );

    if ($ok) {
        $sent_count++;
    } else {
        $failed_count++;
    }

    // Avoid rate-limiting on shared hosts
    usleep(100000); // 100ms between sends
}

// ── Mark as sent ──────────────────────────────────────────────────────────────
if ($sent_count > 0) {
    // Ensure the key exists
    $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('last_emailed_episode', ?)
                   ON DUPLICATE KEY UPDATE setting_value=?")->execute([(int)$chronicle['episode_number'], (int)$chronicle['episode_number']]);
}

header('Content-Type: application/json');
echo json_encode([
    'ok'      => true,
    'episode' => $ep_num,
    'sent'    => $sent_count,
    'failed'  => $failed_count,
    'total'   => count($subscribers),
]);
