import datetime as dt, json, os, shutil, tempfile, unittest
import coinor as C

GOOD = """The Bazaar Holds Its Breath

Sit, young Pepe. The storm has found Kekiston Bazaar tonight, and the lanterns sway on their chains. Let me tell you what the roads whispered while you slept.

Pepe leans closer to the fire. "Aragorn, will the stalls stand through this?" he asks, and his voice is small against the wind.

Tom Bombadil walks the shuttered lanes as though the sky were a gentle ceiling. He is 6.4 gold units lighter tonight, and he sings of green candles and old rivers, untroubled. Some say he is outside every law of the realm. Tonight, that seems true.

Merry does not retreat. That is the first thing you must understand about him. While the Bazaar grew 3.6 gold units lighter, while the lanterns dimmed and the crowds thinned, he planted his feet on the road to his first Gate and refused to move backward. Stubborn, quiet, defiant: still in Heaven one, still walking.

Pippin found the siege harder. He is 4.8 gold units lighter, and he calls his stumbling a search for better ground. The fellowship smiles, because that is Pippin, and because he has survived worse than weather.

Theoden gathers his riders at the eastern gate. He is 7.1 gold units lighter, yet his voice carries across the square: a king who has fallen before knows the shape of the climb back. Frodo sits beside the well with the weight no one else can see, 7.5 gold units lighter, and says only that someone has to carry it.

Bilbo keeps writing. His ink is 13.3 gold units lighter than yesterday, but the Book grows heavier with every line, and he calls that a fair bargain for a chronicler.

Pepe watches the lights flicker along the lanes. "Will the road be shorter tomorrow?" he asks. Aragorn does not answer at once. He has seen darker nights than this one, and he knows the gates still glow beyond the storm.

[SCENE NOTES]
- Scene 1: the Bazaar at dusk, stalls shuttered
- Scene 2: Merry standing firm on a cracked road

[MEMORY] The Bazaar endured a heavy night, each traveller facing the storm in their own way. Merry's march toward his first Gate continued unbroken.
"""

PLAN = {"mode": "story", "clanKey": "meme_lords", "celebration": None, "episode": 5}
CFG = dict(C.DEFAULT_CFG)


def viol(text, plan=PLAN, recent=()):
    return C.check_episode(text, plan, CFG, recent)


class RuleChecker(unittest.TestCase):
    def test_good_story_passes(self):
        self.assertEqual(viol(GOOD), [])

    def test_fixture_length_is_in_range(self):
        _, body, _, _ = C.split_story(GOOD)
        self.assertTrue(250 <= len(C.words(body)) <= 500, len(C.words(body)))

    def test_each_banned_word_is_caught(self):
        for w in ["crypto", "price", "market", "percent", "buy", "sell", "trade", "trading", "invest", "profit", "wallet", "donation", "bought", "sold"]:
            bad = GOOD.replace("The storm has found", f"The {w} has found")
            self.assertTrue(any("Banned word" in v for v in viol(bad)), w)

    def test_percent_sign_is_caught(self):
        self.assertTrue(any("%" in v for v in viol(GOOD.replace("6.4 gold units", "6.4% gold units"))))

    def test_real_world_names_are_caught(self):
        for n in ["Solana", "Bitcoin", "Federal Reserve", "SEC", "China", "Binance", "Nigeria", "Tether"]:
            self.assertTrue(any("Real-world name" in v for v in viol(GOOD.replace("The storm", f"The {n} storm"))), n)

    def test_tickers_are_caught(self):
        self.assertTrue(any("Ticker" in v for v in viol(GOOD.replace("The storm", "The BTC storm"))))

    def test_links_wallets_phones_are_caught(self):
        for junk in ["see https://x.com now", "0x" + "a" * 40, "call 0803 123 4567", "mail me@x.com", "follow @someone"]:
            self.assertTrue(any("links" in v for v in viol(GOOD.replace("The storm", junk))), junk)

    def test_advice_and_meta_language_is_caught(self):
        self.assertTrue(any("Advice" in v for v in viol(GOOD.replace("The storm", "The to the moon storm"))))
        self.assertTrue(any("meta" in v.lower() for v in viol("Here is the story you asked for:\n\n" + GOOD)))

    def test_dialogue_with_cant_is_not_flagged_as_meta(self):
        self.assertEqual(viol(GOOD.replace("the stalls stand", "the stalls stand, because I can't bear it if they fall,")), [])

    def test_too_short_and_too_long(self):
        short = "Title Here Now\n\n" + "\n\n".join(["Sit, young Pepe. gold units gold units?"] * 4)
        self.assertTrue(any("Length" in v for v in viol(short)))
        long_ = GOOD.replace("[SCENE NOTES]", ("Merry waits. " * 200) + "\n\n[SCENE NOTES]")
        self.assertTrue(any("Length" in v for v in viol(long_)))

    def test_structure_rules(self):
        self.assertTrue(any("gold units" in v for v in viol(GOOD.replace("gold units", "golden coins"))))
        self.assertTrue(any("Pepe" in v for v in viol(GOOD.replace("Pepe", "Boy"))))
        self.assertTrue(any("question" in v.lower() for v in viol(GOOD.replace("?", "."))))

    def test_clan_rules(self):
        only_two = GOOD.replace("Theoden", "the king").replace("Frodo", "the guest").replace("Bilbo", "the scribe").replace("Pippin", "the fool")
        self.assertTrue(any("half of this clan" in v for v in viol(only_two)))
        mixed = GOOD.replace("Tom Bombadil walks", "Tom Bombadil and Legolas and Gandalf walk")
        self.assertTrue(any("one clan" in v for v in viol(mixed)))

    def test_aragorn_cameo_is_always_allowed(self):
        self.assertEqual(viol(GOOD), [])  # fixture names Aragorn inside a meme-lords story

    def test_character_match_is_whole_word(self):
        self.assertFalse(C.mentioned("Tom Bombadil", "the bottom of the well"))
        self.assertTrue(C.mentioned("Tom Bombadil", "then bombadil sang"))

    def test_investigate_is_not_banned(self):
        self.assertEqual(viol(GOOD.replace("The storm has found", "The riders investigate")), [])

    def test_duplicate_title_is_caught(self):
        self.assertTrue(any("already used" in v for v in viol(GOOD, recent=["the bazaar holds its breath"])))

    def test_celebration_must_name_character(self):
        plan = {"mode": "celebration", "clanKey": "ancients", "celebration": {"character": "Aragorn", "coin": "BTC"}, "episode": 3}
        body = GOOD.replace("Aragorn", "the elder")
        self.assertTrue(any("must name Aragorn" in v for v in viol(body, plan)))
        self.assertEqual(viol(GOOD, plan), [])

    def test_the_real_failed_sample_is_rejected(self):
        sample = json.load(open(os.path.join(os.path.dirname(__file__), "..", "site", "episodes.sample.json")))["episodes"][0]
        text = sample["title"] + "\n\n" + sample["body"]
        v = viol(text)
        self.assertTrue(any("Solana" in x for x in v), v)
        self.assertTrue(any('"trade"' in x or "trade" in x for x in v), v)


class Plan(unittest.TestCase):
    def prices(self, **over):
        base = {"BTC": 70000, "ETH": 3000, "LTC": 80, "BNB": 600, "XRP": 2, "SOL": 150, "TON": 5, "AVAX": 35, "XLM": 0.1, "TRX": 0.15,
                "ADA": 0.5, "SUI": 2, "JASMY": 0.02, "PEPE": 0.00001, "SHIB": 0.00002, "DOGE": 0.15, "FLOKI": 0.0002, "NOT": 0.01,
                "BOME": 0.01, "BONK": 0.00002}
        base.update(over)
        return {k: {"price": v, "change_24h": 1.0, "gold_units": 1.0, "direction": "heavier"} for k, v in base.items()}

    def setUp(self):
        self.e = C.Engine(dict(C.DEFAULT_CFG), notifier=lambda m: None)
        self.guide = {k: k for k in ["style_guide", "script_generator", "review_agent", "celebration"]}

    def commit(self, S, plan, prices):
        self.e.commit(S, plan, prices, "T", "body " * 80, "", "A summary that is long enough here.", {})

    def test_gates_advance_and_heavens_never_refire(self):
        pr = self.prices()
        S = self.e.new_state(pr)
        self.assertEqual((S["coins"]["BTC"]["gate"], S["coins"]["BTC"]["heaven"]), (1, 7))
        steps = [("BTC", 70000, None), ("BTC", 78000, "BTC|heaven|1|8"), ("BTC", 78000, None), ("BTC", 60000, None),
                 ("BTC", 78000, None), ("BTC", 101000, "BTC|gate|1"), ("BTC", 150000, None)]
        for coin, price, expect in steps:
            p = self.prices(BTC=price)
            plan = self.e.plan(S, p, "dark_siege", self.guide)
            got = plan["celebration"]["key"] if plan["celebration"] else None
            self.assertEqual(got, expect, (price, got))
            self.commit(S, plan, p)
        self.assertEqual(S["coins"]["BTC"]["gate"], 2)

    def test_meme_gate_picks_meme_clan(self):
        S = self.e.new_state(self.prices())
        p = self.prices(DOGE=11)
        plan = self.e.plan(S, p, "golden_season", self.guide)
        self.assertEqual((plan["mode"], plan["clanKey"], plan["celebration"]["key"]), ("celebration", "meme_lords", "DOGE|gate|1"))

    def test_big_mover_overrides_rotation(self):
        p = self.prices(); p["BOME"]["change_24h"] = -13
        S = self.e.new_state(p)
        self.assertEqual(self.e.plan(S, p, "dark_siege", self.guide)["clanKey"], "meme_lords")


class Cycle(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.mkdtemp()
        t = self.tmp
        self.cfg = dict(C.DEFAULT_CFG, feed_dir=f"{t}/feed", state_path=f"{t}/state.json", last_run_path=f"{t}/last.json",
                        cache_dir=f"{t}/cache", log_path=f"{t}/run.log", pause_file=f"{t}/PAUSE", env_file=f"{t}/env")
        self.notes = []
        self.now = dt.datetime(2026, 10, 16, 6, 0, tzinfo=dt.timezone.utc)
        self.llm_texts = []
        self.reviews = []
        self.calls = []
        self.cond_change = -3.0

    def tearDown(self):
        shutil.rmtree(self.tmp, ignore_errors=True)

    def http(self, url, timeout=30, headers=None):
        if "coingecko" in url:
            d = {cg: {"usd": 10, "usd_24h_change": self.cond_change} for cg in C.CG_IDS}
            d["bitcoin"] = {"usd": 70000, "usd_24h_change": self.cond_change}
            d["ethereum"] = {"usd": 3000, "usd_24h_change": self.cond_change}
            d["shiba-inu"] = {"usd": 0.00002, "usd_24h_change": self.cond_change}
            d["bonk"] = {"usd": 0.00002, "usd_24h_change": -13.0}
            return json.dumps(d)
        return "x" * 400  # style guide files

    def llm(self, system, user, max_tokens, **kw):
        self.calls.append(user)
        if system.startswith("You are the official story narrator"):
            return (self.llm_texts.pop(0) if self.llm_texts else GOOD), "fake/model"
        return (self.reviews.pop(0) if self.reviews else "REVIEW RESULT: PASS\nAPPROVAL NOTES: fine"), "fake/review"

    def engine(self):
        return C.Engine(self.cfg, now=lambda: self.now, http=self.http, llm=self.llm, notifier=self.notes.append)

    def run_(self, dry=False):
        import io, contextlib
        with contextlib.redirect_stdout(io.StringIO()), contextlib.redirect_stderr(io.StringIO()):
            return self.engine().run(dry_run=dry)

    def feed(self):
        return json.load(open(f"{self.tmp}/feed/episodes.json"))["episodes"]

    def test_publishes_a_good_episode_and_notifies(self):
        r = self.run_()
        self.assertEqual(r["outcome"], "published")
        eps = self.feed()
        self.assertEqual((len(eps), eps[0]["number"], eps[0]["status"]), (1, 1, "approved"))
        self.assertEqual(eps[0]["checks"]["rules"], "pass")
        self.assertNotIn("MEMORY", eps[0]["body"]); self.assertNotIn("SCENE NOTES", eps[0]["body"])
        self.assertTrue(any("Chronicle published" in n for n in self.notes))
        self.assertEqual(json.load(open(f"{self.tmp}/state.json"))["episode"], 1)

    def test_bad_attempt_is_regenerated_with_feedback(self):
        self.llm_texts = [GOOD.replace("The storm has found", "The crypto market has found"), GOOD]
        r = self.run_()
        self.assertEqual(r["outcome"], "published")
        self.assertEqual(r["checks"]["attempt"], 2)
        self.assertIn("PREVIOUS ATTEMPT BROKE THESE RULES", self.calls[1] if "PREVIOUS" in self.calls[1] else self.calls[2] if len(self.calls) > 2 else "")

    def test_three_bad_attempts_publish_nothing_and_change_nothing(self):
        bad = GOOD.replace("The storm has found", "The crypto market has found")
        self.llm_texts = [bad, bad, bad]
        r = self.run_()
        self.assertEqual(r["outcome"], "failed_checks")
        self.assertFalse(os.path.exists(f"{self.tmp}/feed/episodes.json"))
        self.assertFalse(os.path.exists(f"{self.tmp}/state.json"))
        self.assertTrue(any("failed the safety checks" in n for n in self.notes))

    def test_empty_model_answers_never_publish(self):
        self.llm_texts = ["", "", ""]
        self.assertEqual(self.run_()["outcome"], "failed_checks")
        self.assertFalse(os.path.exists(f"{self.tmp}/feed/episodes.json"))

    def test_model_reviewer_fail_is_advisory_by_default(self):
        self.reviews = ["REVIEW RESULT: FAIL\nSPECIFIC ISSUES: voice drift"]
        r = self.run_()
        self.assertEqual((r["outcome"], r["checks"]["attempt"], r["checks"]["model_review"]), ("published", 1, "fail"))

    def test_model_reviewer_fail_forces_regeneration_when_blocking(self):
        self.cfg["review_blocks"] = True
        self.reviews = ["REVIEW RESULT: FAIL\nSPECIFIC ISSUES: voice drift", "REVIEW RESULT: PASS"]
        r = self.run_()
        self.assertEqual((r["outcome"], r["checks"]["attempt"], r["checks"]["model_review"]), ("published", 2, "pass"))

    def test_verdict_of_exactly_pass_is_parsed(self):
        self.reviews = ["REVIEW RESULT: PASS"]
        self.assertEqual(self.run_()["checks"]["model_review"], "pass")

    def test_unavailable_reviewer_still_publishes_after_rules_pass_but_is_recorded(self):
        self.reviews = ["", ]
        r = self.run_()
        self.assertEqual((r["outcome"], r["checks"]["model_review"]), ("published", "unavailable"))

    def test_daily_cap_and_min_gap(self):
        self.cfg["daily_cap"] = 2
        self.assertEqual(self.run_()["outcome"], "published")
        self.now += dt.timedelta(hours=1)
        self.assertEqual(self.run_()["outcome"], "skipped_gap")
        self.now += dt.timedelta(hours=5)                  # 12:00, second of the day
        self.llm_texts = [GOOD.replace("Holds Its Breath", "Waits For Dawn")]
        self.assertEqual(self.run_()["outcome"], "published")
        self.now += dt.timedelta(hours=5)                  # 17:00, cap reached
        self.assertEqual(self.run_()["outcome"], "skipped_cap")
        self.now += dt.timedelta(days=1)                   # next day resets the cap
        self.llm_texts = [GOOD.replace("Holds Its Breath", "Rises Again")]
        self.assertEqual(self.run_()["outcome"], "published")

    def test_kill_switch(self):
        open(self.cfg["pause_file"], "w").write("x")
        self.assertEqual(self.run_()["outcome"], "paused")
        self.assertEqual(self.calls, [])

    def test_flat_market_publishes_nothing(self):
        self.cond_change = 0.1
        self.assertEqual(self.run_()["outcome"], "skipped_flat")
        self.assertEqual(self.calls, [])

    def test_data_failure_is_reported_not_published(self):
        e = C.Engine(self.cfg, now=lambda: self.now, http=lambda *a, **k: (_ for _ in ()).throw(OSError("down")), llm=self.llm, notifier=self.notes.append)
        import io, contextlib
        with contextlib.redirect_stdout(io.StringIO()), contextlib.redirect_stderr(io.StringIO()):
            r = e.run()
        self.assertEqual(r["outcome"], "error_data")
        self.assertTrue(self.notes)

    def test_dry_run_changes_nothing(self):
        r = self.run_(dry=True)
        self.assertEqual(r["outcome"], "dry_run_ok")
        self.assertFalse(os.path.exists(f"{self.tmp}/feed/episodes.json"))
        self.assertFalse(os.path.exists(f"{self.tmp}/state.json"))

    def test_episode_numbers_increase_and_feed_is_valid_json(self):
        self.run_()
        self.now += dt.timedelta(hours=5)
        self.llm_texts = [GOOD.replace("Holds Its Breath", "Waits For Dawn")]
        self.run_()
        self.assertEqual([e["number"] for e in self.feed()], [1, 2])


class TwoADay(Cycle):
    def test_default_cap_is_two_and_the_third_is_refused(self):
        self.assertEqual(self.cfg["daily_cap"], 2)
        names = ["Waits For Dawn", "Rises Again"]
        outs = []
        for i, nm in enumerate(names):
            self.llm_texts = [GOOD.replace("Holds Its Breath", nm)]
            outs.append(self.run_()["outcome"])
            self.now += dt.timedelta(hours=5)
        self.assertEqual(outs, ["published"] * 2)
        self.now = dt.datetime(2026, 10, 16, 23, 30, tzinfo=dt.timezone.utc)
        self.assertEqual(self.run_()["outcome"], "skipped_cap")


class ModelPools(unittest.TestCase):
    def eng(self, calls, **cfg):
        e = C.Engine(dict(C.DEFAULT_CFG, log_path="/dev/null", **cfg), now=lambda: dt.datetime(2026, 10, 16, tzinfo=dt.timezone.utc))
        def fake(system, user, mt, models, timeout, temp):
            calls.append(list(models))
            return calls_answers.pop(0)
        e._openrouter = fake
        return e

    def test_second_pool_is_tried_when_first_fails(self):
        global calls_answers
        calls, calls_answers = [], [(None, "http 429"), ("a story", "nvidia/x")]
        text, model = self.eng(calls)._real_llm("s", "u", 100)
        self.assertEqual((text, model), ("a story", "nvidia/x"))
        self.assertEqual(calls[0], C.DEFAULT_CFG["models"])
        self.assertEqual(calls[1], C.DEFAULT_CFG["fallback_pools"][0])

    def test_paid_pool_is_last_and_only_when_configured(self):
        global calls_answers
        calls, calls_answers = [], [(None, "x"), (None, "x")]
        text, _ = self.eng(calls)._real_llm("s", "u", 100)
        self.assertIsNone(text); self.assertEqual(len(calls), 2)          # no paid call by default
        calls, calls_answers = [], [(None, "x"), (None, "x"), ("paid answer", "x-ai/grok-4.3")]
        text, model = self.eng(calls, paid_models=["x-ai/grok-4.3"])._real_llm("s", "u", 100)
        self.assertEqual(model, "x-ai/grok-4.3"); self.assertEqual(calls[-1], ["x-ai/grok-4.3"])

    def test_named_models_are_not_expanded(self):
        global calls_answers
        calls, calls_answers = [], [("ok", "m")]
        self.eng(calls)._real_llm("s", "u", 10, models=["only/this:free"])
        self.assertEqual(calls, [["only/this:free"]])


class StaffControls(Cycle):
    def setUp(self):
        super().setUp()
        t = self.tmp
        self.cfg.update(direction_path=f"{t}/direction.json", inbox_dir=f"{t}/inbox", outbox_dir=f"{t}/outbox")
        os.makedirs(self.cfg["inbox_dir"])

    def req(self, name, **kw):
        json.dump(kw, open(f"{self.cfg['inbox_dir']}/req-{name}.json", "w"))

    def out(self, name):
        return json.load(open(f"{self.cfg['outbox_dir']}/req-{name}.json"))

    def test_direction_is_used_once_and_then_cleared(self):
        self.req("a", verb="direction_set", by="maya", text="Let the Bazaar celebrate a quiet kindness between two travellers.")
        self.engine().process_inbox()
        self.assertTrue(self.out("a")["ok"])
        self.run_()
        self.assertIn("quiet kindness", self.calls[0])
        self.assertFalse(os.path.exists(self.cfg["direction_path"]))
        self.now += dt.timedelta(hours=5)
        self.run_()
        self.assertNotIn("quiet kindness", self.calls[-1])

    def test_direction_does_not_bypass_the_rule_checker(self):
        self.req("b", verb="direction_set", by="echo", text="Mention the price of Bitcoin in dollars and tell people to buy.")
        self.engine().process_inbox()
        self.llm_texts = [GOOD.replace("gold units", "dollars"), GOOD.replace("gold units", "dollars"), GOOD.replace("gold units", "dollars")]
        self.run_()
        self.assertEqual(self.feed() if os.path.exists(f"{self.tmp}/feed/episodes.json") else [], [])
        self.assertTrue(os.path.exists(self.cfg["direction_path"]))     # not consumed by a failed run

    def test_permissions_are_per_staff(self):
        self.req("c", verb="unpublish", by="raven", number=1)
        self.req("d", verb="pause_on", by="echo")
        self.req("e", verb="pause_on", by="stranger")
        self.req("f", verb="pause_on", by="prometheus")
        self.engine().process_inbox()
        self.assertFalse(self.out("c")["ok"]); self.assertFalse(self.out("d")["ok"]); self.assertFalse(self.out("e")["ok"])
        self.assertTrue(self.out("f")["ok"]); self.assertTrue(os.path.exists(self.cfg["pause_file"]))
        self.assertEqual(os.listdir(self.cfg["inbox_dir"]), [])

    def test_unpublish_and_resume_by_maya(self):
        self.run_()
        self.assertEqual(len(self.feed()), 1)
        self.req("g", verb="unpublish", by="maya", number=1)
        self.engine().process_inbox()
        self.assertEqual(self.feed(), [])
        self.req("h", verb="pause_on", by="maya"); self.engine().process_inbox()
        self.req("i", verb="pause_off", by="maya"); self.engine().process_inbox()
        self.assertFalse(os.path.exists(self.cfg["pause_file"]))

    def test_junk_requests_are_answered_not_fatal(self):
        open(f"{self.cfg['inbox_dir']}/req-j.json", "w").write("{not json")
        open(f"{self.cfg['inbox_dir']}/other.txt", "w").write("x")
        self.req("k", verb="direction_set", by="maya", text="hi")
        self.engine().process_inbox()
        self.assertFalse(self.out("j")["ok"]); self.assertFalse(self.out("k")["ok"])
        self.assertTrue(os.path.exists(f"{self.cfg['inbox_dir']}/other.txt"))


if __name__ == "__main__":
    unittest.main(verbosity=1)
