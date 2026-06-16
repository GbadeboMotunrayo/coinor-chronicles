<?php
/**
 * CoinorChronicles — Main Pipeline
 * 
 * This is the engine. It runs every 6 hours via Hostinger Cron Job.
 * 
 * Cron command (set in hPanel → Advanced → Cron Jobs):
 * 0 0,6,12,18 * * * php /home/YOUR_USERNAME/public_html/cron/run_pipeline.php >> /home/YOUR_USERNAME/logs/pipeline.log 2>&1
 * 
 * Flow:
 * 1. Fetch live prices from CoinGecko
 * 2. Select which clan runs this episode
 * 3. Fetch style guide from GitHub
 * 4. Read memory from database
 * 5. Generate story via Anthropic API
 * 6. Review story via second Anthropic call
 * 7. Check for milestone crossings
 * 8. Save to database
 * 9. Story goes live on website automatically
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$start_time = microtime(true);
$run_log = [];

echo "[" . date('Y-m-d H:i:s') . "] CoinorChronicles Pipeline Starting...\n";

try {

    // ─── STEP 1: FETCH LIVE PRICES ──────────────────────────────────────────
    echo "[STEP 1] Fetching prices from CoinGecko...\n";
    $prices = fetchCoinPrices();
    if (!$prices) throw new Exception("CoinGecko fetch failed");
    echo "[STEP 1] Got prices for " . count($prices) . " coins.\n";

    // ─── STEP 2: CHECK MARKET CONDITION ─────────────────────────────────────
    $market_condition = determineMarketCondition($prices);
    echo "[STEP 2] Market condition: {$market_condition}\n";
    
    // Skip if sideways market
    if ($market_condition === 'waiting_plains') {
        echo "[SKIP] Waiting Plains detected. No story published in flat markets.\n";
        logPipeline(null, 'skipped', null, 'Waiting Plains — flat market');
        exit(0);
    }

    // ─── STEP 3: SELECT CLAN ─────────────────────────────────────────────────
    echo "[STEP 3] Selecting clan...\n";
    $selected_clan = selectClan($prices);
    $clans = json_decode(CLANS, true);
    $clan_data = $clans[$selected_clan];
    echo "[STEP 3] Selected: {$clan_data['name']}\n";

    // ─── STEP 4: FETCH STYLE GUIDE FROM GITHUB ───────────────────────────────
    echo "[STEP 4] Fetching style guide from GitHub...\n";
    $style_guide = fetchStyleGuide();
    echo "[STEP 4] Style guide loaded (" . strlen($style_guide) . " chars)\n";

    // ─── STEP 5: READ MEMORY FROM DATABASE ──────────────────────────────────
    echo "[STEP 5] Reading clan memory...\n";
    $memory = getClanMemory($selected_clan);
    $creator_override = getCreatorOverride($selected_clan);
    $story_count = (int) getSettingValue('total_story_count') + 1;
    $heaven_positions = getHeavenPositions($selected_clan, $clan_data['coins']);
    echo "[STEP 5] Memory loaded. Story count will be: {$story_count}\n";

    // ─── STEP 6: BUILD PROMPT ────────────────────────────────────────────────
    echo "[STEP 6] Building story prompt...\n";
    $prompt = buildStoryPrompt(
        $clan_data,
        $prices,
        $market_condition,
        $memory,
        $heaven_positions,
        $creator_override,
        $story_count
    );

    // ─── STEP 7: GENERATE STORY ──────────────────────────────────────────────
    echo "[STEP 7] Generating story via Anthropic API...\n";
    $story_raw = callAnthropicAPI($prompt, $style_guide, STORY_MODEL);
    if (!$story_raw) throw new Exception("Story generation failed");
    echo "[STEP 7] Story generated (" . str_word_count($story_raw) . " words)\n";

    // ─── STEP 8: REVIEW STORY ────────────────────────────────────────────────
    echo "[STEP 8] Running quality gate review...\n";
    $review_result = reviewStory($story_raw, $selected_clan);
    
    if ($review_result['result'] !== 'PASS') {
        echo "[STEP 8] REVIEW FAILED. Regenerating with feedback...\n";
        // Try once more with the review feedback injected
        $prompt_with_feedback = $prompt . "\n\n## PREVIOUS ATTEMPT FAILED REVIEW:\n" . $review_result['notes'] . "\n\nFix these issues in your next attempt.";
        $story_raw = callAnthropicAPI($prompt_with_feedback, $style_guide, STORY_MODEL);
        $review_result = reviewStory($story_raw, $selected_clan);
        
        if ($review_result['result'] !== 'PASS') {
            throw new Exception("Story failed review twice: " . $review_result['notes']);
        }
    }
    echo "[STEP 8] Review PASSED.\n";

    // ─── STEP 9: PARSE STORY ─────────────────────────────────────────────────
    $story_parsed = parseStory($story_raw);
    
    // Replace donation wallet placeholder if needed
    if ($story_count % DONATION_TRIGGER_EVERY === 0) {
        $story_parsed['story_text'] = injectWalletAddresses($story_parsed['story_text']);
        $is_donation = true;
    } else {
        $is_donation = false;
    }

    // ─── STEP 10: CHECK MILESTONES ────────────────────────────────────────────
    echo "[STEP 10] Checking milestone crossings...\n";
    $milestones_crossed = checkMilestones($prices, $clan_data['coins']);
    $is_celebration = count($milestones_crossed) > 0;
    
    if ($is_celebration) {
        echo "[STEP 10] MILESTONE DETECTED! Generating celebration episode...\n";
        $story_raw = generateCelebrationEpisode($milestones_crossed[0], $prices, $style_guide);
        $story_parsed = parseStory($story_raw);
        recordMilestone($milestones_crossed[0], $prices);
    }

    // ─── STEP 11: SAVE TO DATABASE ────────────────────────────────────────────
    echo "[STEP 11] Saving story to database...\n";
    $story_id = saveStory([
        'episode_number'     => $story_count,
        'clan'               => $selected_clan,
        'clan_name'          => $clan_data['name'],
        'territory'          => $clan_data['territory'],
        'title'              => $story_parsed['title'],
        'story_text'         => $story_parsed['story_text'],
        'market_condition'   => $market_condition,
        'is_celebration'     => $is_celebration,
        'is_donation'        => $is_donation,
        'is_creator_override' => !empty($creator_override),
    ]);
    echo "[STEP 11] Story saved. ID: {$story_id}\n";

    // ─── STEP 12: UPDATE MEMORY AND STATE ────────────────────────────────────
    updateClanMemory($selected_clan, $story_parsed['story_text'], $clan_data['name']);
    updateCharacterPrices($prices);
    updateHeavenPositions($prices);
    updateSetting('total_story_count', $story_count);
    updateSetting('last_run', date('Y-m-d H:i:s'));
    logClanRotation($selected_clan, $story_id);
    
    // Mark creator override as used
    if ($creator_override) markOverrideUsed($creator_override['id']);

    // ─── DONE ─────────────────────────────────────────────────────────────────
    $duration = round((microtime(true) - $start_time) * 1000);
    logPipeline($selected_clan, 'success', $story_id, null, $duration);
    
    echo "[DONE] Episode {$story_count} published to coinorchronicles.com\n";
    echo "[DONE] Clan: {$clan_data['name']} | Condition: {$market_condition}\n";
    echo "[DONE] Duration: {$duration}ms\n";
    echo "The fellowship endures.\n\n";

} catch (Exception $e) {
    $duration = round((microtime(true) - $start_time) * 1000);
    echo "[ERROR] " . $e->getMessage() . "\n";
    logPipeline($selected_clan ?? null, 'failed', null, $e->getMessage(), $duration);
}

// ─── FUNCTIONS ────────────────────────────────────────────────────────────────

function fetchCoinPrices(): array|false {
    // CoinGecko free API — no key needed for basic calls
    $coin_ids = 'bitcoin,ethereum,litecoin,binancecoin,ripple,solana,the-open-network,avalanche-2,stellar,tron,cardano,chainlink,uniswap,aave,injective-protocol,jasmycoin,pepe,shiba-inu,dogecoin,floki,notcoin,book-of-meme,bonk';
    
    $url = "https://api.coingecko.com/api/v3/simple/price?ids={$coin_ids}&vs_currencies=usd&include_24hr_change=true&include_24hr_vol=true&include_market_cap=true";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200 || !$response) return false;
    
    $data = json_decode($response, true);
    
    // Map CoinGecko IDs to our tickers
    $ticker_map = [
        'bitcoin' => 'BTC', 'ethereum' => 'ETH', 'litecoin' => 'LTC',
        'binancecoin' => 'BNB', 'ripple' => 'XRP', 'solana' => 'SOL',
        'the-open-network' => 'TON', 'avalanche-2' => 'AVAX', 'stellar' => 'XLM',
        'tron' => 'TRX', 'cardano' => 'ADA', 'chainlink' => 'LINK',
        'uniswap' => 'UNI', 'aave' => 'AAVE', 'injective-protocol' => 'INJ',
        'jasmycoin' => 'JASMY', 'pepe' => 'PEPE', 'shiba-inu' => 'SHIB',
        'dogecoin' => 'DOGE', 'floki' => 'FLOKI', 'notcoin' => 'NOT',
        'book-of-meme' => 'BOME', 'bonk' => 'BONK',
    ];
    
    $prices = [];
    foreach ($data as $cg_id => $coin_data) {
        if (isset($ticker_map[$cg_id])) {
            $ticker = $ticker_map[$cg_id];
            $prices[$ticker] = [
                'price'      => $coin_data['usd'],
                'change_24h' => round($coin_data['usd_24h_change'] ?? 0, 2),
                'volume'     => $coin_data['usd_24h_vol'] ?? 0,
                'market_cap' => $coin_data['usd_market_cap'] ?? 0,
                'gold_units' => abs(round($coin_data['usd_24h_change'] ?? 0, 1)),
                'direction'  => ($coin_data['usd_24h_change'] ?? 0) >= 0 ? 'heavier' : 'lighter',
            ];
        }
    }
    
    return $prices;
}

function determineMarketCondition(array $prices): string {
    // Average 24h change of BTC and ETH determines overall condition
    $btc_change = $prices['BTC']['change_24h'] ?? 0;
    $eth_change = $prices['ETH']['change_24h'] ?? 0;
    $avg_change = ($btc_change + $eth_change) / 2;
    
    if ($avg_change > 0.5) return 'golden_season';
    if ($avg_change < -0.5) return 'dark_siege';
    return 'waiting_plains';
}

function selectClan(array $prices): string {
    $db = getDB();
    
    // Get the last 4 clan runs
    $stmt = $db->query("SELECT clan FROM clan_rotation ORDER BY ran_at DESC LIMIT 4");
    $recent_clans = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $all_clans = ['ancients', 'swift', 'meme_lords', 'builders'];
    
    // Check if any coin moved more than 12% in 24h (repeat its clan)
    $dominant_clan = null;
    $clans = json_decode(CLANS, true);
    foreach ($clans as $clan_key => $clan_data) {
        foreach ($clan_data['coins'] as $coin) {
            if (isset($prices[$coin]) && abs($prices[$coin]['change_24h']) > 12) {
                $dominant_clan = $clan_key;
                break 2;
            }
        }
    }
    
    if ($dominant_clan) return $dominant_clan;
    
    // Otherwise rotate — pick the clan that has gone longest without an episode
    foreach ($all_clans as $clan) {
        if (!in_array($clan, $recent_clans)) return $clan;
    }
    
    // All clans ran recently — pick least recent
    $clans_by_last_run = array_flip(array_reverse($recent_clans));
    return array_key_last($clans_by_last_run) ?? $all_clans[array_rand($all_clans)];
}

function fetchStyleGuide(): string {
    $docs = [
        GITHUB_WORLD_BIBLE_URL,
        GITHUB_CHARACTER_VOICES_URL,
        GITHUB_EXTERNAL_REALMS_URL,
        GITHUB_COSMOLOGY_URL,
    ];
    
    $combined = '';
    foreach ($docs as $url) {
        $content = file_get_contents($url);
        if ($content) {
            $combined .= "\n\n---\n\n" . $content;
        }
    }
    
    return $combined;
}

function buildStoryPrompt(array $clan, array $prices, string $condition, array $memory, array $heavens, ?array $override, int $story_count): string {
    $coin_lines = '';
    foreach ($clan['coins'] as $coin) {
        if (isset($prices[$coin])) {
            $p = $prices[$coin];
            $coin_lines .= "  - {$coin}: \${$p['price']} | {$p['change_24h']}% | {$p['gold_units']} gold units {$p['direction']}\n";
        }
    }
    
    $heaven_lines = '';
    foreach ($heavens as $coin => $h) {
        $heaven_lines .= "  - {$h['character']}: {$coin} | Heaven {$h['heaven']} of Gate {$h['gate']} | Last price: \${$h['last_price']}\n";
    }
    
    $override_text = $override ? $override['override_text'] : 'None — generate naturally from market data';
    $condition_name = [
        'golden_season' => 'The Golden Season',
        'dark_siege'    => 'The Dark Siege',
        'waiting_plains' => 'The Waiting Plains',
    ][$condition];
    
    return "SELECTED_CLAN: {$clan['name']} — {$clan['territory']}
MARKET_CONDITION: {$condition_name}
MARKET_DATA:
{$coin_lines}
LAST_EPISODE_SUMMARY: {$memory['last_summary']}
STORY_DIRECTION: {$memory['story_direction']}
CURRENT_HEAVENS:
{$heaven_lines}
CREATOR_OVERRIDE: {$override_text}
STORY_COUNT: {$story_count}

Now write the episode following all rules in the style guide.";
}

function callAnthropicAPI(string $user_prompt, string $system_context, string $model): string|false {
    $payload = [
        'model' => $model,
        'max_tokens' => MAX_TOKENS,
        'system' => "You are the official story narrator of CoinorChronicles. " . $system_context,
        'messages' => [
            ['role' => 'user', 'content' => $user_prompt]
        ],
    ];
    
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . ANTHROPIC_API_KEY,
            'anthropic-version: 2023-06-01',
        ],
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) return false;
    
    $data = json_decode($response, true);
    return $data['content'][0]['text'] ?? false;
}

function reviewStory(string $story, string $clan): array {
    $review_prompt = file_get_contents(GITHUB_REVIEW_PROMPT_URL);
    if (!$review_prompt) {
        // Fallback local check
        return basicReviewCheck($story);
    }
    
    $result_raw = callAnthropicAPI(
        "Review this story script:\n\n---\n{$story}\n---",
        $review_prompt,
        REVIEW_MODEL
    );
    
    if (!$result_raw) return ['result' => 'FAIL', 'notes' => 'Review API call failed'];
    
    $result = str_contains(strtoupper($result_raw), 'REVIEW RESULT: PASS') ? 'PASS' : 'FAIL';
    return ['result' => $result, 'notes' => $result_raw];
}

function basicReviewCheck(string $story): array {
    // Fallback if GitHub fetch fails — basic banned word check
    $banned = ['crypto', 'cryptocurrency', ' market ', 'percent', ' buy ', ' sell ', 'chart'];
    foreach ($banned as $word) {
        if (stripos($story, $word) !== false) {
            return ['result' => 'FAIL', 'notes' => "Banned word found: '{$word}'"];
        }
    }
    return ['result' => 'PASS', 'notes' => 'Basic checks passed'];
}

function parseStory(string $raw): array {
    // Extract title (first line or first bolded line)
    $lines = explode("\n", trim($raw));
    $title = trim(str_replace(['**', '*', '#'], '', $lines[0]));
    if (strlen($title) > 255) $title = substr($title, 0, 252) . '...';
    
    // Remove scene notes from the publishable story
    $story_text = $raw;
    if (str_contains($raw, '[SCENE NOTES]')) {
        $story_text = trim(substr($raw, 0, strpos($raw, '[SCENE NOTES]')));
    }
    
    return ['title' => $title, 'story_text' => $story_text];
}

function injectWalletAddresses(string $story): string {
    $wallets = "
**The Fellowship Contribution Scrolls:**
- BTC: `" . WALLET_BTC . "`
- ETH: `" . WALLET_ETH . "`
- USDT: `" . WALLET_USDT . "`
- PEPE: `" . WALLET_PEPE . "`
- SHIB: `" . WALLET_SHIB . "`
";
    return str_replace('[WALLET_PLACEHOLDER]', $wallets, $story);
}

function checkMilestones(array $prices, array $coins): array {
    $db = getDB();
    $milestones = [];
    $gates = json_decode(GOLDEN_GATES, true);
    
    foreach ($coins as $coin) {
        if (!isset($prices[$coin]) || !isset($gates[$coin])) continue;
        
        $current_price = $prices[$coin]['price'];
        $stmt = $db->prepare("SELECT gate_number, heaven_number FROM character_status WHERE coin_ticker = ?");
        $stmt->execute([$coin]);
        $status = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$status) continue;
        
        $gate_key = 'gate' . $status['gate_number'];
        $gate_price = $gates[$coin][$gate_key] ?? null;
        if (!$gate_price) continue;
        
        // Check Gate crossing
        if ($current_price >= $gate_price && !wasAlreadyRecorded($coin, 'gate', $status['gate_number'])) {
            $milestones[] = ['coin' => $coin, 'type' => 'gate', 'value' => $status['gate_number'], 'price' => $current_price];
        }
        
        // Check Heaven crossing
        $prev_gate_price = $status['gate_number'] > 1 ? ($gates[$coin]['gate' . ($status['gate_number'] - 1)] ?? 0) : 0;
        $gap = $gate_price - $prev_gate_price;
        $heaven_threshold = $prev_gate_price + ($gap / 10) * $status['heaven_number'];
        
        if ($current_price >= $heaven_threshold && !wasAlreadyRecorded($coin, 'heaven', $status['heaven_number'])) {
            $milestones[] = ['coin' => $coin, 'type' => 'heaven', 'value' => $status['heaven_number'], 'price' => $current_price];
        }
    }
    
    return $milestones;
}

function wasAlreadyRecorded(string $coin, string $type, int $value): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM milestones WHERE coin_ticker = ? AND milestone_type = ? AND milestone_value = ?");
    $stmt->execute([$coin, $type, $value]);
    return $stmt->fetch() !== false;
}

function saveStory(array $data): int {
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO stories (episode_number, clan, clan_name, territory, title, story_text, market_condition, is_celebration, is_donation, is_creator_override, published_at)
        VALUES (:episode_number, :clan, :clan_name, :territory, :title, :story_text, :market_condition, :is_celebration, :is_donation, :is_creator_override, NOW())
    ");
    $stmt->execute($data);
    return $db->lastInsertId();
}

function updateClanMemory(string $clan, string $story_text, string $clan_name): void {
    $db = getDB();
    // Generate a brief summary using Anthropic
    $summary_prompt = "Summarise this story in 2 sentences as a memory for the next episode. Focus on what happened to the characters and the road ahead:\n\n{$story_text}";
    $summary = callAnthropicAPI($summary_prompt, '', STORY_MODEL) ?? substr($story_text, 0, 300);
    
    $stmt = $db->prepare("UPDATE story_memory SET last_summary = ?, last_updated = NOW() WHERE clan = ?");
    $stmt->execute([$summary, $clan]);
}

function updateCharacterPrices(array $prices): void {
    $db = getDB();
    foreach ($prices as $ticker => $data) {
        $stmt = $db->prepare("UPDATE character_status SET current_price = ?, last_updated = NOW() WHERE coin_ticker = ?");
        $stmt->execute([$data['price'], $ticker]);
    }
}

function updateHeavenPositions(array $prices): void {
    $db = getDB();
    $gates = json_decode(GOLDEN_GATES, true);
    
    foreach ($prices as $ticker => $data) {
        if (!isset($gates[$ticker])) continue;
        
        $stmt = $db->prepare("SELECT gate_number FROM character_status WHERE coin_ticker = ?");
        $stmt->execute([$ticker]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) continue;
        
        $gate_key = 'gate' . $row['gate_number'];
        $prev_gate_key = 'gate' . ($row['gate_number'] - 1);
        $gate_price = $gates[$ticker][$gate_key] ?? null;
        $prev_gate_price = ($row['gate_number'] > 1) ? ($gates[$ticker][$prev_gate_key] ?? 0) : 0;
        
        if (!$gate_price) continue;
        
        $gap = $gate_price - $prev_gate_price;
        $position = $data['price'] - $prev_gate_price;
        $heaven = max(1, min(10, ceil(($position / $gap) * 10)));
        
        $stmt = $db->prepare("UPDATE character_status SET heaven_number = ? WHERE coin_ticker = ?");
        $stmt->execute([$heaven, $ticker]);
    }
}

function getClanMemory(string $clan): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM story_memory WHERE clan = ?");
    $stmt->execute([$clan]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?? ['last_summary' => 'The chronicle begins.', 'story_direction' => ''];
}

function getCreatorOverride(?string $clan): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM creator_control WHERE is_active = 1 AND (target_clan = ? OR target_clan IS NULL) ORDER BY priority DESC, created_at ASC LIMIT 1");
    $stmt->execute([$clan]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function getHeavenPositions(string $clan, array $coins): array {
    $db = getDB();
    $positions = [];
    foreach ($coins as $coin) {
        $stmt = $db->prepare("SELECT character_name, gate_number, heaven_number, current_price FROM character_status WHERE coin_ticker = ?");
        $stmt->execute([$coin]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $positions[$coin] = [
                'character'  => $row['character_name'],
                'gate'       => $row['gate_number'],
                'heaven'     => $row['heaven_number'],
                'last_price' => $row['current_price'],
            ];
        }
    }
    return $positions;
}

function getSettingValue(string $key): string {
    $db = getDB();
    $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    return $stmt->fetchColumn() ?? '0';
}

function updateSetting(string $key, $value): void {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$key, $value, $value]);
}

function logClanRotation(string $clan, int $story_id): void {
    $db = getDB();
    $db->prepare("INSERT INTO clan_rotation (clan, story_id) VALUES (?, ?)")->execute([$clan, $story_id]);
}

function markOverrideUsed(int $id): void {
    $db = getDB();
    $db->prepare("UPDATE creator_control SET is_active = 0, used_at = NOW() WHERE id = ?")->execute([$id]);
}

function recordMilestone(array $milestone, array $prices): void {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO milestones (coin_ticker, character_name, milestone_type, milestone_value, price_at_crossing) VALUES (?, (SELECT character_name FROM character_status WHERE coin_ticker = ?), ?, ?, ?)");
    $stmt->execute([$milestone['coin'], $milestone['coin'], $milestone['type'], $milestone['value'], $milestone['price']]);
}

function generateCelebrationEpisode(array $milestone, array $prices, string $style_guide): string {
    $prompt_template = file_get_contents(GITHUB_SCRIPT_PROMPT_URL); // Use celebration prompt
    $coin = $milestone['coin'];
    $db = getDB();
    $char = $db->prepare("SELECT * FROM character_status WHERE coin_ticker = ?")->execute([$coin]);
    $memory = $db->prepare("SELECT last_summary FROM story_memory WHERE clan = (SELECT clan FROM character_status WHERE coin_ticker = ?)")->execute([$coin]);
    
    $prompt = "MILESTONE_TYPE: " . strtoupper($milestone['type']) . "_CROSSING
CHARACTER: " . ($milestone['character'] ?? $coin) . "
COIN: {$coin}
MILESTONE: " . ucfirst($milestone['type']) . " " . $milestone['value'] . "
PRICE_AT_MILESTONE: \${$milestone['price']}

Write a full celebration episode for this milestone. Use the celebration episode prompt format.";
    
    return callAnthropicAPI($prompt, $style_guide, STORY_MODEL) ?? "Celebration episode generation failed.";
}

function logPipeline(?string $clan, string $status, ?int $story_id, ?string $error, int $duration = 0): void {
    $db = getDB();
    $db->prepare("INSERT INTO pipeline_logs (clan, status, story_id, error_msg, duration_ms) VALUES (?, ?, ?, ?, ?)")
       ->execute([$clan, $status, $story_id, $error, $duration]);
}
