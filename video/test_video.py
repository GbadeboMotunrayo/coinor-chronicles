import base64, datetime as dt, io, json, os, tempfile, unittest, urllib.error
import coinor_video as V

PNG = base64.b64encode(b"\x89PNG\r\n\x1a\n" + b"x" * 6000).decode()
BODY = "Sit, young Pepe. The roads grew heavy tonight.\n\nAragorn stands firm in Heaven 9 of Gate 1. He remains close to the glowing gate. " + "The fellowship walks on and on. " * 40


class Resp(io.BytesIO):
    def __enter__(self): return self
    def __exit__(self, *a): pass


class Pick(unittest.TestCase):
    def test_lagos_date_decides_the_day(self):
        eps = {"episodes": [{"number": 1, "status": "approved", "published_at": "2026-10-15T23:30:00+00:00", "body": "x", "title": "t"},
                            {"number": 2, "status": "draft", "published_at": "2026-10-16T05:00:00+00:00", "body": "x", "title": "t"}]}
        self.assertEqual(V.pick_episode(eps, "2026-10-16")["number"], 1)       # 23:30 UTC is already the 16th in Lagos
        self.assertIsNone(V.pick_episode(eps, "2026-10-15"))
        self.assertIsNone(V.pick_episode({"episodes": []}, "2026-10-16"))

    def test_latest_chapter_of_the_day_wins(self):
        mk = lambda n, h: {"number": n, "status": "approved", "published_at": f"2026-10-16T{h}:00:00+00:00", "body": "x", "title": "t"}
        self.assertEqual(V.pick_episode({"episodes": [mk(3, "05"), mk(4, "17")]}, "2026-10-16")["number"], 4)


class Narration(unittest.TestCase):
    def test_short_enough_for_a_short_and_ends_on_a_sentence(self):
        out = V.narration(BODY, 60)
        self.assertLessEqual(sum(len(s.split()) for s in out), 60 + 12)
        self.assertTrue(out[-1].endswith((".", "!", "?")))
        self.assertNotIn("*", " ".join(out))

    def test_scenes_fall_back_when_notes_missing(self):
        self.assertEqual(len(V.scenes({"scene_notes": ""})), 5)
        self.assertEqual(V.scenes({"scene_notes": "- Scene 1: a\n- Scene 2: b\n- Scene 3: c\n- Scene 4: d"})[3], "d")

    def test_pollinations_prompt_never_uses_film_names(self):
        p = V.image_prompt("Boromir's clenched fist; Gandalf lifts a staff")
        self.assertNotIn("Boromir", p); self.assertNotIn("Gandalf", p); self.assertIn("wizard", p)


class Cast(unittest.TestCase):
    def test_every_character_has_a_look(self):
        style, cast = V.load_cast()
        for n in V.LOOKS:
            self.assertIn(n, cast, n)
        self.assertGreaterEqual(len(cast), 21); self.assertIn("no text", style)

    def test_characters_found_in_scene_order_max_two(self):
        _, cast = V.load_cast()
        self.assertEqual(V.cast_in("Gandalf and Aragorn and Pepe at the gate", cast), ["Gandalf", "Aragorn"])
        self.assertEqual(V.cast_in("an empty hall", cast), [])

    def test_gemini_prompt_demands_consistency_only_when_there_are_characters(self):
        style, cast = V.load_cast()
        self.assertIn("exactly as in the reference", V.scene_prompt_gemini("Pepe on a bridge", ["Pepe"], cast, style))
        self.assertNotIn("exactly as in the reference", V.scene_prompt_gemini("a storm", [], cast, style))
        self.assertNotIn("Gate 3", V.scene_prompt_gemini("walks to Gate 3", [], cast, style))


class GeminiImage(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.mkdtemp(); V.CFG["gemini_image_models"] = ["m1"]

    def test_reads_the_interactions_shape_and_sends_references(self):
        seen = {}
        def op(req, timeout=0):
            seen["body"] = json.loads(req.data); seen["key"] = req.get_header("X-goog-api-key")
            return Resp(json.dumps({"steps": [{"type": "model_output", "content": [{"type": "image", "mime_type": "image/png", "data": PNG}]}]}).encode())
        ref = os.path.join(self.tmp, "r.png"); open(ref, "wb").write(b"\x89PNG" + b"y" * 100)
        d = os.path.join(self.tmp, "o.png")
        self.assertTrue(V.gemini_image("draw", [ref], d, "KEY", urlopen=op))
        self.assertEqual(seen["key"], "KEY"); self.assertEqual([p["type"] for p in seen["body"]["input"]], ["text", "image"])
        self.assertGreater(os.path.getsize(d), 5000)

    def test_falls_back_to_generatecontent_and_reads_inlinedata(self):
        calls = []
        def op(req, timeout=0):
            calls.append(req.full_url)
            if "interactions" in req.full_url:
                raise urllib.error.HTTPError(req.full_url, 404, "nf", {}, None)
            return Resp(json.dumps({"candidates": [{"content": {"parts": [{"inlineData": {"mimeType": "image/png", "data": PNG}}]}}]}).encode())
        d = os.path.join(self.tmp, "o.png")
        self.assertTrue(V.gemini_image("draw", [], d, "K", urlopen=op)); self.assertEqual(len(calls), 2)

    def test_quota_stops_at_once_and_returns_false(self):
        calls = []
        def op(req, timeout=0):
            calls.append(1); raise urllib.error.HTTPError(req.full_url, 429, "quota", {}, None)
        V.CFG["gemini_image_models"] = ["m1", "m2"]
        self.assertFalse(V.gemini_image("draw", [], os.path.join(self.tmp, "o.png"), "K", urlopen=op)); self.assertEqual(len(calls), 1)

    def test_text_only_reply_is_not_an_image(self):
        op = lambda req, timeout=0: Resp(json.dumps({"candidates": [{"content": {"parts": [{"text": "sorry"}]}}]}).encode())
        self.assertFalse(V.gemini_image("draw", [], os.path.join(self.tmp, "o.png"), "K", urlopen=op))


class Upload(unittest.TestCase):
    def test_no_keys_means_no_upload_and_no_error(self):
        V.CFG["env_file"] = "/nonexistent"
        self.assertEqual(V.upload_youtube("x.mp4", {}), {"uploaded": False, "why": "no YouTube keys yet"})

    def test_upload_starts_private_and_declares_synthetic_media(self):
        d = tempfile.mkdtemp(); env = os.path.join(d, "env"); open(env, "w").write("YT_CLIENT_ID=a\nYT_CLIENT_SECRET=b\nYT_REFRESH_TOKEN=c\n")
        V.CFG["env_file"] = env; mp4 = os.path.join(d, "v.mp4"); open(mp4, "wb").write(b"0" * 100)
        sent = []
        class H(Resp):
            headers = {"Location": "https://up.example/1"}
        def op(req, timeout=0):
            sent.append(req)
            if "oauth2" in req.full_url: return Resp(b'{"access_token":"T"}')
            if req.get_method() == "PUT": return Resp(b'{"id":"abc123"}')
            return H(b"{}")
        r = V.upload_youtube(mp4, V.metadata({"number": 1, "title": "T", "body": "Sit. " * 60}), urlopen=op)
        self.assertEqual((r["uploaded"], r["id"], r["privacy"]), (True, "abc123", "private"))
        meta = json.loads(sent[1].data); self.assertEqual(meta["status"]["privacyStatus"], "private"); self.assertTrue(meta["status"]["containsSyntheticMedia"])


class Captions(unittest.TestCase):
    def test_ass_file_has_every_word_once_in_order(self):
        p = os.path.join(tempfile.mkdtemp(), "c.ass")
        V.make_ass([("One two three four five six seven.", 0.4, 3.0), ("Eight nine.", 3.4, 4.5)], p)
        text = " ".join(l.split(",,")[-1] for l in open(p).read().splitlines() if l.startswith("Dialogue"))
        self.assertEqual(text, "One two three four five six seven. Eight nine.")


if __name__ == "__main__":
    unittest.main()
