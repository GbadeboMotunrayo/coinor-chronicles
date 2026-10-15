#!/usr/bin/env python3
"""
CoinorChronicles engine (v3, autonomous).

One run = fetch live data -> plan -> write an episode -> check it against the style
guide's hard rules IN CODE -> (optional model review) -> publish to the feed.
If a check fails, the engine regenerates with the exact violations as feedback, up to
MAX_ATTEMPTS. If every attempt fails, NOTHING is published and the Chairman is told.

Safety rails that replace a human reading each episode
  * deterministic rule checker (banned words, real-world names, advice language,
    links/wallets, length, structure)
  * daily cap, minimum gap between episodes
  * kill switch: touch /etc/coinor/PAUSE   (or: coinor.py pause on)
  * fail closed: any doubt means no publish
  * every publish and every refusal is logged (JSON lines) and the Chairman is notified

Commands
  run [--dry-run]        full cycle (this is what the scheduler calls)
  status                 show state, cap, last run, feed size
  pause on|off           kill switch
  unpublish N            remove an episode from the feed
"""
import argparse, datetime as dt, hashlib, json, math, os, re, subprocess, sys, time
import urllib.error, urllib.request

CFG_PATH = os.environ.get("COINOR_CONFIG", "/etc/coinor/config.json")
DEFAULT_CFG = {
    "feed_dir": "/var/www/coinor/feed",
    "state_path": "/var/lib/coinor/state.json",
    "last_run_path": "/var/lib/coinor/last_run.json",
    "cache_dir": "/var/lib/coinor/cache",
    "log_path": "/var/log/coinor/run.log",
    "pause_file": "/etc/coinor/PAUSE",
    "env_file": "/home/hermes/.hermes/.env",
    "daily_cap": 2,
    "min_gap_hours": 4,
    "max_attempts": 3,
    "keep_episodes": 500,
    "word_min": 250,
    "word_max": 500,
    "chairman": "telegram:932372452",
    "site_url": "https://coinorchonicles.space",
    "raw_base": "https://raw.githubusercontent.com/GbadeboMotunrayo/coinor-chronicles/main",
    "models": ["google/gemma-4-31b-it:free", "nvidia/nemotron-3-super-120b-a12b:free", "google/gemma-4-26b-a4b-it:free"],
    "review_models": ["poolside/laguna-s-2.1:free", "google/gemma-4-26b-a4b-it:free"],
    "review_blocks": False,   # advisory by default: the rule checker in code is the gate
    "review_timeout": 90,
    "notify": True,
}

# ----------------------------------------------------------------------------- world
CLANS = {
    "ancients":   {"name": "Clan of the Ancients", "territory": "The Obsidian Citadel", "coins": ["BTC", "ETH", "LTC", "BNB", "XRP"]},
    "swift":      {"name": "Clan of the Swift", "territory": "The Electric Plains", "coins": ["SOL", "TON", "AVAX", "XLM", "TRX"]},
    "meme_lords": {"name": "Clan of Meme Lords", "territory": "Kekiston Bazaar", "coins": ["PEPE", "SHIB", "DOGE", "FLOKI", "NOT", "BOME", "BONK"]},
    "builders":   {"name": "Clan of the Builders", "territory": "The Forge of Chains", "coins": ["ADA", "SUI", "JASMY"]},
}
# Characters follow docs/CHARACTER_VOICES.md. LINK/UNI/AAVE/INJ have no character yet.
CHARACTER = {
    "BTC": "Aragorn", "ETH": "Gandalf", "LTC": "Samwise", "BNB": "Elrond", "XRP": "Boromir",
    "SOL": "Legolas", "TON": "Eomer", "AVAX": "Eowyn", "XLM": "Faramir", "TRX": "Saruman",
    "ADA": "Treebeard", "SUI": "Gimli", "JASMY": "Galadriel",
    "PEPE": "Tom Bombadil", "SHIB": "Merry", "DOGE": "Pippin", "FLOKI": "Theoden", "NOT": "Frodo",
    "BOME": "Bilbo", "BONK": "Gollum",
}
CLAN_OF = {c: k for k, v in CLANS.items() for c in v["coins"]}
GATES = {  # SUI is an assumption (not in the original design)
    "BTC": (100000, 1000000, 10000000), "ETH": (10000, 100000, 1000000), "SOL": (1000, 10000, 100000),
    "XRP": (100, 1000, 10000), "BNB": (10000, 100000, 1000000), "LTC": (1000, 10000, 100000),
    "TON": (10, 100, 1000), "AVAX": (500, 5000, 50000), "XLM": (5, 50, 500), "TRX": (1, 10, 100),
    "ADA": (10, 100, 1000), "SUI": (10, 100, 1000), "JASMY": (1, 10, 100),
    "PEPE": (1, 10, 100), "SHIB": (0.001, 0.01, 0.1), "DOGE": (10, 100, 1000), "FLOKI": (1, 10, 100),
    "NOT": (0.1, 1, 10), "BOME": (0.1, 1, 10), "BONK": (1, 10, 100),
}
MAX_GATE = 3
CG_IDS = {
    "bitcoin": "BTC", "ethereum": "ETH", "litecoin": "LTC", "binancecoin": "BNB", "ripple": "XRP", "solana": "SOL",
    "the-open-network": "TON", "avalanche-2": "AVAX", "stellar": "XLM", "tron": "TRX", "cardano": "ADA", "sui": "SUI",
    "jasmycoin": "JASMY", "pepe": "PEPE", "shiba-inu": "SHIB", "dogecoin": "DOGE", "floki": "FLOKI",
    "notcoin": "NOT", "book-of-meme": "BOME", "bonk": "BONK",
}
CONDITION_NAMES = {"golden_season": "The Golden Season", "dark_siege": "The Dark Siege", "waiting_plains": "The Waiting Plains"}
INITIAL_MEMORY = {
    "ancients": ("The chronicle begins. Aragorn speaks for the first time.", "Aragorn reclaims his position after the wars."),
    "swift": ("The chronicle begins. The Electric Plains await.", "Legolas leads the clan through volatile times."),
    "meme_lords": ("The chronicle begins. The Bazaar awakens.", "Merry marches toward his $1 dream. The Bazaar endures."),
    "builders": ("The chronicle begins. The Forge fires are lit.", "The Builders construct in silence."),
}


def gate_price(coin, gate):
    return GATES[coin][gate - 1]


def heaven_for(coin, gate, price):
    gp = gate_price(coin, gate)
    prev = gate_price(coin, gate - 1) if gate > 1 else 0
    h = math.ceil(((price - prev) / (gp - prev)) * 10)
    return max(1, min(10, h))


def baseline_gate(coin, price):
    g = 1
    while g < MAX_GATE and price >= gate_price(coin, g):
        g += 1
    return g


# ----------------------------------------------------------------------------- rule checker
BANNED_WORDS = [
    r"crypto\w*", r"prices?", r"markets?", r"percent\w*", r"buy\w*", r"bought", r"sell\w*", r"sold",
    r"trad(?:e|es|ed|ing|er|ers)", r"invest(?:s|ed|ing|ments?|ors?)?", r"profit\w*", r"guarantee\w*", r"wallets?", r"donat\w*", r"airdrops?",
]
REAL_WORLD_NAMES = [
    "USA", "U.S.", "United States", "America", "American", "Federal Reserve", "SEC", "Congress", "White House",
    "China", "Chinese", "European Union", "ECB", "MiCA", "Russia", "Russian", "Britain", "UK", "IMF", "World Bank",
    "Nigeria", "Nigerian", "Africa", "African", "Japan", "Korea", "India", "UAE", "Dubai", "El Salvador",
    "Binance", "Bybit", "Coinbase", "Kraken", "OKX", "Uniswap", "BlackRock", "Grayscale", "MicroStrategy",
    "Tether", "USDT", "USDC", "Bitcoin", "Ethereum", "Solana", "Litecoin", "Ripple", "Dogecoin", "Shiba",
    "Cardano", "Toncoin", "Notcoin", "Twitter", "Elon", "Musk", "Trump", "Biden", "Fed",
]
TICKERS = list(GATES.keys()) + ["USD", "ETF", "NFT", "DeFi", "HODL", "FOMO", "FUD"]
ADVICE_PHRASES = ["financial advice", "to the moon", "not advice", "do your own research", "price target"]
META_LEAKS = [r"as an ai", r"language model", r"here is the", r"here's the", r"^sure[,!]", r"as requested", r"word count"]


def split_story(text):
    """-> (title, narrative, scene_notes, memory_line). Narrative excludes title, scene notes, memory."""
    text = text.replace("\r\n", "\n").strip()
    mem = ""
    kept = []
    for line in text.split("\n"):
        if line.strip().startswith("[MEMORY]"):
            mem = line.strip()[len("[MEMORY]"):].strip()
        else:
            kept.append(line)
    text = "\n".join(kept).strip()
    scene = ""
    i = text.find("[SCENE NOTES]")
    if i >= 0:
        scene = text[i + len("[SCENE NOTES]"):].strip()
        text = text[:i].strip()
    lines = text.split("\n")
    title = re.sub(r"[*#]", "", lines[0]).strip()
    narrative = "\n".join(lines[1:]).strip()
    return title, narrative, scene, mem


def mentioned(name, low):
    """Whole-word, case-insensitive character match (first name or last name)."""
    for part in {name, name.split()[0], name.split()[-1]}:
        if re.search(r"(?<![a-z])" + re.escape(part.lower()) + r"(?![a-z])", low):
            return True
    return False


def words(s):
    return re.findall(r"[A-Za-z][A-Za-z'’-]*", s)


def check_episode(text, plan, cfg, recent_titles=()):
    """Return a list of violations (empty list = pass). Pure function: no I/O."""
    v = []
    title, body, scene, _ = split_story(text)
    full = title + "\n" + body
    if not (6 <= len(title) <= 120):
        v.append(f"Title must be 6-120 characters (got {len(title)}). Put the title alone on the first line.")
    n = len(words(body))
    if n < cfg["word_min"] or n > cfg["word_max"]:
        v.append(f"Length is {n} words; it must be between {cfg['word_min']} and {cfg['word_max']} (aim for 380).")
    paras = [p for p in re.split(r"\n\s*\n", body) if p.strip()]
    if len(paras) < 4:
        v.append("Use at least 4 separate paragraphs.")
    for pat in BANNED_WORDS:
        for m in re.finditer(r"(?<![A-Za-z])" + pat + r"(?![A-Za-z])", full, re.I):
            v.append(f'Banned word "{m.group(0)}". Use in-world language only (roads, provisions, gold, the Bazaar). Rephrase the sentence.')
            break
    if "%" in full:
        v.append('The "%" sign appears. Convert every percentage into "gold units heavier/lighter".')
    for name in REAL_WORLD_NAMES:
        if re.search(r"(?<![A-Za-z])" + re.escape(name) + r"(?![A-Za-z])", full):
            v.append(f'Real-world name "{name}" appears untranslated. Use the Coinor name from the style guide (e.g. use the character name, never the coin or country).')
    for t in TICKERS:
        if re.search(r"(?<![A-Za-z])" + re.escape(t) + r"(?![A-Za-z])", full):
            v.append(f'Ticker/jargon "{t}" appears. Never use tickers or finance jargon; use the character name.')
            break
    low = full.lower()
    for ph in ADVICE_PHRASES:
        if ph in low:
            v.append(f'Advice-style phrase "{ph}" is not allowed in the story.')
    if re.search(r"https?://|www\.|@\w{3,}|\b0x[0-9a-fA-F]{20,}\b|\b[\w.+-]+@[\w-]+\.\w+\b|\+?\d{3}[\s.-]?\d{3}[\s.-]?\d{4}", full):
        v.append("The story must not contain links, handles, emails, wallet addresses or phone numbers.")
    for pat in META_LEAKS:
        if re.search(pat, low, re.M):
            v.append("The text contains assistant/meta language. Output only the title, the story, [SCENE NOTES] and the [MEMORY] line.")
            break
    if re.search(r"^\s*#{1,6}\s", body, re.M):
        v.append("Do not use markdown headings inside the story.")
    if title.strip().lower() in {t.strip().lower() for t in recent_titles}:
        v.append("This title was already used recently. Choose a new title.")
    if plan["mode"] == "story":
        if "gold unit" not in low or len(re.findall(r"gold units?", low)) < 2:
            v.append('Real movements must appear as numbers in "gold units" (at least twice).')
        if "pepe" not in low[:900]:
            v.append("Aragorn must open by speaking to Pepe (name Pepe near the start).")
        if "?" not in body:
            v.append("Pepe must ask at least one question (a '?' must appear).")
        names = [CHARACTER[c] for c in CLANS[plan["clanKey"]]["coins"] if c in CHARACTER]
        hit = [n_ for n_ in names if mentioned(n_, low)]
        if len(hit) < math.ceil(len(names) / 2):
            v.append(f"Name at least half of this clan's characters: {', '.join(names)}.")
        stray = [CHARACTER[c] for k, cl in CLANS.items() if k != plan["clanKey"] for c in cl["coins"]
                 if c in CHARACTER and CHARACTER[c] != "Aragorn" and mentioned(CHARACTER[c], low)]
        if len(stray) > 1:
            v.append(f"Stay in one clan. These characters belong to other clans: {', '.join(stray)}.")
    else:
        c = plan["celebration"]
        if not mentioned(c["character"], low):
            v.append(f"The celebration must name {c['character']}.")
    return v


# ----------------------------------------------------------------------------- engine
class Engine:
    def __init__(self, cfg, now=None, http=None, llm=None, notifier=None):
        self.cfg = cfg
        self.now = now or (lambda: dt.datetime.now(dt.timezone.utc))
        self._http = http or self._real_http
        self._llm = llm or self._real_llm
        self._notify = notifier or self._real_notify

    # --- io helpers
    def log(self, event, **kw):
        rec = {"t": self.now().isoformat(timespec="seconds"), "event": event, **kw}
        try:
            os.makedirs(os.path.dirname(self.cfg["log_path"]), exist_ok=True)
            with open(self.cfg["log_path"], "a", encoding="utf-8") as f:
                f.write(json.dumps(rec, ensure_ascii=False) + "\n")
        except OSError:
            pass
        print(json.dumps(rec, ensure_ascii=False), file=sys.stderr)

    @staticmethod
    def _real_http(url, timeout=30, headers=None):
        req = urllib.request.Request(url, headers=headers or {"User-Agent": "coinor-engine/3"})
        with urllib.request.urlopen(req, timeout=timeout) as r:
            return r.read().decode("utf-8")

    def _api_key(self):
        for line in open(self.cfg["env_file"], encoding="utf-8").read().splitlines():
            if line.startswith("OPENROUTER_API_KEY="):
                return line.split("=", 1)[1].strip().strip("\"'")
        raise RuntimeError("OPENROUTER_API_KEY not found")

    def _real_llm(self, system, user, max_tokens, models=None, timeout=240, temperature=0.9):
        body = json.dumps({"models": models or self.cfg["models"], "max_tokens": max_tokens, "temperature": temperature,
                           "reasoning": {"effort": "low"},
                           "messages": [{"role": "system", "content": system}, {"role": "user", "content": user}]}).encode()
        req = urllib.request.Request("https://openrouter.ai/api/v1/chat/completions", body, {
            "Authorization": "Bearer " + self._api_key(), "Content-Type": "application/json",
            "HTTP-Referer": self.cfg["site_url"], "X-Title": "CoinorChronicles"})
        try:
            with urllib.request.urlopen(req, timeout=timeout) as r:
                d = json.loads(r.read().decode("utf-8"))
        except urllib.error.HTTPError as e:
            return None, f"http {e.code}"
        except Exception as e:  # network, timeout
            return None, type(e).__name__
        text = ((d.get("choices") or [{}])[0].get("message") or {}).get("content")
        return (text.strip() if text else None), d.get("model")

    def _real_notify(self, msg):
        if not self.cfg.get("notify"):
            return
        try:
            cmd = ["hermes", "send", "--to", self.cfg["chairman"]]
            if os.geteuid() == 0:                      # root has no Telegram config; hermes does
                cmd = ["sudo", "-u", "hermes"] + cmd
            subprocess.run(cmd,
                           input=msg.encode(), timeout=60, check=False, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        except Exception as e:
            self.log("notify_failed", error=str(e))

    @staticmethod
    def _read_json(path, default):
        try:
            with open(path, encoding="utf-8") as f:
                return json.load(f)
        except (OSError, ValueError):
            return default

    @staticmethod
    def _write_json(path, data, mode=0o644):
        os.makedirs(os.path.dirname(path), exist_ok=True)
        tmp = path + ".tmp"
        with open(tmp, "w", encoding="utf-8") as f:
            json.dump(data, f, ensure_ascii=False, indent=2)
            f.write("\n")
        os.chmod(tmp, mode)
        os.replace(tmp, path)

    # --- data
    def get_prices(self):
        ids = ",".join(CG_IDS)
        url = f"https://api.coingecko.com/api/v3/simple/price?ids={ids}&vs_currencies=usd&include_24hr_change=true"
        last = None
        for i in range(4):
            try:
                raw = json.loads(self._http(url, 30))
                break
            except Exception as e:
                last = e
                time.sleep(3 * (i + 1) if self._http == self._real_http else 0)
        else:
            raise RuntimeError(f"CoinGecko unavailable: {last}")
        prices = {}
        for cg, d in raw.items():
            t = CG_IDS.get(cg)
            if not t or not isinstance(d.get("usd"), (int, float)):
                continue
            ch = float(d.get("usd_24h_change") or 0)
            prices[t] = {"price": d["usd"], "change_24h": round(ch, 2), "gold_units": round(abs(ch), 1),
                         "direction": "heavier" if ch >= 0 else "lighter"}
        if "BTC" not in prices or "ETH" not in prices:
            raise RuntimeError("CoinGecko returned no BTC/ETH: refusing to write on bad data")
        avg = (prices["BTC"]["change_24h"] + prices["ETH"]["change_24h"]) / 2
        cond = "golden_season" if avg > 0.5 else "dark_siege" if avg < -0.5 else "waiting_plains"
        return prices, cond

    def get_guide(self):
        files = {"world_bible": "docs/WORLD_BIBLE.md", "character_voices": "docs/CHARACTER_VOICES.md",
                 "external_realms": "docs/EXTERNAL_REALMS.md", "cosmology": "docs/COSMOLOGY.md",
                 "script_generator": "prompts/script_generator.md", "review_agent": "prompts/review_agent.md",
                 "celebration": "prompts/celebration_episode.md"}
        out = {}
        os.makedirs(self.cfg["cache_dir"], exist_ok=True)
        for k, p in files.items():
            cache = os.path.join(self.cfg["cache_dir"], k + ".md")
            try:
                txt = self._http(f"{self.cfg['raw_base']}/{p}", 30)
                if len(txt) < 200:
                    raise ValueError("too short")
                open(cache, "w", encoding="utf-8").write(txt)
            except Exception:
                try:
                    txt = open(cache, encoding="utf-8").read()
                except OSError:
                    raise RuntimeError(f"style guide file unavailable and not cached: {p}")
            out[k] = txt
        out["style_guide"] = "\n\n---\n\n".join(out[k] for k in ("world_bible", "character_voices", "external_realms", "cosmology"))
        return out

    # --- state
    def new_state(self, prices):
        coins = {}
        for c in CHARACTER:
            if c in prices:
                g = baseline_gate(c, prices[c]["price"])
                coins[c] = {"gate": g, "heaven": heaven_for(c, g, prices[c]["price"]), "price": prices[c]["price"]}
        return {"init": True, "episode": 0, "rotation": [], "milestones": [], "recent": [], "published_at": [],
                "memory": {k: {"summary": s, "direction": d} for k, (s, d) in INITIAL_MEMORY.items()}, "coins": coins}

    def plan(self, S, prices, cond, guide):
        first_run = False
        for c in CHARACTER:
            if c in prices and c not in S["coins"]:
                g = baseline_gate(c, prices[c]["price"])
                S["coins"][c] = {"gate": g, "heaven": heaven_for(c, g, prices[c]["price"]), "price": prices[c]["price"]}
        cands = []
        for c in CHARACTER:
            if c not in prices:
                continue
            st, p = S["coins"][c], prices[c]["price"]
            if st["gate"] < MAX_GATE and p >= gate_price(c, st["gate"]):
                key = f"{c}|gate|{st['gate']}"
                if key not in S["milestones"]:
                    cands.append({"coin": c, "type": "gate", "value": st["gate"], "key": key, "price": p, "character": CHARACTER[c], "priority": 2})
            else:
                h = heaven_for(c, st["gate"], p)
                if h > st["heaven"]:
                    key = f"{c}|heaven|{st['gate']}|{h}"
                    if key not in S["milestones"]:
                        cands.append({"coin": c, "type": "heaven", "value": h, "key": key, "price": p, "character": CHARACTER[c], "priority": 1})
        cands.sort(key=lambda x: -x["priority"])
        cel = cands[0] if cands else None
        clan_key = CLAN_OF[cel["coin"]] if cel else None
        if not clan_key:
            for k, cl in CLANS.items():
                if any(c in prices and abs(prices[c]["change_24h"]) > 12 for c in cl["coins"]):
                    clan_key = k
                    break
        if not clan_key:
            clan_key = next((k for k in CLANS if k not in S["rotation"]), S["rotation"][-1] if S["rotation"] else "ancients")
        clan = CLANS[clan_key]
        coin_lines = "\n".join(f"  - {c}: ${prices[c]['price']} | {prices[c]['change_24h']}% | {prices[c]['gold_units']} gold units {prices[c]['direction']}" for c in clan["coins"] if c in prices)
        heaven_lines = "\n".join(f"  - {CHARACTER[c]}: {c} | Heaven {S['coins'][c]['heaven']} of Gate {S['coins'][c]['gate']} | Last price: ${S['coins'][c]['price']}" for c in clan["coins"] if c in S["coins"])
        mem = S["memory"][clan_key]
        episode = S["episode"] + 1
        memory_rule = ("\n\nOUTPUT RULES FOR THIS RUN:\n1. First line: the episode title alone, no markdown.\n"
                       "2. Then the story, 300-450 words, in separate paragraphs.\n"
                       "3. Then a [SCENE NOTES] block.\n"
                       "4. Finally ONE line starting with [MEMORY] and a two-sentence summary for continuity.\n"
                       "5. NEVER write the words: crypto, price, market, percent, buy, sell, trade, invest, profit. Never write the % sign. "
                       "Never name real coins, countries, companies or tickers; use character names. "
                       "Write 'roads' or 'routes', not 'trade routes'. Every movement is expressed as 'X gold units heavier/lighter'. "
                       "Output nothing else: no preface, no commentary.")
        if cel:
            mode = "celebration"
            prompt = (f"MILESTONE_TYPE: {cel['type'].upper()}_CROSSING\nCHARACTER: {cel['character']}\nCOIN: {cel['coin']}\n"
                      f"MILESTONE: {'Gate' if cel['type'] == 'gate' else 'Heaven'} {cel['value']}\nPRICE_AT_MILESTONE: ${cel['price']}\n"
                      f"DATE: {self.now().date().isoformat()}\n\nWrite a full celebration episode for this milestone, following the celebration_episode.md structure exactly.{memory_rule}")
            system_extra = guide["celebration"]
        else:
            mode = "story"
            prompt = (f"SELECTED_CLAN: {clan['name']} - {clan['territory']}\nMARKET_CONDITION: {CONDITION_NAMES[cond]}\nMARKET_DATA:\n{coin_lines}\n"
                      f"LAST_EPISODE_SUMMARY: {mem['summary']}\nSTORY_DIRECTION: {mem['direction']}\nCURRENT_HEAVENS:\n{heaven_lines}\n"
                      f"CREATOR_OVERRIDE: None - generate naturally from market data\nSTORY_COUNT: {episode}\n\nNow write the episode following all rules in the style guide.{memory_rule}")
            system_extra = guide["script_generator"]
        system = "You are the official story narrator of CoinorChronicles.\n\n" + system_extra + "\n\n" + guide["style_guide"]
        return {"mode": mode, "clanKey": clan_key, "clan_name": clan["name"], "episode": episode, "condition": cond,
                "prompt": prompt, "system": system, "candidates": cands, "celebration": cel, "first_run": first_run}

    def commit(self, S, plan, prices, title, body, scene, mem, checks):
        for m in plan["candidates"]:
            if m["key"] not in S["milestones"]:
                S["milestones"].append(m["key"])
        for c, st in S["coins"].items():
            if c not in prices:
                continue
            if st["gate"] < MAX_GATE and any(m["coin"] == c and m["type"] == "gate" for m in plan["candidates"]):
                st["gate"] += 1
            st["heaven"] = heaven_for(c, st["gate"], prices[c]["price"])
            st["price"] = prices[c]["price"]
        S["episode"] = plan["episode"]
        S["rotation"] = ([plan["clanKey"]] + [k for k in S["rotation"] if k != plan["clanKey"]])[:4]
        summary = mem if len(mem) > 20 else re.sub(r"\s+", " ", body)[-280:]
        S["memory"][plan["clanKey"]] = {"summary": summary, "direction": S["memory"][plan["clanKey"]].get("direction", "")}
        S["recent"] = ([{"ep": plan["episode"], "title": title, "clan": plan["clanKey"], "mode": plan["mode"]}] + S["recent"])[:30]
        S["published_at"] = (S.get("published_at", []) + [self.now().isoformat(timespec="seconds")])[-50:]

    # --- model calls
    def review(self, guide, body, plan):
        sysmsg = guide["review_agent"] + ("\n\nIMPORTANT: The donation-appeal rule is DISABLED (no wallets exist); ignore it. "
                                          f"This is episode {plan['episode']}.")
        text, model = self._llm(sysmsg, "Review this story script:\n\n---\n" + body + "\n---", 1500,
                                models=self.cfg["review_models"], timeout=self.cfg["review_timeout"], temperature=0.2)
        if not text:
            return "unavailable", ""
        up = text.upper()
        if "REVIEW RESULT: PASS" in up:
            return "pass", text
        if "REVIEW RESULT: FAIL" in up:
            return "fail", text
        return "unavailable", text

    # --- main cycle
    def run(self, dry_run=False):
        cfg = self.cfg
        t0 = self.now()

        def done(outcome, **kw):
            res = {"outcome": outcome, "at": self.now().isoformat(timespec="seconds"), **kw}
            self._write_json(cfg["last_run_path"], res)
            self.log("run_" + outcome, **kw)
            print(json.dumps(res, ensure_ascii=False))
            return res

        if os.path.exists(cfg["pause_file"]):
            return done("paused")
        S = self._read_json(cfg["state_path"], None)
        feed_path = os.path.join(cfg["feed_dir"], "episodes.json")
        feed = self._read_json(feed_path, {"episodes": []})
        try:
            prices, cond = self.get_prices()
        except Exception as e:
            self._notify(f"CoinorChronicles: could not fetch live data ({e}). No episode this run.")
            return done("error_data", error=str(e))
        if cond == "waiting_plains":
            if S is None:
                S = self.new_state(prices)  # silent baseline so a quiet start is still recorded
                if not dry_run:
                    self._write_json(cfg["state_path"], S)
            return done("skipped_flat", note="Waiting Plains: no episode by design")
        pubs = [dt.datetime.fromisoformat(x) for x in (S or {}).get("published_at", [])]
        today = [p for p in pubs if p.date() == t0.date()]
        if len(today) >= cfg["daily_cap"]:
            return done("skipped_cap", published_today=len(today))
        if pubs and (t0 - max(pubs)).total_seconds() < cfg["min_gap_hours"] * 3600:
            return done("skipped_gap", last=max(pubs).isoformat(timespec="seconds"))
        try:
            guide = self.get_guide()
        except Exception as e:
            self._notify(f"CoinorChronicles: style guide unavailable ({e}). No episode this run.")
            return done("error_guide", error=str(e))

        first_run = S is None
        if first_run:
            S = self.new_state(prices)
        plan = self.plan(S, prices, cond, guide)
        plan["first_run"] = first_run
        recent_titles = [e.get("title", "") for e in feed.get("episodes", [])[-50:]]
        feedback = ""
        attempts = []
        for attempt in range(1, cfg["max_attempts"] + 1):
            user = plan["prompt"] + (("\n\n## PREVIOUS ATTEMPT BROKE THESE RULES. FIX EVERY ONE:\n" + feedback) if feedback else "")
            text, model = self._llm(plan["system"], user, 7000)
            if not text or len(text) < 400:
                attempts.append({"attempt": attempt, "model": model, "problem": "empty or too short"})
                self.log("attempt_empty", attempt=attempt, model=model)
                feedback = "The previous attempt returned no usable text. Write the full episode."
                continue
            problems = check_episode(text, plan, cfg, recent_titles)
            self.log("attempt_checked", attempt=attempt, model=model, violations=len(problems))
            if problems:
                attempts.append({"attempt": attempt, "model": model, "violations": problems[:8]})
                feedback = "\n".join("- " + p for p in problems[:10])
                continue
            title, body, scene, mem = split_story(text)
            verdict, notes = self.review(guide, title + "\n\n" + body, plan)
            if verdict == "fail" and cfg["review_blocks"]:
                attempts.append({"attempt": attempt, "model": model, "model_review": "fail", "notes": notes[:400]})
                feedback = "A second reviewer rejected it:\n" + notes[:900]
                continue
            checks = {"rules": "pass", "model_review": verdict, "attempt": attempt, "model": model}
            if dry_run:
                return done("dry_run_ok", episode=plan["episode"], title=title, words=len(words(body)), checks=checks, clan=plan["clanKey"], mode=plan["mode"], body=body)
            ep = {"number": plan["episode"], "title": title, "clan": plan["clanKey"], "condition": cond,
                  "date": t0.date().isoformat(), "published_at": t0.isoformat(timespec="seconds"), "status": "approved",
                  "mode": plan["mode"], "body": body, "scene_notes": scene, "checks": checks}
            eps = [e for e in feed.get("episodes", []) if e.get("number") != ep["number"]] + [ep]
            feed = {"episodes": eps[-cfg["keep_episodes"]:], "updated": t0.isoformat(timespec="seconds"), "engine": "coinor-v3"}
            self.commit(S, plan, prices, title, body, scene, mem, checks)
            self._write_json(feed_path, feed)       # feed first, then state: a crash re-publishes rather than skips
            self._write_json(cfg["state_path"], S)
            self._notify(f"Chronicle published: Episode {ep['number']}, \"{title}\".\n{cfg['site_url']}/#/episode/{ep['number']}\n"
                         f"Checks: rules passed (enforced in code), second-opinion review: {verdict}. To take it down: coinor.py unpublish {ep['number']}")
            return done("published", episode=ep["number"], title=title, clan=plan["clanKey"], checks=checks)
        # every attempt failed: publish nothing, change nothing, tell the Chairman
        self._notify(f"CoinorChronicles: episode {plan['episode']} ({plan['clan_name']}) failed the safety checks {cfg['max_attempts']} times. "
                     f"Nothing was published and no state changed. Last problems: " + "; ".join((attempts[-1].get("violations") or [attempts[-1].get("problem", "unknown")])[:3]))
        return done("failed_checks", episode=plan["episode"], attempts=attempts)


def load_cfg():
    cfg = dict(DEFAULT_CFG)
    try:
        cfg.update(json.load(open(CFG_PATH, encoding="utf-8")))
    except (OSError, ValueError):
        pass
    return cfg


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = ap.add_subparsers(dest="cmd", required=True)
    r = sub.add_parser("run"); r.add_argument("--dry-run", action="store_true")
    sub.add_parser("status")
    p = sub.add_parser("pause"); p.add_argument("state", choices=["on", "off"])
    u = sub.add_parser("unpublish"); u.add_argument("number", type=int)
    a = ap.parse_args(argv)
    cfg = load_cfg()
    eng = Engine(cfg)
    if a.cmd == "run":
        eng.run(dry_run=a.dry_run)
    elif a.cmd == "status":
        S = eng._read_json(cfg["state_path"], {})
        feed = eng._read_json(os.path.join(cfg["feed_dir"], "episodes.json"), {"episodes": []})
        last = eng._read_json(cfg["last_run_path"], {})
        now = dt.datetime.now(dt.timezone.utc)
        today = [x for x in S.get("published_at", []) if x[:10] == now.date().isoformat()]
        print(json.dumps({"paused": os.path.exists(cfg["pause_file"]), "episodes_in_feed": len(feed.get("episodes", [])),
                          "published_today": len(today), "daily_cap": cfg["daily_cap"], "last_run": last,
                          "episode_counter": S.get("episode")}, indent=2))
    elif a.cmd == "pause":
        if a.state == "on":
            os.makedirs(os.path.dirname(cfg["pause_file"]), exist_ok=True)
            open(cfg["pause_file"], "w").write("paused " + dt.datetime.now().isoformat() + "\n")
            print("PAUSED: no episodes will be published until: coinor.py pause off")
        else:
            try:
                os.remove(cfg["pause_file"])
            except FileNotFoundError:
                pass
            print("Resumed.")
    elif a.cmd == "unpublish":
        path = os.path.join(cfg["feed_dir"], "episodes.json")
        feed = eng._read_json(path, {"episodes": []})
        keep = [e for e in feed["episodes"] if e.get("number") != a.number]
        if len(keep) == len(feed["episodes"]):
            sys.exit(f"Episode {a.number} is not in the feed.")
        feed["episodes"] = keep
        feed["updated"] = dt.datetime.now(dt.timezone.utc).isoformat(timespec="seconds")
        eng._write_json(path, feed)
        print(f"Episode {a.number} removed from the feed.")


if __name__ == "__main__":
    main()
