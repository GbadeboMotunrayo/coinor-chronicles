<?php
require __DIR__ . '/lib.php';
$k = preg_replace('/[^a-z_]/', '', (string)($_GET['k'] ?? ''));
if (!isset($CLANS[$k])) not_found('That clan is not on the map');
$c = $CLANS[$k];
$mine = array_values(array_filter(episodes(), function ($e) use ($k) { return ($e['clan'] ?? '') === $k; }));
page_open([
  'title' => $c['name'] . ' · ' . $c['place'] . ' · Realm of Coinor',
  'desc' => $c['name'] . ' of the Realm of Coinor, from ' . $c['place'] . '. ' . $c['blurb'] . ' Read their chapters in the Book of Meme, an ongoing fantasy saga.',
  'path' => '/clan/' . $k, 'nav' => 'realm',
]);
echo '<section class="clan clan-' . h($k) . '"><p class="back"><a href="/realm">&larr; The Realm</a></p><p class="eyebrow">The Realm of Coinor</p>';
echo '<h1>' . h($c['name']) . '</h1><p class="where">' . h($c['place']) . '</p><p class="lede">' . h($c['blurb']) . '</p><ul>';
foreach ($c['who'] as $w) echo '<li><b>' . h($w[0]) . '</b> · ' . h($w[1]) . '</li>';
echo '</ul></section><h2>Chapters from this clan</h2>';
if ($mine) { echo '<div class="cards">'; foreach ($mine as $e) echo card_html($e); echo '</div>'; }
else echo '<div class="empty"><p>No chapter from this clan has been written yet. New chapters appear here by themselves.</p></div>';
page_close();
