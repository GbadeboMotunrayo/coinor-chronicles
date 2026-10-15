<?php
// Shared helpers for the server-rendered pages (episodes, clans, realm, about, sitemap).
// Why PHP: search engines need a real URL and real HTML per chapter. The page text comes from the
// public episode feed on the VPS, cached here for 5 minutes. No secrets live in this file.

const SITE = 'https://coinorchonicles.space';
const FEED_URL = 'https://coinor.2-24-128-252.sslip.io/feed/episodes.json';
const CACHE_TTL = 300;

$CLANS = [
  'ancients' => ['name' => 'Clan of the Ancients', 'place' => 'The Obsidian Citadel',
    'blurb' => 'The oldest roads and the longest memories.',
    'who' => [['Aragorn', 'Bitcoin'], ['Gandalf', 'Ethereum'], ['Samwise', 'Litecoin'], ['Elrond', 'BNB'], ['Boromir', 'XRP']]],
  'swift' => ['name' => 'Clan of the Swift', 'place' => 'The Electric Plains',
    'blurb' => 'Fast riders on open ground. They win by moving first.',
    'who' => [['Legolas', 'Solana'], ['Eomer', 'Toncoin'], ['Eowyn', 'Avalanche'], ['Faramir', 'Stellar'], ['Saruman', 'Tron']]],
  'meme_lords' => ['name' => 'Clan of Meme Lords', 'place' => 'Kekiston Bazaar',
    'blurb' => 'Loud, loved and loyal. The Bazaar never fully sleeps.',
    'who' => [['Tom Bombadil', 'Pepe'], ['Merry', 'Shiba Inu'], ['Pippin', 'Dogecoin'], ['Theoden', 'Floki'], ['Frodo', 'Notcoin'], ['Bilbo', 'Book of Meme'], ['Gollum', 'Bonk']]],
  'builders' => ['name' => 'Clan of the Builders', 'place' => 'The Forge of Chains',
    'blurb' => 'Patient makers who build in silence.',
    'who' => [['Treebeard', 'Cardano'], ['Gimli', 'Sui'], ['Galadriel', 'Jasmy']]],
];
$CONDITIONS = ['golden_season' => 'The Golden Season', 'dark_siege' => 'The Dark Siege', 'waiting_plains' => 'The Waiting Plains'];

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function fetch_feed_text() {
  if (function_exists('curl_init')) {
    $c = curl_init(FEED_URL);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 4,
                           CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true]);
    $t = curl_exec($c);
    $ok = $t !== false && curl_getinfo($c, CURLINFO_HTTP_CODE) === 200;
    curl_close($c);
    return $ok ? $t : false;
  }
  $ctx = stream_context_create(['http' => ['timeout' => 6]]);
  return @file_get_contents(FEED_URL, false, $ctx);
}

// Approved episodes, newest first. Fresh cache < 5 min; else refetch; if the VPS is down, serve the stale copy.
function episodes() {
  static $memo = null;
  if ($memo !== null) return $memo;
  $cache = rtrim(sys_get_temp_dir(), '/') . '/coinor_feed_' . md5(__DIR__) . '.json';
  $raw = null;
  if (is_file($cache) && (time() - filemtime($cache)) < CACHE_TTL) $raw = @file_get_contents($cache);
  if (!$raw) {
    $t = fetch_feed_text();
    if ($t !== false && is_array(json_decode($t, true)) && isset(json_decode($t, true)['episodes'])) {
      $raw = $t;
      @file_put_contents($cache, $t, LOCK_EX);
    } elseif (is_file($cache)) {
      $raw = @file_get_contents($cache);
    }
  }
  $d = $raw ? json_decode($raw, true) : null;
  $eps = (is_array($d) && isset($d['episodes']) && is_array($d['episodes'])) ? $d['episodes'] : [];
  $eps = array_values(array_filter($eps, function ($e) {
    return is_array($e) && ($e['status'] ?? '') === 'approved' && isset($e['number'], $e['title'], $e['body']);
  }));
  usort($eps, function ($a, $b) { return $b['number'] <=> $a['number']; });
  return $memo = $eps;
}

function paragraphs($body) {
  $p = preg_split('/\n\s*\n/', (string)$body);
  return array_values(array_filter(array_map('trim', $p), 'strlen'));
}

function excerpt($body, $max = 155) {
  $paras = paragraphs($body);
  $first = $paras[0] ?? '';
  foreach ($paras as $i => $p) { if ($i > 0 && mb_strlen($p) >= 140) { $first = $p; break; } }
  $first = trim(str_replace('*', '', $first));
  if (mb_strlen($first) > $max) $first = preg_replace('/\s+\S*$/u', '', mb_substr($first, 0, $max - 1)) . '…';
  return $first;
}

// **bold** and *italic* only, after escaping, so story text can never inject markup.
function inline($text) {
  $t = h($text);
  $t = preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $t);
  return preg_replace('/\*([^*]+)\*/u', '<em>$1</em>', $t);
}

function fmt_date($iso) {
  $ts = strtotime(substr((string)$iso, 0, 10) . ' 12:00:00 UTC');
  return $ts ? gmdate('j F Y', $ts) : '';
}

function weather() {
  $e = episodes();
  $c = $e ? ($e[0]['condition'] ?? '') : '';
  return $c === 'dark_siege' ? 'siege' : ($c === 'golden_season' ? 'golden' : 'calm');
}

// ---------- page shell (same look as index.html) ----------
function page_open($o) {
  $title = $o['title']; $desc = $o['desc']; $canon = SITE . $o['path'];
  $robots = !empty($o['noindex']) ? '<meta name="robots" content="noindex,follow">' : '';
  header('Content-Type: text/html; charset=utf-8');
  header('Cache-Control: public, max-age=300');
  header('X-Content-Type-Options: nosniff');
  header('Referrer-Policy: strict-origin-when-cross-origin');
  echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
  echo '<title>' . h($title) . '</title><meta name="description" content="' . h($desc) . '">' . $robots;
  echo '<link rel="canonical" href="' . h($canon) . '">';
  echo '<meta property="og:site_name" content="CoinorChronicles"><meta property="og:type" content="' . h($o['og'] ?? 'website') . '">';
  echo '<meta property="og:title" content="' . h($title) . '"><meta property="og:description" content="' . h($desc) . '"><meta property="og:url" content="' . h($canon) . '">';
  echo '<meta name="twitter:card" content="summary"><meta name="theme-color" content="#140e08">';
  echo '<link rel="icon" href="/favicon.svg" type="image/svg+xml">';
  echo '<link rel="stylesheet" href="/styles.css">';
  echo '<script>(function(){var d=document.documentElement,w=function(){var x=innerWidth;d.setAttribute(\'data-screen\',x<600?\'phone\':x<1024?\'tablet\':\'desktop\');d.setAttribute(\'data-touch\',matchMedia(\'(pointer:coarse)\').matches?\'1\':\'0\')};w();addEventListener(\'resize\',w);addEventListener(\'orientationchange\',w)})()</script>';
  if (!empty($o['jsonld'])) echo '<script type="application/ld+json">' . json_encode($o['jsonld'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>';
  echo '</head><body data-weather="' . h(weather()) . '">';
  echo '<a class="skip" href="#app">Skip to the chronicle</a>';
  echo <<<'HTML'
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><filter id="rough" x="-5%" y="-2%" width="110%" height="104%"><feTurbulence type="fractalNoise" baseFrequency="0.035" numOctaves="3" seed="7" result="n"/><feDisplacementMap in="SourceGraphic" in2="n" scale="14" xChannelSelector="R" yChannelSelector="G"/></filter></svg>
<div class="realm-bg" aria-hidden="true"><svg viewBox="0 0 1600 1000" preserveAspectRatio="xMidYMid slice" fill="none" stroke="#c9a24a" stroke-linecap="round"><defs><path id="ct" d="M-50 520C120 420 260 560 420 470S700 330 880 430 1180 560 1350 440 1560 380 1660 430"/></defs><g opacity=".11" stroke-width="1.2"><use href="#ct"/><use href="#ct" transform="translate(0 70)"/><use href="#ct" transform="translate(0 140)"/><use href="#ct" transform="translate(0 -70)"/><use href="#ct" transform="translate(0 -140)"/><use href="#ct" transform="translate(0 210)"/><use href="#ct" transform="translate(0 -210)"/></g><path class="far-route" d="M140 880C320 700 420 820 620 640S860 420 1040 480 1300 300 1460 130" stroke-dasharray="3 12" stroke-width="2.4" opacity=".28"/><g class="far-gate" transform="translate(1460 120)" opacity=".5"><path d="M-26 40V-6a26 26 0 0 1 52 0v46" stroke-width="3"/></g></svg></div>
<div class="scroll"><div class="rod top" aria-hidden="true"></div><div class="sheet"><div class="col">
<header class="site-header"><div class="bar"><a class="brand" href="/" aria-label="CoinorChronicles home"><span>CoinorChronicles</span></a>
<nav aria-label="Main"><a href="/">Chronicle</a><a href="/realm"
HTML;
  echo (($o['nav'] ?? '') === 'realm' ? ' aria-current="page"' : '') . '>The Realm</a><a href="/about"' . (($o['nav'] ?? '') === 'about' ? ' aria-current="page"' : '') . '>About</a><a href="/support"' . (($o['nav'] ?? '') === 'support' ? ' aria-current="page"' : '') . '>Support</a></nav></div></header>';
  echo '<main id="app" tabindex="-1">';
}

function page_close($sub = true) {
  if ($sub) echo '<div data-subscribe></div><script src="/subscribe.js" defer></script>';
  echo <<<'HTML'
</main>
<footer class="site-footer"><div>
<p><strong>Fiction.</strong> The Realm of Coinor, its travellers and every episode are works of fiction that use public market data as story material. Nothing here is financial advice, a recommendation, or an offer. Markets are risky; do your own research.</p>
<p>Episodes are written by AI and published automatically after they pass safety checks. No person reads each one first; a person can remove any episode.</p>
<p class="muted"><a href="/support">Support the journey</a> · <a href="/privacy">Privacy</a> · A story project from MG Holdings. Market data: <a href="https://www.coingecko.com/en/api" rel="noopener">Powered by CoinGecko API</a>.</p>
</div></footer>
</div></div><div class="rod bottom" aria-hidden="true"></div></div>
</body></html>
HTML;
}

function not_found($msg = 'That chapter is not in the Book') {
  http_response_code(404);
  page_open(['title' => 'Not found · CoinorChronicles', 'desc' => $msg, 'path' => $_SERVER['REQUEST_URI'] ?? '/', 'noindex' => true]);
  echo '<h1>' . h($msg) . '</h1><p><a href="/">Back to the chronicle</a></p>';
  page_close();
  exit;
}

function card_html($e) {
  global $CLANS, $CONDITIONS;
  $clan = $CLANS[$e['clan'] ?? ''] ?? ['name' => ''];
  $cls = 'clan-' . preg_replace('/[^a-z_]/', '', $e['clan'] ?? '');
  return '<a class="card ' . $cls . '" href="/episode/' . (int)$e['number'] . '"><span class="seal" aria-hidden="true">' . (int)$e['number'] . '</span>'
    . '<p class="meta"><span class="tag">Episode ' . (int)$e['number'] . '</span><span>' . h($clan['name']) . '</span><span>' . h($CONDITIONS[$e['condition'] ?? ''] ?? '') . '</span><span>' . h(fmt_date($e['date'] ?? '')) . '</span></p>'
    . '<h3>' . h($e['title']) . '</h3><p>' . h(excerpt($e['body'], 170)) . '</p></a>';
}
