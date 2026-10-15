<?php
require __DIR__ . '/lib.php';
// Public receiving addresses only. A second copy lives in the open repo (docs/SUPPORT.md) so anyone can cross-check.
$ETH = '0xAE06891d069a5d4c9dbe5EAEA46d00A66CA989aa';
$WALLETS = [
  'btc' => ['Bitcoin', 'BTC', 'Bitcoin network only.', 'bc1qhrdgva4wrw58gftdnwuz9uajl7zjsr8adwug8x', 'btc'],
  'eth' => ['Ethereum', 'ETH', 'Ethereum network: ETH and tokens such as USDT on Ethereum.', $ETH, 'eth'],
  'bnb' => ['BNB Smart Chain', 'BNB', 'BNB Smart Chain network: BNB and BEP-20 tokens. This is the same address as Ethereum, so choose the BNB Smart Chain network when you send.', $ETH, 'eth'],
  'trx' => ['Tron', 'TRX', 'Tron network: TRX and TRC-20 tokens such as USDT on Tron.', 'TVYZtakiWMT4E9k53e664G14C77RPGszdC', 'trx'],
  'sol' => ['Solana', 'SOL', 'Solana network: SOL and SPL tokens.', '2cc62v4Y8gFcn4859s6bDPTnfjRKsn5DNG9qfeR82WLa', 'sol'],
];

// Our own stylised medallions: a coin per network, no external images.
function coin_svg($k) {
  $rim = '<circle cx="32" cy="32" r="25" fill="none" stroke="rgba(255,255,255,.4)" stroke-width="1.6"/>';
  $head = '<svg viewBox="0 0 64 64" width="64" height="64" aria-hidden="true" focusable="false">';
  switch ($k) {
    case 'btc': return $head . '<circle cx="32" cy="32" r="30" fill="#f7931a"/>' . $rim . '<path d="M27 14v6M35 14v6M27 44v6M35 44v6" stroke="#fff" stroke-width="3" stroke-linecap="round"/><text x="32" y="42" text-anchor="middle" font-size="30" font-weight="700" font-family="Arial,Helvetica,sans-serif" fill="#fff">B</text></svg>';
    case 'eth': return $head . '<circle cx="32" cy="32" r="30" fill="#627eea"/>' . $rim . '<path d="M32 11 44 32 32 39 20 32Z" fill="#fff"/><path d="M32 43 44 36 32 53 20 36Z" fill="#fff" opacity=".82"/></svg>';
    case 'bnb': return $head . '<circle cx="32" cy="32" r="30" fill="#f3ba2f"/>' . $rim . '<g fill="#2a1b0d"><path d="M32 22 42 32 32 42 22 32Z"/><path d="M32 10 38 16 32 22 26 16Z"/><path d="M32 42 38 48 32 54 26 48Z"/><path d="M10 32 16 26 22 32 16 38Z"/><path d="M42 32 48 26 54 32 48 38Z"/></g></svg>';
    case 'trx': return $head . '<circle cx="32" cy="32" r="30" fill="#e8002d"/>' . $rim . '<path d="M17 20 47 26 34 49Z" fill="none" stroke="#fff" stroke-width="3.4" stroke-linejoin="round"/><path d="M17 20 34 49M26 23 37 37" stroke="#fff" stroke-width="3" stroke-linecap="round"/></svg>';
    case 'sol': return $head . '<defs><linearGradient id="solg" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#9945ff"/><stop offset="1" stop-color="#14f195"/></linearGradient></defs><circle cx="32" cy="32" r="30" fill="url(#solg)"/>' . $rim . '<g fill="#fff"><path d="M22 18h24l-4 7H18z"/><path d="M18 28.5h24l4 7H22z"/><path d="M22 39h24l-4 7H18z"/></g></svg>';
  }
  return '';
}

page_open([
  'title' => 'Support the Journey · CoinorChronicles',
  'desc' => 'CoinorChronicles is free to read. Voluntary support: choose a coin to see its wallet address and QR code. A gift, not an investment.',
  'path' => '/support', 'nav' => 'support',
]);
echo '<p class="eyebrow">Support the journey</p><h1>Send provisions to the fellowship</h1>';
echo '<p class="lede">The Book of Meme is free to read, and it takes real work to keep it running. If it has given you something and you want to help the road go on, you may send a gift.</p>';
echo '<p><strong>This is a gift, not an investment.</strong> Nothing is promised in return: no reward, no return, no token. Gifts cannot be reversed. This is not financial advice.</p>';
echo '<h2>Choose a coin</h2><p class="muted coin-hint" id="coin-hint">Tap a coin to see where to send it.</p>';
echo '<div class="coins" role="group" aria-label="Choose a coin">';
foreach ($WALLETS as $k => $w)
  echo '<button type="button" class="coin" data-coin="' . h($k) . '" aria-pressed="false" aria-controls="panel-' . h($k) . '">' . coin_svg($k) . '<span>' . h($w[1]) . '</span></button>';
echo '</div><div class="coin-panels">';
foreach ($WALLETS as $k => $w) {
  echo '<section class="wallet-panel" id="panel-' . h($k) . '" aria-label="' . h($w[0]) . ' wallet">';
  echo '<div class="wallet-qr"><img src="/qr/' . h($w[4]) . '.svg" alt="QR code for the ' . h($w[0]) . ' address" width="150" height="150" loading="lazy"></div>';
  echo '<div class="wallet-info"><h3>' . h($w[0]) . '</h3><p class="where">' . h($w[2]) . '</p>';
  echo '<code class="addr">' . h($w[3]) . '</code><p><button type="button" class="btn" data-copy="' . h($w[3]) . '">Copy address</button></p></div></section>';
}
echo '</div><h2>Before you send</h2><ul class="care">';
echo '<li>Send on the matching network only. A coin sent on the wrong network can be lost for good.</li>';
echo '<li>Try a small amount first, and check the address you copied against this page or the open copy at <a href="https://github.com/GbadeboMotunrayo/coinor-chronicles/blob/main/docs/SUPPORT.md" rel="noopener">github.com/GbadeboMotunrayo/coinor-chronicles</a>.</li>';
echo '<li>These addresses appear only here and in that open copy. Nobody from CoinorChronicles will message you asking for money. The stories themselves never contain a wallet address.</li>';
echo '</ul>';
echo <<<'JS'
<script>
(function () {
  var coins = document.querySelectorAll('.coin'), panels = document.querySelectorAll('.wallet-panel'), hint = document.getElementById('coin-hint');
  document.documentElement.classList.add('coin-js');
  function show(key) {
    coins.forEach(function (c) { c.setAttribute('aria-pressed', String(c.getAttribute('data-coin') === key)); });
    panels.forEach(function (p) { p.hidden = (p.id !== 'panel-' + key); });
    if (hint) hint.hidden = !!key;
    var p = key && document.getElementById('panel-' + key);
    if (p && p.scrollIntoView) p.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
  panels.forEach(function (p) { p.hidden = true; });
  coins.forEach(function (c) {
    c.addEventListener('click', function () {
      var key = c.getAttribute('data-coin');
      show(c.getAttribute('aria-pressed') === 'true' ? '' : key);
    });
  });
  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = b.getAttribute('data-copy'), done = function () { b.textContent = 'Copied'; setTimeout(function () { b.textContent = 'Copy address'; }, 1800); };
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(t).then(done).catch(function () {});
      else { var r = document.createRange(), n = b.parentNode.previousSibling; try { r.selectNodeContents(n); var s = getSelection(); s.removeAllRanges(); s.addRange(r); } catch (e) {} }
    });
  });
})();
</script>
JS;
page_close();
