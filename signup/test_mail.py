import datetime as dt, json, os, tempfile, threading, unittest, urllib.request, urllib.error, smtplib
from http.server import ThreadingHTTPServer
import coinor_mail as M

T0 = dt.datetime(2026, 10, 18, 8, 0, tzinfo=dt.timezone.utc)
TOML = '[accounts.echo]\nemail = "echo@mgholdings.tech"\n[accounts.echo.message.send.backend]\nlogin = "echo@mgholdings.tech"\nauth.raw = "x"\n'


def setup_cfg(t):
    p = os.path.join(t, "echo.toml"); open(p, "w").write(TOML)
    M.CFG["mail_toml"] = p
    M.CFG["state"] = os.path.join(t, "digest_state.json")


class Valid(unittest.TestCase):
    def test_emails(self):
        for ok in ["a@b.co", "first.last+tag@sub.example.org", "x_y@e-x.com"]:
            self.assertTrue(M.valid_email(ok), ok)
        for bad in ["", "a", "a@b", "a@@b.co", "a b@c.co", "a@b..co", "<x>@b.co", "a@b.co\nBcc: z@z.co", "x" * 300 + "@b.co", "a@-b.co"]:
            self.assertFalse(M.valid_email(bad), bad)


class Signup(unittest.TestCase):
    def setUp(self):
        self.store = M.MemStore(now=lambda: self.t)
        self.t = 1_000_000.0
        self.lim = M.Limiter(now=lambda: self.t)
        self.touched = 0

    def sub(self, **b):
        body = dict(email="reader@example.com", consent="1"); body.update(b)
        return M.handle_subscribe(self.store, self.lim, "1.2.3.4", body, lambda: setattr(self, "touched", self.touched + 1))

    def test_new_address_becomes_pending_and_triggers_allowlist(self):
        code, obj = self.sub()
        self.assertEqual((code, obj["ok"]), (200, True))
        self.assertEqual(self.store.get("reader@example.com")["status"], "pending")
        self.assertEqual(self.touched, 1)

    def test_consent_is_required(self):
        code, obj = self.sub(consent="")
        self.assertEqual(code, 400); self.assertIsNone(self.store.get("reader@example.com"))

    def test_bad_email_rejected(self):
        self.assertEqual(self.sub(email="nope")[0], 400)

    def test_honeypot_says_ok_but_stores_nothing(self):
        code, obj = self.sub(website="http://spam")
        self.assertEqual(code, 200); self.assertIsNone(self.store.get("reader@example.com"))

    def test_rate_limit_per_ip(self):
        codes = [self.sub(email=f"r{i}@example.com")[0] for i in range(7)]
        self.assertEqual(codes[:5], [200] * 5); self.assertEqual(codes[5:], [429, 429])
        self.t += 3700
        self.assertEqual(self.sub(email="late@example.com")[0], 200)

    def test_same_answer_for_subscribed_address_no_leak_and_no_change(self):
        self.sub(); tok = self.store.rows["reader@example.com"]["token"]
        self.store.confirm(tok)
        code, obj = self.sub()
        self.assertEqual((code, obj["ok"]), (200, True))
        self.assertEqual(self.store.get("reader@example.com")["status"], "subscribed")

    def test_confirm_then_unsubscribe(self):
        self.sub(); tok = self.store.rows["reader@example.com"]["token"]
        self.assertEqual(self.store.confirm(tok), "reader@example.com")
        self.assertIsNone(self.store.confirm(tok))                       # a link works once
        self.assertEqual(self.store.unsubscribe(tok), "reader@example.com")
        self.assertEqual(self.store.get("reader@example.com")["status"], "unsubscribed")
        self.assertEqual(self.store.subscribers(), [])

    def test_unconfirmed_request_expires(self):
        self.sub(); tok = self.store.rows["reader@example.com"]["token"]
        self.t += 8 * 86400
        self.assertIsNone(self.store.confirm(tok))
        self.store.cleanup(); self.assertIsNone(self.store.get("reader@example.com"))

    def test_resubscribe_after_unsubscribe_needs_new_confirmation(self):
        self.sub(); tok = self.store.rows["reader@example.com"]["token"]; self.store.confirm(tok); self.store.unsubscribe(tok)
        self.sub()
        r = self.store.rows["reader@example.com"]
        self.assertEqual(r["status"], "pending"); self.assertNotEqual(r["token"], tok)


class Confirmations(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.mkdtemp(); setup_cfg(self.tmp)
        self.store = M.MemStore(); self.store.upsert_pending("a@example.com", "T" * 24, "c")

    def test_message_has_link_and_unsubscribe(self):
        m = M.confirmation_msg("a@example.com", "T" * 24)
        txt = m.get_body(("plain",)).get_content()
        self.assertIn("/api/confirm?t=" + "T" * 24, txt)
        self.assertIn("api/unsubscribe?t=", m["List-Unsubscribe"])
        raw = m.as_bytes().decode("ascii", "replace")
        line = [l for l in raw.splitlines() if l.startswith("List-Unsubscribe:")][0]
        self.assertTrue(line.startswith("List-Unsubscribe: <https://") and "=?utf-8" not in line, line)
        self.assertEqual(m["To"], "a@example.com"); self.assertIn("echo@mgholdings.tech", m["From"])

    def test_sent_once_and_marked(self):
        got = []
        self.assertEqual(M.send_confirmations(self.store, sender=got.append, log=lambda m: None), 1)
        self.assertEqual(M.send_confirmations(self.store, sender=got.append, log=lambda m: None), 0)
        self.assertEqual(len(got), 1)

    def test_not_yet_allowlisted_is_retried_not_lost(self):
        calls = []
        def refuse(m):
            calls.append(1); raise smtplib.SMTPRecipientsRefused({"a@example.com": (554, b"5.7.1 Access denied")})
        self.assertEqual(M.send_confirmations(self.store, sender=refuse, log=lambda m: None), 0)
        got = []
        self.assertEqual(M.send_confirmations(self.store, sender=got.append, log=lambda m: None), 1)
        self.assertEqual(len(calls), 1)

    def test_log_never_contains_the_full_address(self):
        logs = []
        def refuse(m): raise smtplib.SMTPRecipientsRefused({"x": (554, b"no")})
        M.send_confirmations(self.store, sender=refuse, log=logs.append)
        self.assertTrue(logs and "a@example.com" not in logs[0])


class Digest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.mkdtemp(); setup_cfg(self.tmp)
        self.store = M.MemStore()
        for e in ("a@example.com", "b@example.com"):
            self.store.upsert_pending(e, e[0] * 24, "c"); self.store.confirm(e[0] * 24)
        self.store.upsert_pending("p@example.com", "p" * 24, "c")      # pending: never gets a digest
        body = "Sit, young Pepe.\n\nQuestion?\n\n" + ("A long second paragraph about the road and the fellowship. " * 4)
        self.feed = {"episodes": [
            {"number": 1, "title": "Old", "status": "approved", "published_at": (T0 - dt.timedelta(days=9)).isoformat(), "body": body},
            {"number": 2, "title": "New One", "status": "approved", "published_at": (T0 - dt.timedelta(days=2)).isoformat(), "body": body},
            {"number": 3, "title": "Hidden", "status": "draft", "published_at": (T0 - dt.timedelta(days=1)).isoformat(), "body": body}]}

    def run_(self, **kw):
        got = []
        r = M.run_digest(self.store, T0, sender=got.append, feed=self.feed, sleep=lambda s: None, **kw)
        return r, got

    def test_only_recent_approved_episodes(self):
        self.assertEqual([e["number"] for e in M.digest_episodes(self.feed, T0)], [2])

    def test_sends_to_confirmed_only_each_with_own_unsubscribe(self):
        r, got = self.run_()
        self.assertEqual((r["outcome"], r["sent"]), ("sent", 2))
        self.assertEqual(sorted(m["To"] for m in got), ["a@example.com", "b@example.com"])
        self.assertTrue(all(("t=" + m["To"][0] * 24) in m["List-Unsubscribe"] for m in got))
        self.assertIn("/episode/2", got[0].get_body(("plain",)).get_content())
        self.assertNotIn("Hidden", got[0].get_body(("plain",)).get_content())

    def test_not_twice_in_five_days(self):
        self.assertEqual(self.run_()[0]["outcome"], "sent")
        self.assertEqual(self.run_()[0]["outcome"], "too_soon")
        self.assertEqual(self.run_(force=True)[0]["outcome"], "sent")

    def test_dry_run_sends_nothing_and_records_nothing(self):
        r, got = self.run_(dry=True)
        self.assertEqual((r["outcome"], r["recipients"], got), ("dry_run", 2, []))
        self.assertEqual(self.run_()[0]["outcome"], "sent")

    def test_nothing_new_means_no_email(self):
        self.feed["episodes"] = self.feed["episodes"][:1]
        r, got = self.run_()
        self.assertEqual((r["outcome"], got), ("nothing_new", []))

    def test_unsubscribed_never_receives(self):
        self.store.unsubscribe("a" * 24)
        r, got = self.run_()
        self.assertEqual([m["To"] for m in got], ["b@example.com"])

    def test_one_failure_does_not_stop_the_rest(self):
        n = []
        def flaky(m):
            n.append(m["To"])
            if m["To"] == "a@example.com": raise smtplib.SMTPServerDisconnected("x")
        r = M.run_digest(self.store, T0, sender=flaky, feed=self.feed, sleep=lambda s: None)
        self.assertEqual((r["sent"], r["failed"]), (1, 1))

    def test_single_recipient_test_send_does_not_start_the_clock(self):
        self.assertEqual(self.run_(only="a@example.com")[0]["sent"], 1)
        self.assertEqual(self.run_()[0]["outcome"], "sent")


class Http(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.store = M.MemStore()
        cls.srv = ThreadingHTTPServer(("127.0.0.1", 0), M.make_handler(cls.store, M.Limiter(), lambda: None))
        cls.port = cls.srv.server_address[1]
        threading.Thread(target=cls.srv.serve_forever, daemon=True).start()

    @classmethod
    def tearDownClass(cls):
        cls.srv.shutdown()

    def post(self, path, obj):
        r = urllib.request.Request(f"http://127.0.0.1:{self.port}{path}", json.dumps(obj).encode(), {"Content-Type": "application/json"})
        try:
            with urllib.request.urlopen(r) as x:
                return x.status, json.loads(x.read()), dict(x.headers)
        except urllib.error.HTTPError as e:
            return e.code, json.loads(e.read()), dict(e.headers)

    def test_subscribe_confirm_unsubscribe_over_http(self):
        code, obj, hdr = self.post("/api/subscribe", {"email": "web@example.com", "consent": True})
        self.assertEqual((code, obj["ok"]), (200, True))
        self.assertEqual(hdr["Access-Control-Allow-Origin"], M.CFG["site"])
        tok = self.store.rows["web@example.com"]["token"]
        class NoRedirect(urllib.request.HTTPRedirectHandler):
            def redirect_request(self, *a, **k): return None
        op = urllib.request.build_opener(NoRedirect)
        for path, key in (("/api/confirm", "confirmed"), ("/api/confirm", "invalid"), ("/api/unsubscribe", "unsubscribed")):
            try:
                op.open(f"http://127.0.0.1:{self.port}{path}?t={tok}")
            except urllib.error.HTTPError as e:
                self.assertEqual(e.code, 302); self.assertTrue(e.headers["Location"].endswith("/notice/" + key), (path, key, e.headers["Location"]))

    def test_garbage_tokens_and_bodies(self):
        self.assertEqual(self.post("/api/subscribe", {"email": "x", "consent": True})[0], 400)
        r = urllib.request.Request(f"http://127.0.0.1:{self.port}/api/subscribe", b"{" + b"x" * 5000, {"Content-Type": "application/json"})
        with self.assertRaises(urllib.error.HTTPError) as c:
            urllib.request.urlopen(r)
        self.assertEqual(c.exception.code, 413)
        self.assertEqual(self.post("/api/unsubscribe?t=short", {})[0], 200)   # one-click POST never errors


if __name__ == "__main__":
    unittest.main()
