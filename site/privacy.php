<?php
require __DIR__ . '/lib.php';
page_open([
  'title' => 'Privacy · CoinorChronicles',
  'desc' => 'What CoinorChronicles keeps when you join the newsletter: your email address and when you agreed. Nothing is shared or sold. One click to leave.',
  'path' => '/privacy',
]);
echo '<p class="eyebrow">Privacy</p><h1>How we treat your address</h1>';
echo '<h2>What we keep</h2><p>If you join the newsletter, your email address, the wording you agreed to, and the time you agreed and confirmed. Nothing else. This website sets no cookies and has no analytics or trackers. The emails carry no tracking pixels and no click tracking.</p>';
echo '<h2>Why</h2><p>Only to send you the confirmation email and the weekly newsletter with the week\'s new chapters. We do not share, sell or rent your address.</p>';
echo '<h2>Where and for how long</h2><p>On our own server, run by MG Holdings. We keep your address until you unsubscribe. A request you never confirm is deleted after 7 days. After you unsubscribe we keep the address marked as unsubscribed, so we never email you again by mistake. If you want it erased completely, ask and we will delete it.</p>';
echo '<h2>Your choices</h2><p>Every email has a one-click unsubscribe link. To see, correct or erase what we hold about you, write to <a href="mailto:echo@mgholdings.tech">echo@mgholdings.tech</a>.</p>';
echo '<h2>Support gifts</h2><p>If you send a gift from the <a href="/support">Support page</a>, it happens on a public blockchain between you and our wallet. We receive no name or email from it.</p>';
page_close(false);
