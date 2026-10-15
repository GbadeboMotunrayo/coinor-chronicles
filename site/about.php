<?php
require __DIR__ . '/lib.php';
page_open([
  'title' => 'About the Book of Meme · How CoinorChronicles is written',
  'desc' => 'CoinorChronicles turns market movement into an ongoing fantasy saga. Chapters are written by AI and published automatically after safety checks. Fiction, not financial advice.',
  'path' => '/about', 'nav' => 'about',
]);
echo '<p class="eyebrow">About</p><h1>How the Book is written</h1>';
echo '<p>CoinorChronicles turns real market movement into an unfolding fantasy saga. When the markets move, a new chapter is drafted with the help of AI, using a fixed style guide and the live price data as story material. New chapters can appear up to twice a day.</p>';
echo '<h2>It runs by itself, with safety checks</h2><p>No person reads each chapter before it appears. Instead, every draft must pass a set of automatic checks in code: a fixed length and structure, in-world language only, no real-world names, no finance terms, no advice, no links or wallet addresses. If a draft fails, it is rewritten, and if it still fails, nothing is published. A person watches the system and can remove any chapter at any time.</p>';
echo '<h2>What this is not</h2><p>It is not financial advice, a price prediction, or a recommendation to buy or sell anything. The characters are metaphors. Digital assets are volatile and you can lose money.</p>';
echo '<h2>Who is behind it</h2><p>CoinorChronicles is a story project from MG Holdings. The style guide that governs the writing is open to read at <a href="https://github.com/GbadeboMotunrayo/coinor-chronicles" rel="noopener">github.com/GbadeboMotunrayo/coinor-chronicles</a>.</p>';
page_close();
