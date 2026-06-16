<?php
// Usage: require_once __DIR__ . '/includes/header.php';
// Set $page_title, $page_desc, $page_class before including.
$page_title = $page_title ?? 'Coinor Chronicles — The Realm Awaits';
$page_desc  = $page_desc  ?? 'The daily epic saga of the Realm of Coinor. Twenty-one companions. One eternal quest. Updated every six hours.';
$page_class = $page_class ?? '';
$canonical  = $canonical  ?? SITE_URL . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="description" content="<?= htmlspecialchars($page_desc) ?>"/>
  <meta name="theme-color" content="#0d0c0a"/>

  <!-- Open Graph -->
  <meta property="og:type"        content="website"/>
  <meta property="og:site_name"   content="Coinor Chronicles"/>
  <meta property="og:title"       content="<?= htmlspecialchars($page_title) ?>"/>
  <meta property="og:description" content="<?= htmlspecialchars($page_desc) ?>"/>
  <meta property="og:url"         content="<?= htmlspecialchars($canonical) ?>"/>
  <meta property="og:image"       content="<?= SITE_URL ?>/assets/images/og-card.jpg"/>

  <!-- Twitter -->
  <meta name="twitter:card"        content="summary_large_image"/>
  <meta name="twitter:title"       content="<?= htmlspecialchars($page_title) ?>"/>
  <meta name="twitter:description" content="<?= htmlspecialchars($page_desc) ?>"/>
  <meta name="twitter:image"       content="<?= SITE_URL ?>/assets/images/og-card.jpg"/>

  <link rel="canonical" href="<?= htmlspecialchars($canonical) ?>"/>
  <title><?= htmlspecialchars($page_title) ?></title>

  <!-- Preconnect -->
  <link rel="preconnect" href="https://cdnjs.cloudflare.com"/>

  <!-- Global CSS -->
  <link rel="stylesheet" href="/assets/css/main.css"/>

  <!-- GSAP -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js" defer></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js" defer></script>
</head>
<body class="<?= htmlspecialchars($page_class) ?>">

<div id="progress-bar" role="progressbar" aria-hidden="true"></div>

<nav id="nav" role="navigation" aria-label="Main navigation">
  <a href="/" class="nav-brand" aria-label="Coinor Chronicles home">
    <span class="nav-brand-crown" aria-hidden="true">♚</span>
    Coinor Chronicles
    <span class="nav-brand-sub">· The Realm of Coinor</span>
  </a>

  <ul class="nav-links" role="list">
    <li><a href="/the-fellowship.php" class="<?= basename($_SERVER['PHP_SELF']) === 'the-fellowship.php' ? 'active' : '' ?>">The Fellowship</a></li>
    <li><a href="/chronicle.php"      class="<?= basename($_SERVER['PHP_SELF']) === 'chronicle.php'      ? 'active' : '' ?>">The Chronicle</a></li>
    <li><a href="/lore.html"          class="<?= basename($_SERVER['PHP_SELF']) === 'lore.html'          ? 'active' : '' ?>">Lore</a></li>
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
