<?php
require __DIR__ . '/lib.php';
$n = isset($_GET['n']) ? (int)$_GET['n'] : 0;
$eps = episodes();
$e = null; $idx = -1;
foreach ($eps as $i => $x) { if ((int)$x['number'] === $n) { $e = $x; $idx = $i; break; } }
if (!$e) not_found();
$clan = $CLANS[$e['clan'] ?? ''] ?? null;
$cond = $CONDITIONS[$e['condition'] ?? ''] ?? '';
$newer = $eps[$idx - 1] ?? null; $older = $eps[$idx + 1] ?? null;
$desc = excerpt($e['body']);
$path = '/episode/' . $n;
page_open([
  'title' => $e['title'] . ' · Episode ' . $n . ' · CoinorChronicles', 'desc' => $desc, 'path' => $path, 'og' => 'article',
  'jsonld' => [
    '@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $e['title'], 'description' => $desc,
    'datePublished' => $e['published_at'] ?? ($e['date'] ?? null), 'inLanguage' => 'en', 'url' => SITE . $path,
    'mainEntityOfPage' => SITE . $path, 'author' => ['@type' => 'Organization', 'name' => 'CoinorChronicles'],
    'publisher' => ['@type' => 'Organization', 'name' => 'MG Holdings'],
    'isPartOf' => ['@type' => 'CreativeWorkSeries', 'name' => 'The Book of Meme'],
    'genre' => 'Fantasy', 'position' => $n,
  ],
]);
echo '<article class="episode clan-' . h(preg_replace('/[^a-z_]/', '', $e['clan'] ?? '')) . '"><p class="back"><a href="/">&larr; All chronicles</a></p>';
echo '<p class="meta"><span class="tag">Episode ' . $n . '</span>';
if ($clan) echo '<a href="/clan/' . h($e['clan']) . '">' . h($clan['name']) . '</a>';
echo '<span>' . h($cond) . '</span><span>' . h(fmt_date($e['date'] ?? '')) . '</span></p>';
echo '<h1>' . h($e['title']) . '</h1><div class="body">';
foreach (paragraphs($e['body']) as $p) echo '<p>' . inline($p) . '</p>';
echo '</div><div class="pager">';
echo $older ? '<a class="btn" href="/episode/' . (int)$older['number'] . '">&larr; Episode ' . (int)$older['number'] . '</a>' : '<span></span>';
echo $newer ? '<a class="btn" href="/episode/' . (int)$newer['number'] . '">Episode ' . (int)$newer['number'] . ' &rarr;</a>' : '<span class="muted">The newest chapter</span>';
echo '</div></article>';
page_close();
