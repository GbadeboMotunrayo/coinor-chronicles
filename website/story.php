<?php
require_once __DIR__ . '/includes/config.php';

$episode = null;
$id = (int)($_GET['id'] ?? 0);

try {
    require_once __DIR__ . '/includes/db.php';
    $episode = get_chronicle_by_id($id);
} catch (Throwable) {
    // DB not yet set up — show sample
    if ($id === 47) {
        $episode = [
            'id'             => 47,
            'episode_number' => 47,
            'title'          => '"Sit, Young Pepe. The Citadel doors opened wide this morning."',
            'body'           => '<p>Aragorn settled into the great chair at the summit of the Obsidian Citadel. The fires in the courtyard below burned low — but they burned. That was enough.</p>
<p>"Sit, young Pepe. Let me tell you what the roads whispered today."</p>
<p>The young seeker crouched beside the great stone hearth. Outside, the Electric Plains crackled with distant lightning. Legolas was moving again.</p>
<p>"The Eagle Gods loosened their grip on the trade routes overnight," Aragorn continued. "Word came by swift rider from the Fog Merchants — their own ancient debt lords would hold their edicts another cycle. The roads breathed."</p>
<p>"And Gandalf?" Pepe asked.</p>
<p>"Gandalf ascended three Heavens before the second bell. His bridges carried more weight today than they have in forty cycles." Aragorn paused. The torchlight caught the lines of his face — lines that told of a hundred sieges survived. "He does not celebrate. He never celebrates. He is already building the next bridge."</p>
<p>The Bazaar of Kekiston erupted during the sixth hour. Merry — impossible, unpredictable Merry — gained eighteen gold units between the first and second bell. The crowd in the Bazaar went from murmur to roar in the space of a heartbeat.</p>
<p>"Will it hold?" Pepe asked.</p>
<p>Aragorn was quiet for a long moment.</p>
<p>"Nothing holds forever. But today, the road is wider. The fellowship is stronger. The Dark Siege has receded — it will return, as it always does. But it is not this day." He reached for the great map spread across the table. "Tomorrow, we ride."</p>
<p><em>The fellowship endures. It always has. It always will.</em></p>',
            'season'         => 'golden',
            'clan'           => 'Clan of the Ancients',
            'character_name' => 'Aragorn',
            'published_at'   => '2026-06-16 06:00:00',
            'slug'           => 'episode-047',
        ];
    }
}

if (!$episode) {
    http_response_code(404);
    header('Location: /chronicle.php');
    exit;
}

$is_dark  = ($episode['season'] ?? '') === 'dark';
$date_fmt = date('j F Y', strtotime($episode['published_at'] ?? 'now'));
$ep_num   = str_pad((int)$episode['episode_number'], 3, '0', STR_PAD_LEFT);

$page_title = 'Episode ' . $ep_num . ' — ' . htmlspecialchars(strip_tags($episode['title'])) . ' · Coinor Chronicles';
$page_desc  = htmlspecialchars(mb_strimwidth(strip_tags($episode['excerpt'] ?? $episode['body'] ?? ''), 0, 155, '…'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="description" content="<?= $page_desc ?>"/>
  <meta property="og:title"       content="<?= $page_title ?>"/>
  <meta property="og:description" content="<?= $page_desc ?>"/>
  <meta property="og:type"        content="article"/>
  <meta property="article:published_time" content="<?= htmlspecialchars($episode['published_at']) ?>"/>
  <title><?= $page_title ?></title>
  <link rel="stylesheet" href="/assets/css/main.css"/>
  <style>
    .story-hero {
      padding: 9rem 2rem 4rem;
      background: radial-gradient(ellipse at 50% 0%, <?= $is_dark ? '#1a0a0a' : '#1e1a10' ?> 0%, var(--dark) 65%);
      position: relative; text-align: center;
    }
    .story-hero::before {
      content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 1px;
      background: linear-gradient(90deg, transparent, rgba(201,168,76,0.18), transparent);
    }
    .story-episode-label {
      display: inline-flex; align-items: center; gap: 0.8rem;
      margin-bottom: 1.5rem;
    }
    .story-ep-number {
      font-size: 0.62rem; letter-spacing: 0.3em; text-transform: uppercase;
      color: var(--gold); opacity: 0.6;
    }
    .story-ep-dot { width: 4px; height: 4px; border-radius: 50%; background: rgba(201,168,76,0.35); }
    .story-clan-badge {
      font-size: 0.6rem; letter-spacing: 0.18em; text-transform: uppercase;
      padding: 0.2rem 0.7rem; border-radius: 100px;
      background: rgba(201,168,76,0.08); border: 1px solid rgba(201,168,76,0.2); color: var(--gold);
    }
    .story-hero h1 {
      font-size: clamp(1.4rem, 3.5vw, 2.6rem);
      color: var(--cream); line-height: 1.3; max-width: 700px; margin: 0 auto 1.2rem;
      font-style: italic;
    }
    .story-meta {
      display: flex; align-items: center; justify-content: center; gap: 1rem; flex-wrap: wrap;
      font-size: 0.65rem; letter-spacing: 0.18em; text-transform: uppercase; color: var(--cream-dim); opacity: 0.5;
    }
    .story-season-badge {
      font-size: 0.62rem; padding: 0.18rem 0.65rem; border-radius: 100px;
    }
    .story-season-badge.golden { background: rgba(93,222,138,0.08); border: 1px solid rgba(93,222,138,0.2); color: var(--green-up); }
    .story-season-badge.dark   { background: rgba(224,93,93,0.08);  border: 1px solid rgba(224,93,93,0.2);  color: var(--red-down); }

    .story-body-wrap {
      background: var(--dark-mid); padding: 5rem 2rem 6rem;
    }

    .story-body {
      max-width: 680px; margin: 0 auto;
      font-size: clamp(1rem, 1.8vw, 1.12rem);
      line-height: 1.92;
      color: var(--cream);
    }

    .story-body p { margin-bottom: 1.5rem; }
    .story-body p:last-child { margin-bottom: 0; }

    .story-body em {
      color: var(--gold); opacity: 0.82; font-style: italic;
    }

    .story-body blockquote {
      border-left: 2px solid rgba(201,168,76,0.35);
      padding: 0.5rem 0 0.5rem 1.2rem;
      margin: 2rem 0;
      color: var(--gold);
      opacity: 0.75;
      font-style: italic;
    }

    /* Nav between episodes */
    .episode-nav {
      display: flex; justify-content: space-between; align-items: center;
      max-width: 680px; margin: 3rem auto 0;
      padding-top: 2rem;
      border-top: 1px solid rgba(201,168,76,0.1);
      gap: 1rem;
    }

    .ep-nav-link {
      font-size: 0.72rem; letter-spacing: 0.14em; text-transform: uppercase;
      color: var(--gold); opacity: 0.6; transition: opacity var(--transition);
      text-decoration: none;
    }
    .ep-nav-link:hover { opacity: 1; }

    .ep-nav-archive {
      font-size: 0.72rem; letter-spacing: 0.14em; text-transform: uppercase;
      color: var(--cream-dim); opacity: 0.4; transition: opacity var(--transition);
      text-decoration: none;
    }
    .ep-nav-archive:hover { color: var(--gold); opacity: 0.8; }
  </style>
</head>
<body>

<div id="progress-bar" role="progressbar" aria-hidden="true"></div>

<nav id="nav" role="navigation" aria-label="Main navigation">
  <a href="/" class="nav-brand" aria-label="Coinor Chronicles home">
    <span class="nav-brand-crown" aria-hidden="true">♚</span>
    Coinor Chronicles
    <span class="nav-brand-sub">· The Realm of Coinor</span>
  </a>
  <ul class="nav-links" role="list">
    <li><a href="/the-fellowship.php">The Fellowship</a></li>
    <li><a href="/chronicle.php" class="active">The Chronicle</a></li>
    <li><a href="/lore.html">Lore</a></li>
  </ul>
  <a href="/#subscribe" class="nav-cta">Receive the Scroll</a>
  <button class="nav-toggle" aria-label="Toggle navigation" aria-expanded="false" aria-controls="mobile-menu">
    <span></span><span></span><span></span>
  </button>
</nav>

<div id="mobile-menu" role="dialog" aria-label="Mobile navigation">
  <a href="/">Home</a>
  <a href="/the-fellowship.php">The Fellowship</a>
  <a href="/chronicle.php">The Chronicle</a>
  <a href="/lore.html">Lore</a>
  <a href="/#subscribe">Receive the Scroll</a>
</div>


<!-- Story hero -->
<header class="story-hero">
  <div class="story-episode-label">
    <span class="story-ep-number">Episode <?= $ep_num ?></span>
    <span class="story-ep-dot" aria-hidden="true"></span>
    <span class="story-clan-badge"><?= htmlspecialchars($episode['clan'] ?? 'The Fellowship') ?></span>
  </div>
  <h1><?= htmlspecialchars($episode['title']) ?></h1>
  <div class="story-meta">
    <span><?= htmlspecialchars($date_fmt) ?></span>
    <span class="story-season-badge <?= $is_dark ? 'dark' : 'golden' ?>">
      <?= $is_dark ? 'The Dark Siege' : 'The Golden Season' ?>
    </span>
    <span><?= htmlspecialchars($episode['character_name'] ?? 'Aragorn') ?> narrates</span>
  </div>
</header>


<!-- Story body -->
<main class="story-body-wrap" aria-label="Episode story">
  <article class="story-body">
    <?= $episode['body'] ?? '<p>The chronicle scribes are setting their quills. This episode will appear shortly.</p>' ?>

    <nav class="episode-nav" aria-label="Episode navigation">
      <?php if ($episode['id'] > 1): ?>
        <a href="/story.php?id=<?= $episode['id'] - 1 ?>" class="ep-nav-link">← Previous episode</a>
      <?php else: ?>
        <span></span>
      <?php endif; ?>

      <a href="/chronicle.php" class="ep-nav-archive">All chronicles</a>

      <a href="/story.php?id=<?= $episode['id'] + 1 ?>" class="ep-nav-link">Next episode →</a>
    </nav>
  </article>
</main>


<footer id="footer" role="contentinfo">
  <div class="footer-inner">
    <div class="footer-brand">
      <h3>Coinor Chronicles</h3>
      <p>An AI media empire. Twenty-one companions. One road. Updated every six hours.</p>
    </div>
    <div class="footer-col">
      <h4>The Realm</h4>
      <ul>
        <li><a href="/chronicle.php">The Chronicle</a></li>
        <li><a href="/the-fellowship.php">The Fellowship</a></li>
        <li><a href="/lore.html">The Lore</a></li>
        <li><a href="/#subscribe">Subscribe</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>The Clans</h4>
      <ul>
        <li><a href="/the-fellowship.php#ancients">Clan of the Ancients</a></li>
        <li><a href="/the-fellowship.php#swift">Clan of the Swift</a></li>
        <li><a href="/the-fellowship.php#meme">Clan of Meme Lords</a></li>
        <li><a href="/the-fellowship.php#builders">Clan of the Builders</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>Follow the Quest</h4>
      <ul>
        <li><a href="#" rel="noopener noreferrer">YouTube</a></li>
        <li><a href="#" rel="noopener noreferrer">TikTok</a></li>
        <li><a href="#" rel="noopener noreferrer">X / Twitter</a></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <p>© <?= date('Y') ?> Coinor Chronicles · The Realm of Coinor</p>
    <p>The chronicle continues ∞</p>
  </div>
</footer>

<script>
(function() {
  const nav = document.getElementById('nav');
  const bar = document.getElementById('progress-bar');
  window.addEventListener('scroll', function() {
    nav.classList.toggle('scrolled', window.scrollY > 60);
    const s = document.documentElement.scrollTop;
    const t = document.documentElement.scrollHeight - window.innerHeight;
    if (bar && t > 0) bar.style.width = (s/t*100)+'%';
  }, { passive:true });
  const toggle = document.querySelector('.nav-toggle');
  const menu   = document.getElementById('mobile-menu');
  if (toggle && menu) {
    toggle.addEventListener('click', function() {
      const open = menu.classList.toggle('open');
      toggle.classList.toggle('open', open);
      toggle.setAttribute('aria-expanded', String(open));
    });
  }
}());
</script>
</body>
</html>
