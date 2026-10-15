<?php
require __DIR__ . '/lib.php';
$k = (string)($_GET['k'] ?? '');
$N = [
  'confirmed' => ['You are in', 'Thank you. Your place in the Book of Meme is confirmed. The next Scroll arrives on Sunday.'],
  'unsubscribed' => ['You have left the list', 'You will not get any more emails from us. If that was a mistake, you can join again from the home page.'],
  'invalid' => ['That link did not work', 'It may have been used already, or it has expired. If you wanted to join, please sign up again from the home page.'],
];
if (!isset($N[$k])) not_found('That page is not in the Book');
page_open(['title' => $N[$k][0] . ' · CoinorChronicles', 'desc' => $N[$k][1], 'path' => '/notice/' . $k, 'noindex' => true]);
echo '<h1>' . h($N[$k][0]) . '</h1><p class="lede">' . h($N[$k][1]) . '</p><p><a class="btn" href="/">Back to the chronicle</a></p>';
page_close(false);
