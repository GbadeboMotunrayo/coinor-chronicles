<?php
require_once __DIR__ . '/includes/config.php';

$page_title = 'The Fellowship — Coinor Chronicles';
$page_desc  = 'Twenty-one companions. Four clans. One eternal quest. Meet every member of the fellowship of the Realm of Coinor.';
$page_class = 'page-fellowship';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="description" content="<?= htmlspecialchars($page_desc) ?>"/>
  <meta property="og:title"       content="<?= htmlspecialchars($page_title) ?>"/>
  <meta property="og:description" content="<?= htmlspecialchars($page_desc) ?>"/>
  <meta property="og:url"         content="https://coinorchronicles.com/the-fellowship.php"/>
  <title><?= htmlspecialchars($page_title) ?></title>
  <link rel="preconnect" href="https://cdnjs.cloudflare.com"/>
  <link rel="stylesheet" href="/assets/css/main.css"/>
  <style>
    /* ── PAGE HERO ─────────────────────────────────────── */
    .page-hero {
      padding: 9rem 2rem 5rem;
      text-align: center;
      background: radial-gradient(ellipse at 50% 0%, #1e1a10 0%, var(--dark) 65%);
      position: relative;
      overflow: hidden;
    }

    .page-hero::before {
      content: '';
      position: absolute;
      bottom: 0; left: 0; right: 0; height: 1px;
      background: linear-gradient(90deg, transparent, rgba(201,168,76,0.2), transparent);
    }

    .page-hero .section-eyebrow { margin-bottom: 0.75rem; }

    .page-hero h1 {
      font-size: clamp(2rem, 5vw, 3.8rem);
      color: var(--gold);
      letter-spacing: 0.08em;
      text-transform: uppercase;
      line-height: 1.1;
      text-shadow: 0 0 60px rgba(201,168,76,0.3);
    }

    .page-hero p {
      margin: 1rem auto 0;
      max-width: 520px;
      font-size: clamp(0.9rem, 1.6vw, 1.05rem);
      color: var(--cream-dim);
      font-style: italic;
      line-height: 1.8;
      opacity: 0.75;
    }

    /* ── CLAN TABS ──────────────────────────────────────── */
    #fellowship-page { padding: 4rem 2rem 6rem; background: var(--dark); }

    .clan-tabs {
      display: flex;
      justify-content: center;
      gap: 0.5rem;
      flex-wrap: wrap;
      margin-bottom: 3.5rem;
    }

    .clan-tab {
      background: none;
      border: 1px solid rgba(201,168,76,0.15);
      border-radius: 100px;
      padding: 0.5rem 1.3rem;
      font-family: var(--font-serif);
      font-size: 0.72rem;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: var(--cream-dim);
      opacity: 0.55;
      cursor: pointer;
      transition: all var(--transition);
    }

    .clan-tab:hover         { opacity: 0.85; border-color: rgba(201,168,76,0.3); color: var(--cream); }
    .clan-tab.active        { opacity: 1; color: var(--dark); background: var(--gold); border-color: var(--gold); }
    .clan-tab[data-clan="ancients"].active  { background: var(--clan-ancients); border-color: var(--clan-ancients); }
    .clan-tab[data-clan="swift"].active     { background: var(--clan-swift);    border-color: var(--clan-swift); }
    .clan-tab[data-clan="meme"].active      { background: var(--clan-meme);     border-color: var(--clan-meme); }
    .clan-tab[data-clan="builders"].active  { background: var(--clan-builders); border-color: var(--clan-builders); }

    /* ── CLAN SECTION ───────────────────────────────────── */
    .clan-section {
      max-width: 1160px;
      margin: 0 auto 4rem;
      scroll-margin-top: 90px;
    }

    .clan-header {
      display: flex;
      align-items: center;
      gap: 1.5rem;
      margin-bottom: 2rem;
      padding-bottom: 1.2rem;
      border-bottom: 1px solid rgba(201,168,76,0.1);
    }

    .clan-icon {
      font-size: 1.8rem;
      filter: drop-shadow(0 0 10px currentColor);
    }

    .clan-header-text h2 {
      font-size: clamp(1.2rem, 2.5vw, 1.8rem);
      letter-spacing: 0.08em;
      text-transform: uppercase;
      line-height: 1.2;
    }

    .clan-header-text p {
      font-size: 0.8rem;
      color: var(--cream-dim);
      opacity: 0.55;
      letter-spacing: 0.06em;
      margin-top: 0.2rem;
      font-style: italic;
    }

    .clan-section.ancients .clan-icon,
    .clan-section.ancients .clan-header-text h2 { color: var(--clan-ancients); }
    .clan-section.swift    .clan-icon,
    .clan-section.swift    .clan-header-text h2 { color: var(--clan-swift);    }
    .clan-section.meme     .clan-icon,
    .clan-section.meme     .clan-header-text h2 { color: var(--clan-meme);     }
    .clan-section.builders .clan-icon,
    .clan-section.builders .clan-header-text h2 { color: var(--clan-builders); }

    /* ── CHARACTER CARDS ────────────────────────────────── */
    .characters-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 1.2rem;
    }

    .char-card {
      background: var(--mid);
      border: 1px solid rgba(201,168,76,0.09);
      border-radius: var(--radius-lg);
      padding: 1.8rem 1.6rem;
      position: relative;
      overflow: hidden;
      transition: border-color var(--transition), transform var(--transition), box-shadow var(--transition);
    }

    .char-card:hover {
      border-color: rgba(201,168,76,0.22);
      transform: translateY(-3px);
      box-shadow: 0 12px 40px rgba(0,0,0,0.5);
    }

    .char-card::before {
      content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
    }
    .char-card.ancients::before { background: linear-gradient(90deg, transparent, var(--clan-ancients), transparent); }
    .char-card.swift::before    { background: linear-gradient(90deg, transparent, var(--clan-swift),    transparent); }
    .char-card.meme::before     { background: linear-gradient(90deg, transparent, var(--clan-meme),     transparent); }
    .char-card.builders::before { background: linear-gradient(90deg, transparent, var(--clan-builders), transparent); }

    .char-top {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: 1rem;
    }

    .char-avatar {
      width: 56px; height: 56px;
      border-radius: 50%;
      background: var(--mid-light);
      border: 1.5px solid rgba(201,168,76,0.25);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.5rem;
      flex-shrink: 0;
    }

    .char-identity { flex: 1; }

    .char-name {
      font-size: 1.2rem;
      color: var(--cream);
      letter-spacing: 0.03em;
    }

    .char-coin {
      font-size: 0.6rem;
      letter-spacing: 0.22em;
      text-transform: uppercase;
      color: var(--gold);
      opacity: 0.55;
      margin-top: 0.1rem;
    }

    .char-title {
      font-size: 0.65rem;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--gold);
      opacity: 0.6;
      margin-top: 0.2rem;
    }

    .char-gate-badge {
      font-size: 0.58rem;
      letter-spacing: 0.1em;
      padding: 0.2rem 0.6rem;
      border-radius: 100px;
      border: 1px solid rgba(201,168,76,0.2);
      color: var(--gold);
      background: rgba(201,168,76,0.06);
      white-space: nowrap;
      flex-shrink: 0;
    }

    /* Heaven progress bar */
    .char-progress-wrap {
      margin: 0.75rem 0 1rem;
    }

    .char-progress-label {
      display: flex;
      justify-content: space-between;
      font-size: 0.6rem;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--cream-dim);
      opacity: 0.45;
      margin-bottom: 0.35rem;
    }

    .char-progress-bar {
      height: 3px;
      background: rgba(255,255,255,0.07);
      border-radius: 3px;
      overflow: hidden;
    }

    .char-progress-fill {
      height: 100%;
      border-radius: 3px;
      background: linear-gradient(90deg, var(--accent), var(--gold));
      transition: width 1s var(--ease-out);
    }

    .char-lore {
      font-size: 0.83rem;
      line-height: 1.74;
      color: var(--cream-dim);
      opacity: 0.72;
      margin-bottom: 1rem;
    }

    .char-gate-info {
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
      margin-bottom: 1rem;
    }

    .char-gate-pill {
      font-size: 0.6rem;
      letter-spacing: 0.1em;
      padding: 0.22rem 0.65rem;
      border-radius: 100px;
      background: rgba(201,168,76,0.06);
      border: 1px solid rgba(201,168,76,0.18);
      color: var(--cream-dim);
      opacity: 0.7;
    }

    .char-quote {
      font-style: italic;
      font-size: 0.8rem;
      color: var(--gold);
      opacity: 0.55;
      border-top: 1px solid rgba(201,168,76,0.1);
      padding-top: 0.9rem;
      line-height: 1.6;
    }

    /* Denethor — special styling */
    .char-card.fallen {
      border-color: rgba(224,93,93,0.15);
      background: rgba(20,10,10,0.6);
    }
    .char-card.fallen::before { background: linear-gradient(90deg, transparent, var(--red-down), transparent); }
    .char-card.fallen .char-name { color: var(--cream-dim); }
    .char-card.fallen .char-coin { color: var(--red-down); }

    .fallen-label {
      font-size: 0.58rem;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: var(--red-down);
      opacity: 0.7;
      padding: 0.18rem 0.55rem;
      border: 1px solid rgba(224,93,93,0.25);
      border-radius: 100px;
      background: rgba(224,93,93,0.07);
      flex-shrink: 0;
    }
  </style>
</head>
<body class="page-fellowship">

<div id="progress-bar" role="progressbar" aria-hidden="true"></div>

<nav id="nav" role="navigation" aria-label="Main navigation">
  <a href="/" class="nav-brand" aria-label="Coinor Chronicles home">
    <span class="nav-brand-crown" aria-hidden="true">♚</span>
    Coinor Chronicles
    <span class="nav-brand-sub">· The Realm of Coinor</span>
  </a>
  <ul class="nav-links" role="list">
    <li><a href="/the-fellowship.php" class="active">The Fellowship</a></li>
    <li><a href="/chronicle.php">The Chronicle</a></li>
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


<!-- PAGE HERO -->
<div class="page-hero">
  <div class="section-eyebrow">The Realm of Coinor</div>
  <h1>The Fellowship</h1>
  <p>Twenty-one companions. Four clans. One eternal quest.<br/>Each carries a different burden. All serve the same road.</p>
</div>


<!-- FELLOWSHIP PAGE -->
<main id="fellowship-page" aria-label="Fellowship roster">

  <!-- Clan filter tabs -->
  <div class="clan-tabs" role="tablist" aria-label="Filter by clan">
    <button class="clan-tab active" data-clan="all"      role="tab" aria-selected="true">All Companions</button>
    <button class="clan-tab" data-clan="ancients" role="tab" aria-selected="false">Clan of the Ancients</button>
    <button class="clan-tab" data-clan="swift"    role="tab" aria-selected="false">Clan of the Swift</button>
    <button class="clan-tab" data-clan="meme"     role="tab" aria-selected="false">Clan of Meme Lords</button>
    <button class="clan-tab" data-clan="builders" role="tab" aria-selected="false">Clan of the Builders</button>
  </div>


  <!-- ── CLAN OF THE ANCIENTS ── -->
  <section class="clan-section ancients" id="ancients" data-clan="ancients" aria-labelledby="ancients-heading">
    <div class="clan-header">
      <span class="clan-icon" aria-hidden="true">🏰</span>
      <div class="clan-header-text">
        <h2 id="ancients-heading">Clan of the Ancients</h2>
        <p>The Obsidian Citadel · BTC · ETH · LTC · BNB · XRP</p>
      </div>
    </div>
    <div class="characters-grid">

      <article class="char-card ancients" aria-label="Aragorn character card">
        <div class="char-top">
          <div class="char-avatar" aria-hidden="true">⚔</div>
          <div class="char-identity">
            <div class="char-name">Aragorn</div>
            <div class="char-coin">Bitcoin · BTC</div>
            <div class="char-title">The Returned King · Narrator</div>
          </div>
          <span class="char-gate-badge">Heaven 7 · Gate I</span>
        </div>
        <div class="char-progress-wrap">
          <div class="char-progress-label"><span>Gate I ($100k)</span><span>Gate II ($1M)</span></div>
          <div class="char-progress-bar"><div class="char-progress-fill" style="width:7%"></div></div>
        </div>
        <div class="char-gate-info">
          <span class="char-gate-pill">Gate 1 reached · driven back</span>
          <span class="char-gate-pill">Reclaiming the throne</span>
        </div>
        <p class="char-lore">The narrator. The anchor. He has watched every Golden Season, every Dark Siege since the first age of the realm. His voice never wavers. He gives perspective when others give panic. Aragorn does not represent Bitcoin — Aragorn is Bitcoin.</p>
        <blockquote class="char-quote">"Sit, young Pepe. Let me tell you what the roads whispered today."</blockquote>
      </article>

      <article class="char-card ancients" aria-label="Gandalf character card">
        <div class="char-top">
          <div class="char-avatar" aria-hidden="true">🔮</div>
          <div class="char-identity">
            <div class="char-name">Gandalf</div>
            <div class="char-coin">Ethereum · ETH</div>
            <div class="char-title">The Guide · Clan of the Ancients</div>
          </div>
          <span class="char-gate-badge">Heaven 4 · Gate I</span>
        </div>
        <div class="char-progress-wrap">
          <div class="char-progress-label"><span>Gate I ($10k)</span><span>Gate II ($100k)</span></div>
          <div class="char-progress-bar"><div class="char-progress-fill" style="width:30%"></div></div>
        </div>
        <div class="char-gate-info">
          <span class="char-gate-pill">The Merge completed</span>
          <span class="char-gate-pill">Grey → White transformation</span>
        </div>
        <p class="char-lore">Wise. Philosophical. The bridges Gandalf builds outlast every siege. His Transformation — from Grey to White — is the most significant event in his legend. He speaks rarely, but when he speaks, the fellowship listens.</p>
        <blockquote class="char-quote">"The bridges I have built will outlast this siege. The work continues."</blockquote>
      </article>

      <article class="char-card ancients" aria-label="Samwise character card">
        <div class="char-top">
          <div class="char-avatar" aria-hidden="true">🌿</div>
          <div class="char-identity">
            <div class="char-name">Samwise</div>
            <div class="char-coin">Litecoin · LTC</div>
            <div class="char-title">The Loyal · Clan of the Ancients</div>
          </div>
          <span class="char-gate-badge">Heaven 2 · Gate I</span>
        </div>
        <div class="char-progress-wrap">
          <div class="char-progress-label"><span>Gate I ($1k)</span><span>Gate II ($10k)</span></div>
          <div class="char-progress-bar"><div class="char-progress-fill" style="width:18%"></div></div>
        </div>
        <p class="char-lore">The faithful one. Where Aragorn goes, Samwise follows. Never the most spoken of — but always there when it matters. He carries provisions when others drop theirs. The first companion and the most enduring.</p>
        <blockquote class="char-quote">"I'm not going back without you, Mr. Aragorn. Not until this road is finished."</blockquote>
      </article>

      <article class="char-card ancients" aria-label="Elrond character card">
        <div class="char-top">
          <div class="char-avatar" aria-hidden="true">🌟</div>
          <div class="char-identity">
            <div class="char-name">Elrond</div>
            <div class="char-coin">BNB · Binance Coin</div>
            <div class="char-title">The Council Master · Clan of the Ancients</div>
          </div>
          <span class="char-gate-badge">Heaven 6 · Gate I</span>
        </div>
        <div class="char-progress-wrap">
          <div class="char-progress-label"><span>Gate I ($10k)</span><span>Gate II ($100k)</span></div>
          <div class="char-progress-bar"><div class="char-progress-fill" style="width:58%"></div></div>
        </div>
        <p class="char-lore">Lord of Rivendell — the great council hall where all roads intersect. Elrond commands the largest trading hall in the realm. His exchange is where the fellowship replenishes. Where others see a market, Elrond sees infrastructure.</p>
        <blockquote class="char-quote">"This council was not called for debate. It was called for decisions."</blockquote>
      </article>

      <article class="char-card ancients" aria-label="Boromir character card">
        <div class="char-top">
          <div class="char-avatar" aria-hidden="true">🛡</div>
          <div class="char-identity">
            <div class="char-name">Boromir</div>
            <div class="char-coin">XRP · Ripple</div>
            <div class="char-title">The Embattled · Clan of the Ancients</div>
          </div>
          <span class="char-gate-badge">Heaven 3 · Gate I</span>
        </div>
        <div class="char-progress-wrap">
          <div class="char-progress-label"><span>Gate I ($100)</span><span>Gate II ($1k)</span></div>
          <div class="char-progress-bar"><div class="char-progress-fill" style="width:24%"></div></div>
        </div>
        <div class="char-gate-info">
          <span class="char-gate-pill">Battle with Eagle Warriors</span>
          <span class="char-gate-pill">SEC lawsuit lore</span>
        </div>
        <p class="char-lore">The embattled defender. He fought the Eagle Warriors for years and emerged battered but unbroken. Every legal ruling in the realm is an update to his battle. His scars are his credentials — no other companion has faced what he has faced and continued walking.</p>
        <blockquote class="char-quote">"They tried to break me with their laws. I am still on the road."</blockquote>
      </article>

    </div>
  </section>


  <!-- ── CLAN OF THE SWIFT ── -->
  <section class="clan-section swift" id="swift" data-clan="swift" aria-labelledby="swift-heading">
    <div class="clan-header">
      <span class="clan-icon" aria-hidden="true">⚡</span>
      <div class="clan-header-text">
        <h2 id="swift-heading">Clan of the Swift</h2>
        <p>The Electric Plains · SOL · TON · AVAX · XLM · TRX</p>
      </div>
    </div>
    <div class="characters-grid">

      <article class="char-card swift" aria-label="Legolas character card">
        <div class="char-top">
          <div class="char-avatar" aria-hidden="true">🏹</div>
          <div class="char-identity">
            <div class="char-name">Legolas</div>
            <div class="char-coin">Solana · SOL</div>
            <div class="char-title">The Swift · First Rider</div>
          </div>
          <span class="char-gate-badge">Heaven 2 · Gate I</span>
        </div>
        <div class="char-progress-wrap">
          <div class="char-progress-label"><span>Gate I ($1k)</span><span>Gate II ($10k)</span></div>
          <div class="char-progress-bar"><div class="char-progress-fill" style="width:17%"></div></div>
        </div>
        <p class="char-lore">First to move. First to arrive. His speed across the Electric Plains is a thing of legend. He saw the ambush three cycles before it reached the others. When the plains glow blue with lightning, it means Legolas is riding.</p>
        <blockquote class="char-quote">"Speed is not recklessness — it is preparation made manifest."</blockquote>
      </article>

      <article class="char-card swift" aria-label="Eomer character card">
        <div class="char-top">
          <div class="char-avatar" aria-hidden="true">🐴</div>
          <div class="char-identity">
            <div class="char-name">Eomer</div>
            <div class="char-coin">TON · Telegram Open Network</div>
            <div class="char-title">The Rider · Clan of the Swift</div>
          </div>
          <span class="char-gate-badge">Heaven 4 · Gate I</span>
        </div>
        <div class="char-progress-wrap">
          <div class="char-progress-label"><span>Gate I ($10)</span><span>Gate II ($100)</span></div>
          <div class="char-progress-bar"><div class="char-progress-fill" style="width:36%"></div></div>
        </div>
        <p class="char-lore">The marshal of the plains. Where Legolas is lightning, Eomer is the thunder that follows. He commands riders that reach every corner of the realm. His network is vast, his message loud, his speed matched only by his ambition.</p>
        <blockquote class="char-quote">"My riders have reached the far edges of the realm. The message is delivered."</blockquote>
      </article>

      <article class="char-card swift" aria-label="Eowyn character card">
        <div class="char-top">
          <div class="char-avatar" aria-hidden="true">🗡</div>
          <div class="char-identity">
            <div class="char-name">Eowyn</div>
            <div class="char-coin">AVAX · Avalanche</div>
            <div class="char-title">The Shield-maiden · Clan of the Swift</div>
          </div>
          <span class="char-gate-badge">Heaven 3 · Gate I</span>
        </div>
        <div class="char-progress-wrap">
          <div class="char-progress-label"><span>Gate I ($500)</span><span>Gate II ($5k)</span></div>
          <div class="char-progress-bar"><div class="char-progress-fill" style="width:25%"></div></div>
        </div>
        <p class="char-lore">She who broke the siege that could not be broken. Where others doubted the Electric Plains could produce a true warrior, Eowyn proved them wrong. Her defenses are layered and her counterattacks swift.</p>
        <blockquote class="char-quote">"I am no man. And this siege is no match for me."</blockquote>
      </article>

      <article class="char-card swift" aria-label="Faramir character card">
        <div class="char-top">
          <div class="char-avatar" aria-hidden="true">🌠</div>
          <div class="char-identity">
            <div class="char-name">Faramir</div>
            <div class="char-coin">XLM · Stellar</div>
            <div class="char-title">The Quiet Captain · Clan of the Swift</div>
          </div>
          <span class="char-gate-badge">Heaven 2 · Gate I</span>
        </div>
        <div class="char-progress-wrap">
          <div class="char-progress-label"><span>Gate I ($5)</span><span>Gate II ($50)</span></div>
          <div class="char-progress-bar"><div class="char-progress-fill" style="width:14%"></div></div>
        </div>
        <p class="char-lore">The bridge-builder. While others seek speed, Faramir seeks connection. His stellar network links distant parts of the realm that would otherwise never meet. Underestimated by the crowd, indispensable to the fellowship.</p>
        <blockquote class="char-quote">"Not all bridges are made of stone. Some are made of trust."</blockquote>
      </article>

      <article class="char-card swift" aria-label="Saruman character card">
        <div class="char-top">
          <div class="char-avatar" aria-hidden="true">🌀</div>
          <div class="char-identity">
            <div class="char-name">Saruman</div>
            <div class="char-coin">TRX · TRON</div>
            <div class="char-title">The Operator · Clan of the Swift</div>
          </div>
          <span class="char-gate-badge">Heaven 5 · Gate I</span>
        </div>
        <div class="char-progress-wrap">
          <div class="char-progress-label"><span>Gate I ($1)</span><span>Gate II ($10)</span></div>
          <div class="char-progress-bar"><div class="char-progress-fill" style="width:45%"></div></div>
        </div>
        <p class="char-lore">A wizard of great industry, once of the order but now walking his own road. His towers process more transactions than many care to admit. He does not inspire loyalty — he buys it. And somehow, that works.</p>
        <blockquote class="char-quote">"My machines run whether you praise them or not. Results speak."</blockquote>
      </article>

    </div>
  </section>


  <!-- ── CLAN OF MEME LORDS ── -->
  <section class="clan-section meme" id="meme" data-clan="meme" aria-labelledby="meme-heading">
    <div class="clan-header">
      <span class="clan-icon" aria-hidden="true">🔥</span>
      <div class="clan-header-text">
        <h2 id="meme-heading">Clan of Meme Lords</h2>
        <p>Kekiston Bazaar · PEPE · SHIB · DOGE · FLOKI · NOT · BOME · BONK</p>
      </div>
    </div>
    <div class="characters-grid">

      <article class="char-card meme" aria-label="Tom Bombadil character card">
        <div class="char-top">
          <div class="char-avatar" aria-hidden="true">🐸</div>
          <div class="char-identity">
            <div class="char-name">Tom Bombadil</div>
            <div class="char-coin">PEPE coin</div>
            <div class="char-title">The Master · Oldest Being in the Realm</div>
          </div>
          <span class="char-gate-badge">Heaven 3 · Gate I</span>
        </div>
        <div class="char-progress-wrap">
          <div class="char-progress-label"><span>Gate I ($1.00)</span><span>Gate II ($10)</span></div>
          <div class="char-progress-bar"><div class="char-progress-fill" style="width:20%"></div></div>
        </div>
        <p class="char-lore">Ancient. Chaotic. Outside the laws that govern others. The One Ring — the greatest power in the realm — means nothing to Tom. He is the oldest being, beholden to no clan, no rule, no logic. His power lies in the laughter of thousands.</p>
        <blockquote class="char-quote">"Hey dol! Merry dol! Ring-a-dong-dillo! Tom doesn't worry about such things!"</blockquote>
      </article>

      <article class="char-card meme" aria-label="Merry character card">
        <div class="char-top">
          <div class="char-avatar" aria-hidden="true">🐕</div>
          <div class="char-identity">
            <div class="char-name">Merry</div>
            <div class="char-coin">SHIB · Shiba Inu</div>
            <div class="char-title">The Impossible Dreamer · Clan of Meme Lords</div>
          </div>
          <span class="char-gate-badge">Heaven 2 · Gate I</span>
        </div>
        <div class="char-progress-wrap">
          <div class="char-progress-label"><span>Gate I ($0.001)</span><span>Gate II ($0.01)</span></div>
          <div class="char-progress-bar"><div class="char-progress-fill" style="width:12%"></div></div>
        </div>
        <div class="char-gate-info">
          <span class="char-gate-pill">The $1.00 Dream</span>
        </div>
        <p class="char-lore">The companion who carries the impossible dream. His $1.00 dream is the fellowship's great open question. Most think it can't happen. Merry thinks only about the next Heaven. If he ever reaches $1.00, it is the most celebrated moment in Coinor Chronicles history.</p>
        <blockquote class="char-quote">"I just need to still be on the road when the others have stopped."</blockquote>
      </article>

      <article class="char-card meme" aria-label="Pippin character card">
        <div class="char-top">
          <div class="char-avatar" aria-hidden="true">🎩</div>
          <div class="char-identity">
            <div class="char-name">Pippin</div>
            <div class="char-coin">DOGE · Dogecoin</div>
            <div class="char-title">The Survivor · A Fool of a Took</div>
          </div>
          <span class="char-gate-badge">Heaven 6 · Gate I</span>
        </div>
        <div class="char-progress-wrap">
          <div class="char-progress-label"><span>Gate I ($10)</span><span>Gate II ($100)</span></div>
          <div class="char-progress-bar"><div class="char-progress-fill" style="width:52%"></div></div>
        </div>
        <p class="char-lore">A fool of a Took, they called him. He has outlasted half the fellowship's doubters. Cheerful. Battle-hardened. Still on the road. He was the meme before memes were respected. The longest-surviving wanderer in the Bazaar.</p>
        <blockquote class="char-quote">"I'm still here. I'm always still here."</blockquote>
      </article>

      <article class="char-card meme" aria-label="Theoden character card">
        <div class="char-top">
          <div class="char-avatar" aria-hidden="true">👑</div>
          <div class="char-identity">
            <div class="char-name">Theoden</div>
            <div class="char-coin">FLOKI · Floki Inu</div>
            <div class="char-title">The King Reawakened · Clan of Meme Lords</div>
          </div>
          <span class="char-gate-badge">Heaven 1 · Gate I</span>
        </div>
        <div class="char-progress-wrap">
          <div class="char-progress-label"><span>Gate I ($1.00)</span><span>Gate II ($10)</span></div>
          <div class="char-progress-bar"><div class="char-progress-fill" style="width:8%"></div></div>
        </div>
        <p class="char-lore">The king who was enchanted, then freed. His rise from obscurity to relevance mirrors the legendary Return of the King. When Theoden rides, the Rohirrim ride with him. His charge is famous for its surprise and its fury.</p>
        <blockquote class="char-quote">"Ride now! Ride to ruin and the world's ending!"</blockquote>
      </article>

      <article class="char-card meme" aria-label="Frodo character card">
        <div class="char-top">
          <div class="char-avatar" aria-hidden="true">💍</div>
          <div class="char-identity">
            <div class="char-name">Frodo</div>
            <div class="char-coin">NOT · Notcoin</div>
            <div class="char-title">The Ring-bearer · Clan of Meme Lords</div>
          </div>
          <span class="char-gate-badge">Heaven 1 · Gate I</span>
        </div>
        <div class="char-progress-wrap">
          <div class="char-progress-label"><span>Gate I ($0.10)</span><span>Gate II ($1.00)</span></div>
          <div class="char-progress-bar"><div class="char-progress-fill" style="width:9%"></div></div>
        </div>
        <p class="char-lore">He carries a burden no one else can carry. Not for power — because he was chosen. The gate ahead is heavy. Every Heaven gained costs more than the last. But Frodo does not stop. He cannot stop.</p>
        <blockquote class="char-quote">"I will take the Ring to Mordor, though I do not know the way."</blockquote>
      </article>

      <article class="char-card meme" aria-label="Bilbo Baggins character card">
        <div class="char-top">
          <div class="char-avatar" aria-hidden="true">📜</div>
          <div class="char-identity">
            <div class="char-name">Bilbo Baggins</div>
            <div class="char-coin">BOME · Book of Meme</div>
            <div class="char-title">The Chronicler · Keeper of the Book</div>
          </div>
          <span class="char-gate-badge">Heaven 1 · Gate I</span>
        </div>
        <div class="char-progress-wrap">
          <div class="char-progress-label"><span>Gate I ($0.10)</span><span>Gate II ($1.00)</span></div>
          <div class="char-progress-bar"><div class="char-progress-fill" style="width:11%"></div></div>
        </div>
        <div class="char-gate-info">
          <span class="char-gate-pill">This website is his Book</span>
        </div>
        <p class="char-lore">The archivist of the realm. This very chronicle is Bilbo's book. Every story published makes the Book of Meme heavier — and Bilbo's position stronger. He is the only companion who gains power from storytelling itself.</p>
        <blockquote class="char-quote">"A story worth telling is worth recording properly."</blockquote>
      </article>

      <article class="char-card meme" aria-label="Gollum character card">
        <div class="char-top">
          <div class="char-avatar" aria-hidden="true">👁</div>
          <div class="char-identity">
            <div class="char-name">Gollum</div>
            <div class="char-coin">BONK · Bonk</div>
            <div class="char-title">The Wanderer · No Clan Claims Him</div>
          </div>
          <span class="char-gate-badge">Heaven 2 · Gate I</span>
        </div>
        <div class="char-progress-wrap">
          <div class="char-progress-label"><span>Gate I ($1.00)</span><span>Gate II ($10)</span></div>
          <div class="char-progress-bar"><div class="char-progress-fill" style="width:15%"></div></div>
        </div>
        <p class="char-lore">He creeps through the edges of the Bazaar. No clan claims him. He claims no clan. His precious provisions vanish and reappear without logic. The fellowship watches him with equal parts pity and alarm.</p>
        <blockquote class="char-quote">"Preciousss… they bonked us, precious. But we knows the way."</blockquote>
      </article>

    </div>
  </section>


  <!-- ── CLAN OF THE BUILDERS ── -->
  <section class="clan-section builders" id="builders" data-clan="builders" aria-labelledby="builders-heading">
    <div class="clan-header">
      <span class="clan-icon" aria-hidden="true">⚒</span>
      <div class="clan-header-text">
        <h2 id="builders-heading">Clan of the Builders</h2>
        <p>The Forge of Chains · ADA · SUI · JASMY · LINK · UNI</p>
      </div>
    </div>
    <div class="characters-grid">

      <article class="char-card builders" aria-label="Treebeard character card">
        <div class="char-top">
          <div class="char-avatar" aria-hidden="true">🌳</div>
          <div class="char-identity">
            <div class="char-name">Treebeard</div>
            <div class="char-coin">ADA · Cardano</div>
            <div class="char-title">The Slow Builder · Ancient of the Forge</div>
          </div>
          <span class="char-gate-badge">Heaven 5 · Gate I</span>
        </div>
        <div class="char-progress-wrap">
          <div class="char-progress-label"><span>Gate I ($10)</span><span>Gate II ($100)</span></div>
          <div class="char-progress-bar"><div class="char-progress-fill" style="width:44%"></div></div>
        </div>
        <p class="char-lore">Do not be hasty. The Ents move slowly but they move with purpose. Treebeard has been building roads since before the Electric Plains existed. His critics say he moves too slow. His roads say otherwise. The foundation holds.</p>
        <blockquote class="char-quote">"We Ents do not make hasty decisions. The road must be sound before any traveler walks it."</blockquote>
      </article>

      <article class="char-card builders" aria-label="Gimli character card">
        <div class="char-top">
          <div class="char-avatar" aria-hidden="true">⛏</div>
          <div class="char-identity">
            <div class="char-name">Gimli</div>
            <div class="char-coin">SUI · Sui Network</div>
            <div class="char-title">The Miner · Clan of the Builders</div>
          </div>
          <span class="char-gate-badge">Heaven 3 · Gate I</span>
        </div>
        <div class="char-progress-wrap">
          <div class="char-progress-label"><span>Gate I ($10)</span><span>Gate II ($100)</span></div>
          <div class="char-progress-bar"><div class="char-progress-fill" style="width:28%"></div></div>
        </div>
        <p class="char-lore">He digs. He mines. He builds where others refuse to go. His speed in the deep forge surprises those who dismissed him. Gimli does not seek elegance — he seeks throughput. The forge never sleeps when Gimli holds the hammer.</p>
        <blockquote class="char-quote">"Nobody tosses a Gimli. Nobody tosses the Forge either."</blockquote>
      </article>

      <article class="char-card builders" aria-label="Galadriel character card">
        <div class="char-top">
          <div class="char-avatar" aria-hidden="true">✨</div>
          <div class="char-identity">
            <div class="char-name">Galadriel</div>
            <div class="char-coin">JASMY · JasmyCoin</div>
            <div class="char-title">The Seer · Lady of the Forge</div>
          </div>
          <span class="char-gate-badge">Heaven 1 · Gate I</span>
        </div>
        <div class="char-progress-wrap">
          <div class="char-progress-label"><span>Gate I ($1)</span><span>Gate II ($10)</span></div>
          <div class="char-progress-bar"><div class="char-progress-fill" style="width:6%"></div></div>
        </div>
        <p class="char-lore">She sees what others cannot. The mirror shows things that were, things that are, and things that yet may be. Her path is long and her gate distant — but no one doubts she will walk it. Her data protects the fellowship in ways they do not always notice.</p>
        <blockquote class="char-quote">"I pass the test. I will diminish and go into the west — but not yet."</blockquote>
      </article>

    </div>
  </section>


  <!-- ── THE FALLEN — DENETHOR ── -->
  <section class="clan-section" style="margin-top:1rem;" aria-labelledby="fallen-heading">
    <div class="clan-header" style="border-color:rgba(224,93,93,0.2);">
      <span class="clan-icon" style="color:var(--red-down);" aria-hidden="true">💀</span>
      <div class="clan-header-text">
        <h2 id="fallen-heading" style="color:var(--red-down);">The Fallen</h2>
        <p>No clan. A legend and a warning.</p>
      </div>
    </div>
    <div class="characters-grid" style="max-width:400px;">
      <article class="char-card fallen" aria-label="Denethor character card">
        <div class="char-top">
          <div class="char-avatar" style="border-color:rgba(224,93,93,0.3);" aria-hidden="true">🔥</div>
          <div class="char-identity">
            <div class="char-name" style="color:var(--cream-dim);">Denethor</div>
            <div class="char-coin" style="color:var(--red-down);">LUNC · Terra Classic</div>
            <div class="char-title" style="color:var(--red-down);opacity:0.6;">The Steward · The Warning</div>
          </div>
          <span class="fallen-label">The Fallen</span>
        </div>
        <p class="char-lore">The Steward who looked too long into the Palantir and lost everything. His collapse is the story Aragorn references when companions grow reckless. Do not look too long into the dark mirror. Do not mistake speed for wisdom. Do not mistake leverage for power. Denethor did all three.</p>
        <blockquote class="char-quote">"I will not bow to this. I will go to my—" — The chronicle ends here.</blockquote>
      </article>
    </div>
  </section>

</main>


<!-- ═══════════════════════════════════════════════════
     FOOTER
═══════════════════════════════════════════════════ -->
<footer id="footer" role="contentinfo">
  <div class="footer-inner">
    <div class="footer-brand">
      <h3>Coinor Chronicles</h3>
      <p>An AI media empire. The markets of the Realm of Coinor, told as an eternal fantasy saga. Twenty-one companions. One road. Updated every six hours.</p>
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
        <li><a href="#ancients">Clan of the Ancients</a></li>
        <li><a href="#swift">Clan of the Swift</a></li>
        <li><a href="#meme">Clan of Meme Lords</a></li>
        <li><a href="#builders">Clan of the Builders</a></li>
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
  /* Nav */
  const nav = document.getElementById('nav');
  window.addEventListener('scroll', function() { nav.classList.toggle('scrolled', window.scrollY > 60); }, { passive:true });

  /* Mobile menu */
  const toggle = document.querySelector('.nav-toggle');
  const menu   = document.getElementById('mobile-menu');
  if (toggle && menu) {
    toggle.addEventListener('click', function() {
      const open = menu.classList.toggle('open');
      toggle.classList.toggle('open', open);
      toggle.setAttribute('aria-expanded', String(open));
    });
  }

  /* Progress bar */
  const bar = document.getElementById('progress-bar');
  window.addEventListener('scroll', function() {
    const s = document.documentElement.scrollTop;
    const t = document.documentElement.scrollHeight - window.innerHeight;
    if (bar && t > 0) bar.style.width = (s/t*100) + '%';
  }, { passive:true });

  /* Clan filter tabs */
  const tabs     = document.querySelectorAll('.clan-tab');
  const sections = document.querySelectorAll('.clan-section[data-clan]');

  tabs.forEach(function(tab) {
    tab.addEventListener('click', function() {
      const clan = tab.dataset.clan;
      tabs.forEach(function(t) { t.classList.remove('active'); t.setAttribute('aria-selected','false'); });
      tab.classList.add('active');
      tab.setAttribute('aria-selected','true');

      sections.forEach(function(sec) {
        if (clan === 'all' || sec.dataset.clan === clan) {
          sec.style.display = '';
        } else {
          sec.style.display = 'none';
        }
      });
    });
  });

  /* Progress bar animation on scroll into view */
  if ('IntersectionObserver' in window) {
    const obs = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (entry.isIntersecting) {
          const fill = entry.target;
          fill.style.width = fill.getAttribute('data-width') || fill.style.width;
          obs.unobserve(fill);
        }
      });
    }, { threshold: 0.3 });

    document.querySelectorAll('.char-progress-fill').forEach(function(fill) {
      const w = fill.style.width;
      fill.setAttribute('data-width', w);
      fill.style.width = '0%';
      obs.observe(fill);
    });
  }
}());
</script>
</body>
</html>
