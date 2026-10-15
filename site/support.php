<?php
require __DIR__ . '/lib.php';
// Public receiving addresses only. A second copy lives in the open repo (docs/SUPPORT.md) so anyone can cross-check.
$WALLETS = [
  ['Bitcoin', 'Bitcoin network only', 'bc1qhrdgva4wrw58gftdnwuz9uajl7zjsr8adwug8x', 'btc', 'BTC'],
  ['Ethereum', 'Ethereum network: ETH and tokens such as USDT on Ethereum', '0xAE06891d069a5d4c9dbe5EAEA46d00A66CA989aa', 'eth', 'ETH'],
  ['BNB Smart Chain', 'BNB Smart Chain network: BNB and BEP-20 tokens. This is the same address as Ethereum, so choose the BNB Smart Chain network when you send', '0xAE06891d069a5d4c9dbe5EAEA46d00A66CA989aa', 'eth', 'BNB'],
  ['Tron', 'Tron network: TRX and TRC-20 tokens such as USDT on Tron', 'TVYZtakiWMT4E9k53e664G14C77RPGszdC', 'trx', 'TRX'],
  ['Solana', 'Solana network: SOL and SPL tokens', '2cc62v4Y8gFcn4859s6bDPTnfjRKsn5DNG9qfeR82WLa', 'sol', 'SOL'],
];
page_open([
  'title' => 'Support the Journey · CoinorChronicles',
  'desc' => 'CoinorChronicles is free to read. Voluntary support: wallet addresses for Bitcoin, Ethereum, BNB Smart Chain, Tron and Solana. A gift, not an investment.',
  'path' => '/support', 'nav' => 'support',
]);
echo '<p class="eyebrow">Support the journey</p><h1>Send provisions to the fellowship</h1>';
echo '<p class="lede">The Book of Meme is free to read, and it takes real work to keep it running. If it has given you something and you want to help the road go on, you may send a gift.</p>';
echo '<p><strong>This is a gift, not an investment.</strong> Nothing is promised in return: no reward, no return, no token. Gifts cannot be reversed. This is not financial advice.</p>';
echo '<h2>Where to send</h2><div class="wallets">';
foreach ($WALLETS as $w) {
  echo '<section class="wallet"><div class="wallet-qr"><img src="/qr/' . h($w[3]) . '.svg" alt="QR code for the ' . h($w[0]) . ' address" width="120" height="120" loading="lazy"></div>';
  echo '<div class="wallet-info"><h3>' . h($w[0]) . '</h3><p class="where">' . h($w[1]) . '</p>';
  echo '<code class="addr">' . h($w[2]) . '</code>';
  echo '<p><button type="button" class="btn" data-copy="' . h($w[2]) . '">Copy</button></p></div></section>';
}
echo '</div><h2>Before you send</h2><ul class="care">';
echo '<li>Send on the matching network only. A coin sent on the wrong network can be lost for good.</li>';
echo '<li>Try a small amount first, and check the address you copied against this page or the open copy at <a href="https://github.com/GbadeboMotunrayo/coinor-chronicles/blob/main/docs/SUPPORT.md" rel="noopener">github.com/GbadeboMotunrayo/coinor-chronicles</a>.</li>';
echo '<li>These addresses appear only here and in that open copy. Nobody from CoinorChronicles will message you asking for money. The stories themselves never contain a wallet address.</li>';
echo '</ul>';
echo '<script>document.querySelectorAll("[data-copy]").forEach(function(b){b.addEventListener("click",function(){var t=b.getAttribute("data-copy");if(!navigator.clipboard)return;navigator.clipboard.writeText(t).then(function(){b.textContent="Copied";setTimeout(function(){b.textContent="Copy"},1800)}).catch(function(){})})})</script>';
page_close();
