#!/usr/bin/env python3
"""
Builds two importable n8n workflows for CoinorChronicles v2:

  coinor_v2_pipeline.json     "CoinorChronicles v2 - Draft for Review"
  coinor_v2_error_alert.json  "CoinorChronicles - Error Alert"

Design decisions (see n8n/README_V2.md):
  * NOTHING publishes automatically. Episodes are drafted, reviewed by a second
    model call, and sent to the Chairman on Telegram. Posting is manual.
  * No external database. State lives in n8n workflow static data (persisted in
    n8n's own DB, therefore covered by the nightly encrypted backup).
  * Milestones are fixed: gates advance, heavens are detected as upward crossings,
    and the first run only records a silent baseline (no celebration flood).
  * Milestones are checked BEFORE generation, so no episode is written and thrown away.
  * Model: OpenRouter 'openrouter/free' through the existing n8n credential.

Run:  python3 build_v2.py
"""
import json

CHAIRMAN_CHAT = "932372452"
TELEGRAM_CRED = {"telegramApi": {"id": "1ILbERWZVYYmLGe0", "name": "Telegram account"}}
OPENROUTER_CRED = {"httpHeaderAuth": {"id": "classDemoOpenRouter", "name": "OpenRouter (class demo)"}}
ERROR_WORKFLOW_ID = "coinorErrorAlert01"
PIPELINE_ID = "coinorV2Pipeline01"

# ----------------------------------------------------------------------------
# JavaScript for the Code nodes
# ----------------------------------------------------------------------------
SHARED_CONFIG = r"""
// ---- world configuration (single source of truth for every Code node) ------
const CLANS = {
  ancients:   { name:'Clan of the Ancients',  territory:'The Obsidian Citadel', coins:['BTC','ETH','LTC','BNB','XRP'] },
  swift:      { name:'Clan of the Swift',     territory:'The Electric Plains',  coins:['SOL','TON','AVAX','XLM','TRX'] },
  meme_lords: { name:'Clan of Meme Lords',    territory:'Kekiston Bazaar',      coins:['PEPE','SHIB','DOGE','FLOKI','NOT','BOME','BONK'] },
  builders:   { name:'Clan of the Builders',  territory:'The Forge of Chains',  coins:['ADA','SUI','JASMY'] }
};
// Characters follow docs/CHARACTER_VOICES.md. LINK, UNI, AAVE and INJ have no
// character in the style guide yet, so they are deliberately not tracked.
const CHARACTER = {
  BTC:'Aragorn', ETH:'Gandalf', LTC:'Samwise', BNB:'Elrond', XRP:'Boromir',
  SOL:'Legolas', TON:'Eomer', AVAX:'Eowyn', XLM:'Faramir', TRX:'Saruman',
  ADA:'Treebeard', SUI:'Gimli', JASMY:'Galadriel',
  PEPE:'Tom Bombadil', SHIB:'Merry', DOGE:'Pippin', FLOKI:'Theoden', NOT:'Frodo', BOME:'Bilbo', BONK:'Gollum'
};
const CLAN_OF = {};
for (const [k, c] of Object.entries(CLANS)) for (const coin of c.coins) CLAN_OF[coin] = k;
// SUI gate prices are an assumption (not in the original design) - confirm.
const GATES = {
  BTC:{gate1:100000,gate2:1000000,gate3:10000000}, ETH:{gate1:10000,gate2:100000,gate3:1000000},
  SOL:{gate1:1000,gate2:10000,gate3:100000}, XRP:{gate1:100,gate2:1000,gate3:10000},
  BNB:{gate1:10000,gate2:100000,gate3:1000000}, LTC:{gate1:1000,gate2:10000,gate3:100000},
  TON:{gate1:10,gate2:100,gate3:1000}, AVAX:{gate1:500,gate2:5000,gate3:50000},
  XLM:{gate1:5,gate2:50,gate3:500}, TRX:{gate1:1,gate2:10,gate3:100},
  ADA:{gate1:10,gate2:100,gate3:1000}, SUI:{gate1:10,gate2:100,gate3:1000}, JASMY:{gate1:1,gate2:10,gate3:100},
  PEPE:{gate1:1,gate2:10,gate3:100}, SHIB:{gate1:0.001,gate2:0.01,gate3:0.1},
  DOGE:{gate1:10,gate2:100,gate3:1000}, FLOKI:{gate1:1,gate2:10,gate3:100},
  NOT:{gate1:0.1,gate2:1,gate3:10}, BOME:{gate1:0.1,gate2:1,gate3:10}, BONK:{gate1:1,gate2:10,gate3:100}
};
const MAX_GATE = 3;
function gatePrice(coin, gate) { return (GATES[coin] || {})['gate' + gate]; }
function heavenFor(coin, gate, price) {
  const gp = gatePrice(coin, gate); if (!gp) return 1;
  const prev = gate > 1 ? (gatePrice(coin, gate - 1) || 0) : 0;
  const h = Math.ceil(((price - prev) / (gp - prev)) * 10);
  return Math.max(1, Math.min(10, h));
}
function baselineGate(coin, price) {
  let g = 1;
  while (g < MAX_GATE && price >= gatePrice(coin, g)) g++;
  return g;
}
"""

NORMALIZE_JS = r"""
const tickerMap = {
  bitcoin:'BTC', ethereum:'ETH', litecoin:'LTC', binancecoin:'BNB', ripple:'XRP',
  solana:'SOL', 'the-open-network':'TON', 'avalanche-2':'AVAX', stellar:'XLM', tron:'TRX',
  cardano:'ADA', sui:'SUI', jasmycoin:'JASMY', pepe:'PEPE', 'shiba-inu':'SHIB',
  dogecoin:'DOGE', floki:'FLOKI', notcoin:'NOT', 'book-of-meme':'BOME', bonk:'BONK'
};
const raw = $input.first().json;
const prices = {};
for (const [cgId, d] of Object.entries(raw)) {
  const t = tickerMap[cgId];
  if (!t || typeof d.usd !== 'number') continue;
  const change = Number(d.usd_24h_change ?? 0);
  prices[t] = {
    price: d.usd,
    change_24h: Math.round(change * 100) / 100,
    gold_units: Math.round(Math.abs(change) * 10) / 10,
    direction: change >= 0 ? 'heavier' : 'lighter'
  };
}
if (!prices.BTC || !prices.ETH) throw new Error('CoinGecko returned no BTC/ETH price - refusing to write an episode on bad data');
const avg = (prices.BTC.change_24h + prices.ETH.change_24h) / 2;
let market_condition = 'waiting_plains';
if (avg > 0.5) market_condition = 'golden_season';
else if (avg < -0.5) market_condition = 'dark_siege';
return [{ json: { prices, market_condition, skip: market_condition === 'waiting_plains' ? 'yes' : 'no' } }];
"""

STYLE_GUIDE_JS = r"""
const base = 'https://raw.githubusercontent.com/GbadeboMotunrayo/coinor-chronicles/main';
const files = {
  world_bible: 'docs/WORLD_BIBLE.md', character_voices: 'docs/CHARACTER_VOICES.md',
  external_realms: 'docs/EXTERNAL_REALMS.md', cosmology: 'docs/COSMOLOGY.md',
  script_generator: 'prompts/script_generator.md', review_agent: 'prompts/review_agent.md',
  celebration_episode: 'prompts/celebration_episode.md'
};
const got = {};
for (const [k, p] of Object.entries(files)) {
  got[k] = await this.helpers.httpRequest({ method: 'GET', url: `${base}/${p}` });
  if (!got[k] || String(got[k]).length < 200) throw new Error('Style guide file missing or empty: ' + p);
}
const style_guide = [got.world_bible, got.character_voices, got.external_realms, got.cosmology].join('\n\n---\n\n');
const prev = $('Normalize Prices').first().json;
return [{ json: { ...prev, style_guide, script_generator: got.script_generator, review_agent: got.review_agent, celebration_episode: got.celebration_episode } }];
"""

PLAN_JS = SHARED_CONFIG + r"""
const S = $getWorkflowStaticData('global');
const inp = $input.first().json;
const prices = inp.prices;
const tracked = Object.keys(CHARACTER).filter(c => prices[c]);

// --- first run: record a silent baseline, never a celebration ---
let firstRun = false;
if (!S.init) {
  firstRun = true;
  S.init = true; S.episode = 0; S.rotation = []; S.milestones = []; S.recent = [];
  S.memory = {
    ancients:   { summary: 'The chronicle begins. Aragorn speaks for the first time.', direction: 'Aragorn reclaims his position after the wars.' },
    swift:      { summary: 'The chronicle begins. The Electric Plains await.',          direction: 'Legolas leads the clan through volatile times.' },
    meme_lords: { summary: 'The chronicle begins. The Bazaar awakens.',                 direction: 'Merry marches toward his $1 dream. The Bazaar endures.' },
    builders:   { summary: 'The chronicle begins. The Forge fires are lit.',            direction: 'The Builders construct in silence.' }
  };
  S.coins = {};
  for (const c of tracked) { const g = baselineGate(c, prices[c].price); S.coins[c] = { gate: g, heaven: heavenFor(c, g, prices[c].price), price: prices[c].price }; }
}
// coins that appeared later get a silent baseline too
for (const c of tracked) if (!S.coins[c]) { const g = baselineGate(c, prices[c].price); S.coins[c] = { gate: g, heaven: heavenFor(c, g, prices[c].price), price: prices[c].price }; }

// --- milestone detection (read-only) ---
const candidates = [];
if (!firstRun) {
  for (const c of tracked) {
    const st = S.coins[c]; const p = prices[c].price;
    if (st.gate < MAX_GATE && p >= gatePrice(c, st.gate)) {
      const key = `${c}|gate|${st.gate}`;
      if (!S.milestones.includes(key)) candidates.push({ coin: c, type: 'gate', value: st.gate, key, price: p, character: CHARACTER[c], priority: 2 });
    } else {
      const h = heavenFor(c, st.gate, p);
      if (h > st.heaven) {
        const key = `${c}|heaven|${st.gate}|${h}`;
        if (!S.milestones.includes(key)) candidates.push({ coin: c, type: 'heaven', value: h, key, price: p, character: CHARACTER[c], priority: 1 });
      }
    }
  }
}
candidates.sort((a, b) => b.priority - a.priority);
const celebration = candidates[0] || null;

// --- clan choice ---
let clanKey = null;
if (celebration) clanKey = CLAN_OF[celebration.coin];
if (!clanKey) {
  outer: for (const [k, c] of Object.entries(CLANS)) for (const coin of c.coins) if (prices[coin] && Math.abs(prices[coin].change_24h) > 12) { clanKey = k; break outer; }
}
if (!clanKey) clanKey = Object.keys(CLANS).find(k => !S.rotation.includes(k)) || S.rotation[S.rotation.length - 1];
const clan = CLANS[clanKey];

// --- prompt ---
const coinLines = clan.coins.filter(c => prices[c]).map(c => `  - ${c}: $${prices[c].price} | ${prices[c].change_24h}% | ${prices[c].gold_units} gold units ${prices[c].direction}`).join('\n');
const heavenLines = clan.coins.filter(c => S.coins[c]).map(c => `  - ${CHARACTER[c]}: ${c} | Heaven ${S.coins[c].heaven} of Gate ${S.coins[c].gate} | Last price: $${S.coins[c].price}`).join('\n');
const conditionName = { golden_season:'The Golden Season', dark_siege:'The Dark Siege', waiting_plains:'The Waiting Plains' }[inp.market_condition];
const mem = S.memory[clanKey] || { summary: 'The chronicle begins.', direction: '' };
const episode = (S.episode || 0) + 1;

const MEMORY_RULE = `\n\nOUTPUT RULE FOR THIS RUN: after everything the style guide requires (including any [SCENE NOTES] block), finish with one final line that starts with [MEMORY] followed by a two-sentence summary of what happened, for next-episode continuity. Do not mention this rule in the story.`;

let prompt, mode;
if (celebration) {
  mode = 'celebration';
  prompt = `MILESTONE_TYPE: ${celebration.type.toUpperCase()}_CROSSING
CHARACTER: ${celebration.character}
COIN: ${celebration.coin}
MILESTONE: ${celebration.type === 'gate' ? 'Gate' : 'Heaven'} ${celebration.value}
PRICE_AT_MILESTONE: $${celebration.price}
DATE: ${new Date().toISOString().slice(0, 10)}

Write a full celebration episode for this milestone, following the celebration_episode.md structure exactly.${MEMORY_RULE}`;
} else {
  mode = 'story';
  prompt = `SELECTED_CLAN: ${clan.name} — ${clan.territory}
MARKET_CONDITION: ${conditionName}
MARKET_DATA:
${coinLines}
LAST_EPISODE_SUMMARY: ${mem.summary}
STORY_DIRECTION: ${mem.direction}
CURRENT_HEAVENS:
${heavenLines}
CREATOR_OVERRIDE: None — generate naturally from market data
STORY_COUNT: ${episode}

Now write the episode following all rules in the style guide.${MEMORY_RULE}`;
}
const system = 'You are the official story narrator of CoinorChronicles.\n\n' + (mode === 'celebration' ? inp.celebration_episode : inp.script_generator) + '\n\n' + inp.style_guide;

return [{ json: { ...inp, mode, prompt, system, clanKey, clan_name: clan.name, episode, first_run: firstRun, candidates, celebration } }];
"""

EXTRACT_JS = r"""
const res = $input.first().json;
const text = res?.choices?.[0]?.message?.content;
if (!text || String(text).trim().length < 400) throw new Error('Model returned an empty or too-short story: ' + JSON.stringify(res).slice(0, 300));
const lines = String(text).trim().split('\n');
const memLine = lines.filter(l => l.trim().startsWith('[MEMORY]')).pop() || '';
const story_raw = lines.filter(l => !l.trim().startsWith('[MEMORY]')).join('\n').trim();
const plan = $('Plan').first().json;
return [{ json: { story_raw, memory_line: memLine.replace('[MEMORY]', '').trim(), review_input: 'Review this story script:\n\n---\n' + story_raw + '\n---', system_review: plan.review_agent } }];
"""

PARSE_REVIEW_JS = r"""
const res = $input.first().json;
const review = res?.choices?.[0]?.message?.content || '';
const unreviewed = review.trim().length < 20;
const passed = unreviewed || review.toUpperCase().includes('REVIEW RESULT: PASS');
const ex = $('Extract Story').first().json;
return [{ json: { unreviewed, passed: passed ? 'yes' : 'no', review_notes: review, story_raw: ex.story_raw, memory_line: ex.memory_line } }];
"""

REGEN_JS = r"""
const plan = $('Plan').first().json;
const notes = $('Parse Review').first().json.review_notes;
return [{ json: { regen_prompt: plan.prompt + '\n\n## PREVIOUS ATTEMPT FAILED REVIEW:\n' + notes + '\n\nFix these issues in your next attempt.' } }];
"""

EXTRACT2_JS = r"""
const res = $input.first().json;
const text = res?.choices?.[0]?.message?.content;
if (!text || String(text).trim().length < 400) throw new Error('Regeneration returned an empty or too-short story');
const lines = String(text).trim().split('\n');
const memLine = lines.filter(l => l.trim().startsWith('[MEMORY]')).pop() || '';
const story_raw = lines.filter(l => !l.trim().startsWith('[MEMORY]')).join('\n').trim();
const plan = $('Plan').first().json;
return [{ json: { story_raw, memory_line: memLine.replace('[MEMORY]', '').trim(), review_input: 'Review this story script:\n\n---\n' + story_raw + '\n---', system_review: plan.review_agent } }];
"""

PARSE_REVIEW2_JS = r"""
const res = $input.first().json;
const review = res?.choices?.[0]?.message?.content || '';
const unreviewed = review.trim().length < 20;
const passed = unreviewed || review.toUpperCase().includes('REVIEW RESULT: PASS');
const ex = $('Extract Story (2nd)').first().json;
return [{ json: { unreviewed, passed: passed ? 'yes' : 'no', review_notes: review, story_raw: ex.story_raw, memory_line: ex.memory_line } }];
"""

COMMIT_JS = SHARED_CONFIG + r"""
// Apply state ONLY now that an episode passed review.
const S = $getWorkflowStaticData('global');
const plan = $('Plan').first().json;
const pr = $input.first().json;            // the passing review result
const prices = plan.prices;

// 1. milestone bookkeeping: every detected crossing is marked as passed so it never re-fires
for (const m of plan.candidates || []) { if (!S.milestones.includes(m.key)) S.milestones.push(m.key); }
// 2. silent state sync for every tracked coin
for (const c of Object.keys(S.coins)) {
  if (!prices[c]) continue;
  const st = S.coins[c];
  const crossedGate = (plan.candidates || []).some(m => m.coin === c && m.type === 'gate');
  if (crossedGate && st.gate < MAX_GATE) { st.gate += 1; }
  st.heaven = heavenFor(c, st.gate, prices[c].price);
  st.price = prices[c].price;
}
// 3. episode, rotation, memory
S.episode = plan.episode;
S.rotation = [plan.clanKey, ...(S.rotation || []).filter(k => k !== plan.clanKey)].slice(0, 4);
const summary = pr.memory_line && pr.memory_line.length > 20 ? pr.memory_line : pr.story_raw.replace(/\[SCENE NOTES\][\s\S]*$/, '').trim().slice(-280);
S.memory[plan.clanKey] = { summary, direction: (S.memory[plan.clanKey] || {}).direction || '' };

// 4. parse title/body, keep a short archive of drafts
let title = pr.story_raw.trim().split('\n')[0].replace(/[*#]/g, '').trim().slice(0, 200);
const sceneIdx = pr.story_raw.indexOf('[SCENE NOTES]');
const body = (sceneIdx >= 0 ? pr.story_raw.slice(0, sceneIdx) : pr.story_raw).trim();
const scene = sceneIdx >= 0 ? pr.story_raw.slice(sceneIdx).trim() : '';
S.recent = [{ ep: plan.episode, title, clan: plan.clanKey, mode: plan.mode, date: new Date().toISOString().slice(0, 10) }, ...(S.recent || [])].slice(0, 30);

const label = plan.mode === 'celebration' ? `CELEBRATION (${plan.celebration.character}, ${plan.celebration.coin} ${plan.celebration.type} ${plan.celebration.value})` : plan.clan_name;
const flag = pr.unreviewed ? '⚠️ The automatic quality review was unavailable for this draft. Read it carefully.\n' : '✅ Passed the automatic quality review.\n';
const header = `📜 CoinorChronicles — Episode ${plan.episode} DRAFT\n${label} · ${plan.market_condition.replace('_', ' ')}\n${flag}Review before posting. NOTHING has been published.\n\n`;

// 5. Telegram limit is 4096 chars: split on paragraph boundaries
const full = header + body + (scene ? '\n\n' + scene : '');
const chunks = []; let cur = '';
for (const para of full.split('\n\n')) {
  if ((cur + '\n\n' + para).length > 3800) { if (cur) chunks.push(cur); cur = para.slice(0, 3800); }
  else cur = cur ? cur + '\n\n' + para : para;
}
if (cur) chunks.push(cur);
return chunks.map((t, i) => ({ json: { text: chunks.length > 1 ? `(${i + 1}/${chunks.length})\n` + t : t } }));
"""

FAIL_ALERT_JS = r"""
const plan = $('Plan').first().json;
const notes = ($input.first().json.review_notes || '').slice(0, 800);
return [{ json: { text: `⚠️ CoinorChronicles: episode ${plan.episode} (${plan.clan_name}) failed the quality review twice. No draft was produced and no state was changed.\n\nReviewer notes:\n${notes}` } }];
"""

# ----------------------------------------------------------------------------
# node builders
# ----------------------------------------------------------------------------
def code(id_, name, js, pos):
    return {"parameters": {"jsCode": js.strip()}, "id": id_, "name": name,
            "type": "n8n-nodes-base.code", "typeVersion": 2, "position": pos}

def if_yes(id_, name, left, pos):
    return {"parameters": {"conditions": {"options": {"caseSensitive": True, "leftValue": "", "typeValidation": "strict", "version": 2},
            "conditions": [{"id": "c1", "leftValue": left, "rightValue": "yes", "operator": {"type": "string", "operation": "equals"}}],
            "combinator": "and"}, "options": {}},
            "id": id_, "name": name, "type": "n8n-nodes-base.if", "typeVersion": 2.2, "position": pos}

def llm(id_, name, system_expr, user_expr, max_tokens, pos):
    body = ("={{ JSON.stringify({ models: ['google/gemma-4-31b-it:free','nvidia/nemotron-3-super-120b-a12b:free','google/gemma-4-26b-a4b-it:free'], reasoning: { effort: 'low' }, max_tokens: %d, temperature: 0.9, messages: [ "
            "{ role: 'system', content: %s }, { role: 'user', content: %s } ] }) }}") % (max_tokens, system_expr, user_expr)
    return {"parameters": {"method": "POST", "url": "https://openrouter.ai/api/v1/chat/completions",
            "authentication": "genericCredentialType", "genericAuthType": "httpHeaderAuth",
            "sendBody": True, "specifyBody": "json", "jsonBody": body, "options": {"timeout": 120000}},
            "id": id_, "name": name, "type": "n8n-nodes-base.httpRequest", "typeVersion": 4.2, "position": pos,
            "credentials": OPENROUTER_CRED, "retryOnFail": True, "maxTries": 3, "waitBetweenTries": 8000}

def telegram(id_, name, pos):
    return {"parameters": {"chatId": CHAIRMAN_CHAT, "text": "={{ $json.text }}", "additionalFields": {"appendAttribution": False}},
            "id": id_, "name": name, "type": "n8n-nodes-base.telegram", "typeVersion": 1.2, "position": pos,
            "credentials": TELEGRAM_CRED}

def sticky(id_, content, pos, w=360, h=220):
    return {"parameters": {"content": content, "width": w, "height": h}, "id": id_, "name": id_,
            "type": "n8n-nodes-base.stickyNote", "typeVersion": 1, "position": pos}

# ----------------------------------------------------------------------------
# pipeline workflow
# ----------------------------------------------------------------------------
nodes = [
    sticky("note_intro",
           "## CoinorChronicles v2 — Draft for Review\n**Nothing here publishes anything.** Each run drafts an episode, a second model call reviews it, and the draft is sent to you on Telegram. You post it yourself.\n\nState (episode number, clan memory, gates/heavens) lives in this workflow's static data, so it only saves on *production* (active) runs, not manual test runs.\n\nActivate only after you have read the checklist in n8n/README_V2.md.",
           [-80, -80], 420, 260),
    {"parameters": {"rule": {"interval": [{"field": "cronExpression", "expression": "0 6,18 * * *"}]}},
     "id": "trig_schedule", "name": "Twice Daily", "type": "n8n-nodes-base.scheduleTrigger", "typeVersion": 1.2, "position": [0, 240]},
    {"parameters": {}, "id": "trig_manual", "name": "Manual Test", "type": "n8n-nodes-base.manualTrigger", "typeVersion": 1, "position": [0, 400]},
    {"parameters": {"method": "GET", "url": "https://api.coingecko.com/api/v3/simple/price",
                    "sendQuery": True, "queryParameters": {"parameters": [
                        {"name": "ids", "value": "bitcoin,ethereum,litecoin,binancecoin,ripple,solana,the-open-network,avalanche-2,stellar,tron,cardano,sui,jasmycoin,pepe,shiba-inu,dogecoin,floki,notcoin,book-of-meme,bonk"},
                        {"name": "vs_currencies", "value": "usd"}, {"name": "include_24hr_change", "value": "true"}]},
                    "options": {"timeout": 30000}},
     "id": "http_prices", "name": "Fetch CoinGecko Prices", "type": "n8n-nodes-base.httpRequest", "typeVersion": 4.2,
     "position": [240, 320], "retryOnFail": True, "maxTries": 3, "waitBetweenTries": 5000},
    code("code_norm", "Normalize Prices", NORMALIZE_JS, [480, 320]),
    if_yes("if_skip", "Flat Market?", "={{ $json.skip }}", [720, 320]),
    {"parameters": {}, "id": "noop_skip", "name": "Skip - Flat Market", "type": "n8n-nodes-base.noOp", "typeVersion": 1, "position": [960, 200]},
    code("code_style", "Fetch Style Guide", STYLE_GUIDE_JS, [960, 440]),
    code("code_plan", "Plan", PLAN_JS, [1200, 440]),
    llm("llm_gen", "Generate Draft", "$('Plan').first().json.system", "$('Plan').first().json.prompt", 7000, [1440, 440]),
    code("code_extract", "Extract Story", EXTRACT_JS, [1680, 440]),
    llm("llm_review", "Review Draft", "$json.system_review", "$json.review_input", 3000, [1920, 440]),
    code("code_parse", "Parse Review", PARSE_REVIEW_JS, [2160, 440]),
    if_yes("if_passed", "Passed Review?", "={{ $json.passed }}", [2400, 440]),
    # second attempt
    code("code_regen", "Build Regeneration Prompt", REGEN_JS, [2640, 640]),
    llm("llm_regen", "Regenerate Draft", "$('Plan').first().json.system", "$json.regen_prompt", 7000, [2880, 640]),
    code("code_extract2", "Extract Story (2nd)", EXTRACT2_JS, [3120, 640]),
    llm("llm_review2", "Review Draft (2nd)", "$json.system_review", "$json.review_input", 3000, [3360, 640]),
    code("code_parse2", "Parse Review (2nd)", PARSE_REVIEW2_JS, [3600, 640]),
    if_yes("if_passed2", "Passed 2nd Review?", "={{ $json.passed }}", [3840, 640]),
    code("code_fail", "Build Failure Message", FAIL_ALERT_JS, [4080, 760]),
    telegram("tg_fail", "Alert - Failed Review", [4320, 760]),
    # success path
    code("code_commit", "Commit State & Build Draft", COMMIT_JS, [4080, 440]),
    telegram("tg_draft", "Send Draft to Chairman", [4320, 440]),
]

connections = {
    "Twice Daily": {"main": [[{"node": "Fetch CoinGecko Prices", "type": "main", "index": 0}]]},
    "Manual Test": {"main": [[{"node": "Fetch CoinGecko Prices", "type": "main", "index": 0}]]},
    "Fetch CoinGecko Prices": {"main": [[{"node": "Normalize Prices", "type": "main", "index": 0}]]},
    "Normalize Prices": {"main": [[{"node": "Flat Market?", "type": "main", "index": 0}]]},
    "Flat Market?": {"main": [[{"node": "Skip - Flat Market", "type": "main", "index": 0}],
                              [{"node": "Fetch Style Guide", "type": "main", "index": 0}]]},
    "Fetch Style Guide": {"main": [[{"node": "Plan", "type": "main", "index": 0}]]},
    "Plan": {"main": [[{"node": "Generate Draft", "type": "main", "index": 0}]]},
    "Generate Draft": {"main": [[{"node": "Extract Story", "type": "main", "index": 0}]]},
    "Extract Story": {"main": [[{"node": "Review Draft", "type": "main", "index": 0}]]},
    "Review Draft": {"main": [[{"node": "Parse Review", "type": "main", "index": 0}]]},
    "Parse Review": {"main": [[{"node": "Passed Review?", "type": "main", "index": 0}]]},
    "Passed Review?": {"main": [[{"node": "Commit State & Build Draft", "type": "main", "index": 0}],
                                [{"node": "Build Regeneration Prompt", "type": "main", "index": 0}]]},
    "Build Regeneration Prompt": {"main": [[{"node": "Regenerate Draft", "type": "main", "index": 0}]]},
    "Regenerate Draft": {"main": [[{"node": "Extract Story (2nd)", "type": "main", "index": 0}]]},
    "Extract Story (2nd)": {"main": [[{"node": "Review Draft (2nd)", "type": "main", "index": 0}]]},
    "Review Draft (2nd)": {"main": [[{"node": "Parse Review (2nd)", "type": "main", "index": 0}]]},
    "Parse Review (2nd)": {"main": [[{"node": "Passed 2nd Review?", "type": "main", "index": 0}]]},
    "Passed 2nd Review?": {"main": [[{"node": "Commit State & Build Draft", "type": "main", "index": 0}],
                                    [{"node": "Build Failure Message", "type": "main", "index": 0}]]},
    "Build Failure Message": {"main": [[{"node": "Alert - Failed Review", "type": "main", "index": 0}]]},
    "Commit State & Build Draft": {"main": [[{"node": "Send Draft to Chairman", "type": "main", "index": 0}]]},
}

pipeline = {
    "id": PIPELINE_ID,
    "name": "CoinorChronicles v2 - Draft for Review",
    "active": False,
    "nodes": nodes,
    "connections": connections,
    "settings": {"executionOrder": "v1", "errorWorkflow": ERROR_WORKFLOW_ID, "timezone": "Africa/Lagos"},
}

# ----------------------------------------------------------------------------
# error alert workflow
# ----------------------------------------------------------------------------
ERR_JS = r"""
const e = $input.first().json;
const wf = e.workflow?.name || 'unknown workflow';
const node = e.execution?.lastNodeExecuted || 'unknown node';
const msg = String(e.execution?.error?.message || 'no message').slice(0, 600);
return [{ json: { text: `🚨 n8n workflow failed: ${wf}\nNode: ${node}\n${msg}\n\nExecution: ${e.execution?.id || '?'}` } }];
"""
error_wf = {
    "id": ERROR_WORKFLOW_ID,
    "name": "CoinorChronicles - Error Alert",
    "active": False,
    "nodes": [
        {"parameters": {}, "id": "err_trigger", "name": "Error Trigger", "type": "n8n-nodes-base.errorTrigger", "typeVersion": 1, "position": [0, 0]},
        code("err_code", "Format Alert", ERR_JS, [240, 0]),
        telegram("err_tg", "Alert Chairman", [480, 0]),
    ],
    "connections": {
        "Error Trigger": {"main": [[{"node": "Format Alert", "type": "main", "index": 0}]]},
        "Format Alert": {"main": [[{"node": "Alert Chairman", "type": "main", "index": 0}]]},
    },
    "settings": {"executionOrder": "v1"},
}

if __name__ == "__main__":
    # n8n's import expects a LIST of workflows for import:workflow --separate-less input
    json.dump(pipeline, open("coinor_v2_pipeline.json", "w"), indent=2, ensure_ascii=False)
    json.dump(error_wf, open("coinor_v2_error_alert.json", "w"), indent=2, ensure_ascii=False)
    print("wrote coinor_v2_pipeline.json (%d nodes) and coinor_v2_error_alert.json (%d nodes)" % (len(nodes), len(error_wf["nodes"])))
