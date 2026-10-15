<?php
require __DIR__ . '/lib.php';
page_open([
  'title' => 'The Realm of Coinor · Clans, travellers and Golden Gates · CoinorChronicles',
  'desc' => 'Meet the four clans of the Realm of Coinor and the travellers who chase the Golden Gates in the Book of Meme, an ongoing fantasy saga.',
  'path' => '/realm', 'nav' => 'realm',
]);
echo '<p class="eyebrow">The Realm</p><h1>The Realm of Coinor</h1>';
echo '<p class="lede">Travellers walk toward Golden Gates: great milestones that lift them to a new height of power. Between one Gate and the next lie ten Heavens. The journey never ends, because a new Gate always appears beyond the last.</p>';
echo '<h2>The market weather</h2><p>Each chapter is set in one of three conditions. The Golden Season is a rising market, the Dark Siege is a falling one, and in the Waiting Plains nothing moves enough to tell, so no chapter is written.</p><h2>The clans</h2>';
foreach ($CLANS as $k => $c) {
  echo '<section class="clan clan-' . h($k) . '"><h3><a href="/clan/' . h($k) . '">' . h($c['name']) . '</a></h3><p class="where">' . h($c['place']) . '</p><p style="margin:.2em 0">' . h($c['blurb']) . '</p><ul>';
  foreach ($c['who'] as $w) echo '<li><b>' . h($w[0]) . '</b> · ' . h($w[1]) . '</li>';
  echo '</ul></section>';
}
echo '<p class="muted" style="font-size:.95rem">Each traveller is a character who stands for a real digital asset. The stories use public price movement as material. They are fiction and are not advice.</p>';
page_close();
