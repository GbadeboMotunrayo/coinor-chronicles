<?php
require_once __DIR__ . '/includes/config.php';

$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 12;
$offset = ($page - 1) * $per;
$season = $_GET['season'] ?? 'all';

// Try to load from DB; fall back to sample data for initial launch
$chronicles = [];
$total      = 0;

try {
    require_once __DIR__ . '/includes/db.php';
    $chronicles = get_latest_chronicles($per, $offset);
    $total      = count_chronicles();
} catch (Throwable) {
    // DB not yet set up — show sample data
    $chronicles = get_sample_chronicles();
    $total      = count($chronicles);
}

function get_sample_chronicles(): array {
    return [
        ['id'=>47,'episode_number'=>47,'title'=>'"Sit, Young Pepe. The Citadel doors opened wide this morning."','excerpt'=>'Aragorn watched from the parapets as Gandalf ascended three Heavens before the second bell. The Eagle Gods had loosened their grip on the trade routes overnight...','season'=>'golden','clan'=>'Clan of the Ancients','character_name'=>'Aragorn','published_at'=>'2026-06-16 06:00:00','slug'=>'episode-047'],
        ['id'=>46,'episode_number'=>46,'title'=>'"The Swift Riders scattered. But Legolas reached Heaven 2 before the dust settled."','excerpt'=>'A Dark Siege swept the Electric Plains in the third cycle. Most of the Swift Clan retreated. But Legolas — always Legolas — found the gap in the ambush and rode through it...','season'=>'dark','clan'=>'Clan of the Swift','character_name'=>'Legolas','published_at'=>'2026-06-16 00:00:00','slug'=>'episode-046'],
        ['id'=>45,'episode_number'=>45,'title'=>'"The Bazaar erupted. Merry gained eighteen gold units in a single bell."','excerpt'=>'No one predicted it. No one could have predicted it. The Kekiston Bazaar fell silent for a moment — then exploded. Merry, the impossible dreamer, gained more provisions in one bell than most companions gain in a cycle...','season'=>'golden','clan'=>'Clan of Meme Lords','character_name'=>'Merry','published_at'=>'2026-06-15 18:00:00','slug'=>'episode-045'],
        ['id'=>44,'episode_number'=>44,'title'=>'"Treebeard speaks. When Treebeard speaks, the Forge listens."','excerpt'=>'It is rare for Treebeard to address the full council. He prefers the quiet of the Forge, the sound of iron on stone. But today he spoke. And the weight of his words...','season'=>'golden','clan'=>'Clan of the Builders','character_name'=>'Treebeard','published_at'=>'2026-06-15 12:00:00','slug'=>'episode-044'],
        ['id'=>43,'episode_number'=>43,'title'=>'"Boromir holds the bridge. The Eagle Warriors circle but do not land."','excerpt'=>'The embattled bridge-keeper. Every ruling that falls in his favor is a sword blow returned. Every ruling against him — and he has weathered many — only teaches him to stand wider...','season'=>'dark','clan'=>'Clan of the Ancients','character_name'=>'Boromir','published_at'=>'2026-06-15 06:00:00','slug'=>'episode-043'],
        ['id'=>42,'episode_number'=>42,'title'=>'"The Shire road was quiet. Aragorn watched. He has seen this quiet before."','excerpt'=>'The Waiting Plains are not the enemy. Aragorn has endured longer silences than this. The fellowship rests. Plans. Trains. The silence before the Golden Season is still silence...','season'=>'dark','clan'=>'Clan of the Ancients','character_name'=>'Aragorn','published_at'=>'2026-06-14 18:00:00','slug'=>'episode-042'],
    ];
}

$page_title = 'The Chronicle — Coinor Chronicles';
$page_desc  = 'The full archive of the Realm of Coinor. Every episode, every season, every companion. The road is long and the chronicle is longer.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="description" content="<?= htmlspecialchars($page_desc) ?>"/>
  <meta property="og:title"       content="<?= htmlspecialchars($page_title) ?>"/>
  <meta property="og:description" content="<?= htmlspecialchars($page_desc) ?>"/>
  <title><?= htmlspecialchars($page_title) ?></title>
  <link rel="stylesheet" href="/assets/css/main.css"/>
  <style>
    .page-hero {
      padding: 9rem 2rem 5rem;
      text-align: center;
      background: radial-gradient(ellipse at 50% 0%, #1e1a10 0%, var(--dark) 65%);
      position: relative;
    }
    .page-hero::before {
      content: ''; position: absolute;
      bottom: 0; left: 0; right: 0; height: 1px;
      background: linear-gradient(90deg, transparent, rgba(201,168,76,0.2), transparent);
    }
    .page-hero h1 {
      font-size: clamp(2rem, 5vw, 3.8rem);
      color: var(--gold); letter-spacing: 0.08em;
      text-transform: uppercase;
      text-shadow: 0 0 60px rgba(201,168,76,0.3);
    }
    .page-hero p { margin: 1rem auto 0; max-width: 500px; font-size: clamp(0.9rem,1.6vw,1.05rem); color: var(--cream-dim); font-style: italic; line-height: 1.8; opacity: 0.72; }

    #chronicle-page { padding: 4rem 2rem 6rem; background: var(--dark); }

    .filter-bar {
      display: flex;
      justify-content: center;
      gap: 0.5rem;
      flex-wrap: wrap;
      margin-bottom: 3rem;
    }

    .filter-btn {
      background: none; border: 1px solid rgba(201,168,76,0.15); border-radius: 100px;
      padding: 0.45rem 1.2rem;
      font-family: var(--font-serif); font-size: 0.7rem; letter-spacing: 0.18em; text-transform: uppercase;
      color: var(--cream-dim); opacity: 0.55; cursor: pointer;
      transition: all var(--transition);
      text-decoration: none; display: inline-block;
    }
    .filter-btn:hover  { opacity: 0.85; border-color: rgba(201,168,76,0.3); color: var(--cream); }
    .filter-btn.active { opacity: 1; color: var(--dark); background: var(--gold); border-color: var(--gold); }

    .chronicle-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
      gap: 1.4rem;
      max-width: 1160px;
      margin: 0 auto 3rem;
    }

    .chronicle-card {
      background: var(--mid);
      border: 1px solid rgba(201,168,76,0.1);
      border-radius: var(--radius-lg);
      overflow: hidden;
      display: flex; flex-direction: column;
      transition: border-color var(--transition), transform var(--transition), box-shadow var(--transition);
      text-decoration: none;
      color: inherit;
    }
    .chronicle-card:hover {
      border-color: rgba(201,168,76,0.25); transform: translateY(-3px);
      box-shadow: 0 12px 40px rgba(0,0,0,0.5);
    }

    .card-visual {
      height: 130px;
      background: radial-gradient(ellipse at 40% 50%, #2a1f0e 0%, #0d0a06 100%);
      position: relative; overflow: hidden;
      display: flex; align-items: center; justify-content: center;
    }
    .card-visual.season-dark { background: radial-gradient(ellipse at 40% 50%, #1a0a0a 0%, #080606 100%); }

    .card-episode-num {
      font-size: 5rem; font-weight: bold; letter-spacing: -0.05em;
      color: rgba(201,168,76,0.05); position: absolute; bottom: -0.5rem; right: 0.8rem; line-height: 1;
    }

    .card-clan-badge {
      font-size: 0.58rem; letter-spacing: 0.2em; text-transform: uppercase;
      padding: 0.3rem 0.9rem; border-radius: 100px;
      background: rgba(201,168,76,0.1); border: 1px solid rgba(201,168,76,0.2); color: var(--gold);
      z-index: 1;
    }

    .card-body { padding: 1.5rem 1.5rem 1.3rem; flex: 1; display: flex; flex-direction: column; }

    .card-meta {
      display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem; flex-wrap: wrap;
    }

    .card-date { font-size: 0.62rem; letter-spacing: 0.18em; text-transform: uppercase; color: var(--cream-dim); opacity: 0.5; }

    .card-season {
      font-size: 0.62rem; letter-spacing: 0.1em;
      padding: 0.15rem 0.6rem; border-radius: 100px;
    }
    .card-season.golden { background: rgba(93,222,138,0.08); border: 1px solid rgba(93,222,138,0.2); color: var(--green-up); }
    .card-season.dark   { background: rgba(224,93,93,0.08);  border: 1px solid rgba(224,93,93,0.2);  color: var(--red-down); }

    .card-title { font-size: clamp(0.9rem, 1.8vw, 1.1rem); color: var(--cream); line-height: 1.35; margin-bottom: 0.75rem; flex: 1; }

    .card-excerpt { font-size: 0.82rem; line-height: 1.75; color: var(--cream-dim); opacity: 0.65; margin-bottom: 1rem; }

    .card-read-link {
      display: inline-flex; align-items: center; gap: 0.4rem;
      font-size: 0.72rem; letter-spacing: 0.14em; text-transform: uppercase;
      color: var(--gold); transition: gap var(--transition); margin-top: auto;
    }
    .card-read-link::after { content: '→'; }
    .chronicle-card:hover .card-read-link { gap: 0.75rem; }

    /* Pagination */
    .pagination {
      display: flex; justify-content: center; align-items: center;
      gap: 0.5rem; flex-wrap: wrap;
    }

    .page-btn {
      width: 38px; height: 38px; border-radius: var(--radius);
      border: 1px solid rgba(201,168,76,0.2);
      background: none;
      font-family: var(--font-serif); font-size: 0.85rem;
      color: var(--cream-dim); opacity: 0.55;
      cursor: pointer; transition: all var(--transition);
      display: flex; align-items: center; justify-content: center;
      text-decoration: none;
    }
    .page-btn:hover  { opacity: 0.85; border-color: rgba(201,168,76,0.4); color: var(--cream); }
    .page-btn.active { background: var(--gold); border-color: var(--gold); color: var(--dark); opacity: 1; }
    .page-btn.prev, .page-btn.next { width: auto; padding: 0 1rem; font-size: 0.72rem; letter-spacing: 0.12em; text-transform: uppercase; }
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


<div class="page-hero">
  <div class="section-eyebrow">Updated Every Six Hours</div>
  <h1>The Chronicle</h1>
  <p>Every episode ever told. Every season endured. The road is long and the chronicle is longer.</p>
</div>


<main id="chronicle-page" aria-label="Chronicle archive">

  <!-- Season filter -->
  <div class="filter-bar" role="group" aria-label="Filter by season">
    <a href="/chronicle.php" class="filter-btn <?= $season === 'all'    ? 'active' : '' ?>">All Seasons</a>
    <a href="/chronicle.php?season=golden" class="filter-btn <?= $season === 'golden' ? 'active' : '' ?>">The Golden Season</a>
    <a href="/chronicle.php?season=dark"   class="filter-btn <?= $season === 'dark'   ? 'active' : '' ?>">The Dark Siege</a>
  </div>

  <!-- Chronicle grid -->
  <div class="chronicle-grid" aria-label="Chronicle list">
    <?php if (empty($chronicles)): ?>
      <div style="grid-column:1/-1;text-align:center;padding:4rem 0;color:var(--cream-dim);opacity:0.5;">
        <p>The chronicle scribes are still setting their quills. Episodes will appear here soon.</p>
      </div>
    <?php else: ?>
      <?php foreach ($chronicles as $ep): ?>
        <?php
          $is_dark   = ($ep['season'] ?? '') === 'dark';
          $date_fmt  = date('j F, Third Age', strtotime($ep['published_at'] ?? 'now'));
          $read_url  = '/story.php?id=' . (int)$ep['id'];
        ?>
        <a href="<?= htmlspecialchars($read_url) ?>" class="chronicle-card" aria-label="Episode <?= (int)$ep['episode_number'] ?>: <?= htmlspecialchars($ep['title']) ?>">
          <div class="card-visual <?= $is_dark ? 'season-dark' : '' ?>">
            <div class="card-clan-badge"><?= htmlspecialchars($ep['clan'] ?? 'The Fellowship') ?></div>
            <div class="card-episode-num"><?= str_pad((int)$ep['episode_number'], 3, '0', STR_PAD_LEFT) ?></div>
          </div>
          <div class="card-body">
            <div class="card-meta">
              <span class="card-date"><?= htmlspecialchars($date_fmt) ?></span>
              <span class="card-season <?= $is_dark ? 'dark' : 'golden' ?>"><?= $is_dark ? 'Dark Siege' : 'Golden Season' ?></span>
            </div>
            <h2 class="card-title"><?= htmlspecialchars($ep['title']) ?></h2>
            <p class="card-excerpt"><?= htmlspecialchars(mb_strimwidth($ep['excerpt'] ?? '', 0, 160, '…')) ?></p>
            <span class="card-read-link">Read the Chronicle</span>
          </div>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Pagination -->
  <?php if ($total > $per): ?>
    <?php $pages = ceil($total / $per); ?>
    <nav class="pagination" aria-label="Pagination">
      <?php if ($page > 1): ?>
        <a href="/chronicle.php?page=<?= $page - 1 ?>&season=<?= urlencode($season) ?>" class="page-btn prev">← Previous</a>
      <?php endif; ?>

      <?php for ($p = 1; $p <= $pages; $p++): ?>
        <a href="/chronicle.php?page=<?= $p ?>&season=<?= urlencode($season) ?>"
           class="page-btn <?= $p === $page ? 'active' : '' ?>"
           aria-label="Page <?= $p ?>"
           <?= $p === $page ? 'aria-current="page"' : '' ?>><?= $p ?></a>
      <?php endfor; ?>

      <?php if ($page < $pages): ?>
        <a href="/chronicle.php?page=<?= $page + 1 ?>&season=<?= urlencode($season) ?>" class="page-btn next">Next →</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>

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
  window.addEventListener('scroll', function() { nav.classList.toggle('scrolled', window.scrollY > 60); }, { passive:true });
  const bar = document.getElementById('progress-bar');
  window.addEventListener('scroll', function() {
    const s = document.documentElement.scrollTop;
    const t = document.documentElement.scrollHeight - window.innerHeight;
    if (bar && t > 0) bar.style.width = (s/t*100) + '%';
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
  if ('IntersectionObserver' in window) {
    const obs = new IntersectionObserver(function(entries) {
      entries.forEach(function(e) {
        if (!e.isIntersecting) return;
        e.target.style.transition = 'opacity 0.7s cubic-bezier(0.16,1,0.3,1), transform 0.7s cubic-bezier(0.16,1,0.3,1)';
        e.target.style.opacity   = '1';
        e.target.style.transform = 'none';
        obs.unobserve(e.target);
      });
    }, { threshold: 0.08 });
    document.querySelectorAll('.chronicle-card').forEach(function(el) {
      el.style.opacity = '0'; el.style.transform = 'translateY(30px)'; obs.observe(el);
    });
  }
}());
</script>
</body>
</html>
