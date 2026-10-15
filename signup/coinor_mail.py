#!/usr/bin/env python3
"""CoinorChronicles mailing list: signup API, double opt-in, weekly digest.

  coinor_mail.py serve                       # the signup API on 127.0.0.1:8099 (behind nginx)
  coinor_mail.py digest [--dry-run] [--force] [--to ADDR]
  coinor_mail.py stats

Rules this follows
- Double opt-in: an address is only mailed the newsletter after it clicks the confirmation link.
- The confirmation goes through the same Postfix submission port as all staff mail, so the Chairman's
  recipient allowlist still applies. Pending addresses are admitted to that allowlist for 7 days.
- Every email carries a one-click unsubscribe. Unsubscribing takes effect at once.
- No tracking pixels, no click tracking, no IP addresses stored. Consent text and time are stored.
- The digest is built from the public episode feed; no AI model is involved.
"""
import argparse, datetime as dt, hashlib, json, os, re, secrets, smtplib, subprocess, sys, threading, time, tomllib
from email import policy
from email.message import EmailMessage
from email.utils import formatdate, make_msgid
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import parse_qs, urlparse

CFG = {
    "host": "127.0.0.1", "port": 8099,
    "site": "https://coinorchonicles.space",
    "api_base": "https://coinor.2-24-128-252.sslip.io/api",
    "feed": "/var/www/coinor/feed/episodes.json",
    "mail_toml": "/home/hermes/.mail-vault/coinor.toml", "account": "coinor",
    "smtp_host": "127.0.0.1", "smtp_port": 2525,
    "from_name": "CoinorChronicles",
    "trigger": "/var/lib/coinor/allowlist.trigger",
    "state": "/var/lib/coinor/digest_state.json",
    "source": "coinor",
    "ip_limit": 5, "ip_window": 3600, "global_limit": 300,
    "consent_text": "newsletter-v1: I agree to receive the weekly CoinorChronicles newsletter by email, and I know I can unsubscribe at any time.",
    "chairman": "telegram:932372452",
    "digest_min_gap_days": 5,
}
EMAIL_RE = re.compile(r"^[A-Za-z0-9.!#$%&'*+/=?^_`{|}~-]{1,64}@[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)+$")
TOKEN_RE = re.compile(r"^[A-Za-z0-9_-]{20,64}$")


def valid_email(e):
    return bool(e) and len(e) <= 254 and bool(EMAIL_RE.match(e))


# ----------------------------------------------------------------------------- storage
class PgStore:
    """Paperclip's `leads` table, reached through the paperclip_db container. Values travel as psql
    variables (:'name'), never spliced into SQL text."""

    def _run(self, sql, **v):
        cmd = ["docker", "exec", "-i", "paperclip_db", "psql", "-U", "paperclip", "-d", "paperclip", "-X", "-q",
               "-v", "ON_ERROR_STOP=1", "-t", "-A", "-F", "\x1f"]
        for k, val in v.items():
            cmd += ["-v", f"{k}={val}"]
        r = subprocess.run(cmd, input=sql, capture_output=True, text=True, timeout=25)
        if r.returncode:
            raise RuntimeError(r.stderr.strip()[:300])
        return [ln.split("\x1f") for ln in r.stdout.splitlines() if ln]

    def get(self, email):
        r = self._run("SELECT status, source, COALESCE(EXTRACT(EPOCH FROM now()-confirm_sent_at),999999)::bigint "
                      "FROM leads WHERE email=lower(:'email');", email=email)
        return {"status": r[0][0], "source": r[0][1], "since_sent": int(r[0][2])} if r else None

    def upsert_pending(self, email, token, consent):
        self._run("INSERT INTO leads(email,source,status,token,consent_at,consent_text) "
                  "VALUES (lower(:'email'),:'src','pending',:'tok',now(),:'ct') "
                  "ON CONFLICT (email) DO UPDATE SET status='pending', token=:'tok', consent_at=now(), consent_text=:'ct', "
                  "confirm_sent_at=NULL, unsubscribed_at=NULL, source=:'src' WHERE leads.status IN ('pending','unsubscribed');",
                  email=email, tok=token, ct=consent, src=CFG["source"])

    def confirm(self, token):
        r = self._run("UPDATE leads SET status='subscribed', confirmed_at=now() WHERE token=:'t' AND status='pending' "
                      "AND consent_at > now() - interval '7 days' RETURNING email;", t=token)
        return r[0][0] if r else None

    def unsubscribe(self, token):
        r = self._run("UPDATE leads SET status='unsubscribed', unsubscribed_at=now() WHERE token=:'t' "
                      "AND status IN ('subscribed','pending') RETURNING email;", t=token)
        return r[0][0] if r else None

    def to_confirm(self):
        r = self._run("SELECT email, token FROM leads WHERE status='pending' AND source=:'src' AND confirm_sent_at IS NULL "
                      "AND consent_at > now() - interval '30 minutes' ORDER BY id LIMIT 20;", src=CFG["source"])
        return [(a, b) for a, b in r]

    def mark_sent(self, email):
        self._run("UPDATE leads SET confirm_sent_at=now() WHERE email=lower(:'email');", email=email)

    def cleanup(self):
        self._run("DELETE FROM leads WHERE status='pending' AND source=:'src' AND consent_at < now() - interval '7 days';", src=CFG["source"])

    def subscribers(self):
        r = self._run("SELECT email, token FROM leads WHERE status='subscribed' AND source=:'src' AND token IS NOT NULL ORDER BY id;", src=CFG["source"])
        return [(a, b) for a, b in r]

    def counts(self):
        return {a: int(b) for a, b in self._run("SELECT status, count(*) FROM leads WHERE source=:'src' GROUP BY 1;", src=CFG["source"])}


class MemStore:
    """Same contract, in memory. Used by the tests."""

    def __init__(self, now=None):
        self.rows, self.now = {}, now or (lambda: time.time())

    def get(self, email):
        r = self.rows.get(email.lower())
        if not r:
            return None
        return {"status": r["status"], "source": r["source"], "since_sent": 999999 if r["sent"] is None else int(self.now() - r["sent"])}

    def upsert_pending(self, email, token, consent):
        e = email.lower(); r = self.rows.get(e)
        if r and r["status"] == "subscribed":
            return
        self.rows[e] = {"status": "pending", "source": CFG["source"], "token": token, "consent_at": self.now(), "consent": consent, "sent": None}

    def _by_token(self, t):
        for e, r in self.rows.items():
            if r.get("token") == t:
                return e, r
        return None, None

    def confirm(self, token):
        e, r = self._by_token(token)
        if r and r["status"] == "pending" and self.now() - r["consent_at"] < 7 * 86400:
            r["status"] = "subscribed"
            return e
        return None

    def unsubscribe(self, token):
        e, r = self._by_token(token)
        if r and r["status"] in ("subscribed", "pending"):
            r["status"] = "unsubscribed"
            return e
        return None

    def to_confirm(self):
        return [(e, r["token"]) for e, r in self.rows.items()
                if r["status"] == "pending" and r["sent"] is None and self.now() - r["consent_at"] < 1800]

    def mark_sent(self, email):
        self.rows[email.lower()]["sent"] = self.now()

    def cleanup(self):
        for e in [e for e, r in self.rows.items() if r["status"] == "pending" and self.now() - r["consent_at"] > 7 * 86400]:
            del self.rows[e]

    def subscribers(self):
        return [(e, r["token"]) for e, r in self.rows.items() if r["status"] == "subscribed"]

    def counts(self):
        c = {}
        for r in self.rows.values():
            c[r["status"]] = c.get(r["status"], 0) + 1
        return c


# ----------------------------------------------------------------------------- mail
def smtp_send(msg):
    d = tomllib.load(open(CFG["mail_toml"], "rb"))["accounts"][CFG["account"]]["message"]["send"]["backend"]
    with smtplib.SMTP(CFG["smtp_host"], CFG["smtp_port"], timeout=30) as s:
        s.login(d["login"], d["auth"]["raw"])
        s.send_message(msg)


def sender_addr():
    return tomllib.load(open(CFG["mail_toml"], "rb"))["accounts"][CFG["account"]]["email"]


def base_msg(to, subject, unsub_url=None):
    m = EmailMessage(policy=policy.SMTP.clone(max_line_length=2000))   # long List-Unsubscribe must stay plain ASCII
    m["From"] = f'{CFG["from_name"]} <{sender_addr()}>'
    m["To"] = to
    m["Subject"] = subject
    m["Date"] = formatdate(localtime=False)
    m["Message-ID"] = make_msgid(domain="mgholdings.tech")
    if unsub_url:
        m["List-Unsubscribe"] = f"<{unsub_url}>"
        m["List-Unsubscribe-Post"] = "List-Unsubscribe=One-Click"
    return m


FOOT = "MG Holdings. You are getting this because you asked for it at coinorchonicles.space."


def confirmation_msg(email, token):
    url = f'{CFG["api_base"]}/confirm?t={token}'
    unsub = f'{CFG["api_base"]}/unsubscribe?t={token}'
    m = base_msg(email, "Confirm your place in the Book of Meme", unsub)
    m.set_content(
        "Someone (we hope you) asked to get the weekly Scroll from the Realm of Coinor.\n\n"
        f"To confirm, open this link:\n{url}\n\n"
        "If you did not ask for this, ignore this email. Nothing will be sent to you, and the request disappears in 7 days.\n\n"
        f"{FOOT}\nWe never share your address.\n")
    m.add_alternative(
        '<div style="font-family:Georgia,serif;max-width:540px;color:#2a1b0d;line-height:1.6">'
        "<p>Someone (we hope you) asked to get the weekly <b>Scroll from the Realm of Coinor</b>.</p>"
        f'<p><a href="{url}" style="background:#8a2b17;color:#fff;padding:10px 18px;border-radius:4px;text-decoration:none">Confirm my place</a></p>'
        f'<p style="font-size:13px;color:#5b4429">Or open: {url}</p>'
        '<p style="font-size:13px;color:#5b4429">If you did not ask for this, ignore this email. Nothing will be sent to you, and the request disappears in 7 days.</p>'
        f'<p style="font-size:12px;color:#5b4429">{FOOT} We never share your address.</p></div>', subtype="html")
    return m


def send_confirmations(store, sender=smtp_send, log=print):
    sent = 0
    for email, token in store.to_confirm():
        try:
            sender(confirmation_msg(email, token))
            store.mark_sent(email)
            sent += 1
        except (smtplib.SMTPRecipientsRefused, smtplib.SMTPDataError, smtplib.SMTPSenderRefused) as e:
            log(f"confirm to {email[:3]}***: not yet accepted ({type(e).__name__}); will retry")   # allowlist sync still pending
        except Exception as e:
            log(f"confirm to {email[:3]}***: {type(e).__name__}")
    return sent


# ----------------------------------------------------------------------------- the API
class Limiter:
    def __init__(self, now=time.time):
        self.now, self.ip, self.glob = now, {}, []

    def allow(self, ip):
        t = self.now()
        self.glob = [x for x in self.glob if t - x < 3600]
        hits = [x for x in self.ip.get(ip, []) if t - x < CFG["ip_window"]]
        if len(hits) >= CFG["ip_limit"] or len(self.glob) >= CFG["global_limit"]:
            self.ip[ip] = hits
            return False
        hits.append(t); self.glob.append(t); self.ip[ip] = hits
        return True


def handle_subscribe(store, limiter, ip, body, touch=lambda: None):
    """Returns (status, payload). Always the same answer for a valid address, whatever its state."""
    ok_msg = {"ok": True, "msg": "Almost there. Check your inbox and open the link to confirm."}
    if (body.get("website") or "").strip():                      # honeypot: pretend it worked
        return 200, ok_msg
    if not limiter.allow(ip):
        return 429, {"ok": False, "msg": "Too many tries. Please wait a while and try again."}
    email = (body.get("email") or "").strip()
    if not valid_email(email):
        return 400, {"ok": False, "msg": "That email address does not look right."}
    if str(body.get("consent")).lower() not in ("1", "true", "on", "yes"):
        return 400, {"ok": False, "msg": "Please tick the box to agree to the newsletter."}
    cur = store.get(email)
    if cur and cur["status"] == "subscribed":
        return 200, ok_msg
    if cur and cur["status"] == "pending" and cur["since_sent"] < 600:
        return 200, ok_msg                                       # asked again within 10 minutes: do not send twice
    store.upsert_pending(email, secrets.token_urlsafe(24), CFG["consent_text"])
    touch()                                                      # ask the allowlist sync to run now
    return 200, ok_msg


def make_handler(store, limiter, touch):
    class H(BaseHTTPRequestHandler):
        server_version = "coinor-mail"
        sys_version = ""

        def log_message(self, *a):
            pass

        def _cors(self):
            self.send_header("Access-Control-Allow-Origin", CFG["site"])
            self.send_header("Access-Control-Allow-Methods", "POST, OPTIONS")
            self.send_header("Access-Control-Allow-Headers", "Content-Type")
            self.send_header("Vary", "Origin")

        def _json(self, code, obj):
            b = json.dumps(obj).encode()
            self.send_response(code); self._cors()
            self.send_header("Content-Type", "application/json"); self.send_header("Content-Length", str(len(b)))
            self.send_header("Cache-Control", "no-store"); self.end_headers(); self.wfile.write(b)

        def _go(self, key):
            self.send_response(302); self.send_header("Location", f'{CFG["site"]}/notice/{key}')
            self.send_header("Cache-Control", "no-store"); self.end_headers()

        def do_OPTIONS(self):
            self.send_response(204); self._cors(); self.end_headers()

        def do_GET(self):
            u = urlparse(self.path); t = (parse_qs(u.query).get("t") or [""])[0]
            if u.path in ("/api/confirm", "/api/unsubscribe"):
                if not TOKEN_RE.match(t):
                    return self._go("invalid")
                try:
                    done = store.confirm(t) if u.path.endswith("confirm") else store.unsubscribe(t)
                except Exception:
                    return self._go("invalid")
                return self._go(("confirmed" if u.path.endswith("confirm") else "unsubscribed") if done else "invalid")
            self._json(404, {"ok": False})

        def do_POST(self):
            u = urlparse(self.path)
            if u.path == "/api/unsubscribe":                      # RFC 8058 one-click
                t = (parse_qs(u.query).get("t") or [""])[0]
                if TOKEN_RE.match(t):
                    try:
                        store.unsubscribe(t)
                    except Exception:
                        pass
                return self._json(200, {"ok": True})
            if u.path != "/api/subscribe":
                return self._json(404, {"ok": False})
            try:
                n = int(self.headers.get("Content-Length") or 0)
                if n > 2048:
                    return self._json(413, {"ok": False, "msg": "Too large."})
                raw = self.rfile.read(n).decode("utf-8", "replace")
                body = json.loads(raw) if raw.lstrip().startswith("{") else {k: v[0] for k, v in parse_qs(raw).items()}
            except Exception:
                return self._json(400, {"ok": False, "msg": "Could not read that."})
            ip = self.headers.get("X-Real-IP") or self.client_address[0]
            try:
                code, obj = handle_subscribe(store, limiter, ip, body, touch)
            except Exception as e:
                print("subscribe error:", type(e).__name__, str(e)[:120], flush=True)
                code, obj = 500, {"ok": False, "msg": "Something went wrong on our side. Please try again later."}
            self._json(code, obj)
    return H


def touch_trigger():
    try:
        with open(CFG["trigger"], "w") as f:
            f.write(str(time.time()))
    except OSError:
        pass


def worker(store):
    last_clean = 0
    while True:
        try:
            send_confirmations(store, log=lambda m: print(m, flush=True))
            if time.time() - last_clean > 3600:
                store.cleanup(); last_clean = time.time()
        except Exception as e:
            print("worker error:", type(e).__name__, str(e)[:120], flush=True)
        time.sleep(15)


def serve():
    store = PgStore()
    threading.Thread(target=worker, args=(store,), daemon=True).start()
    srv = ThreadingHTTPServer((CFG["host"], CFG["port"]), make_handler(store, Limiter(), touch_trigger))
    print("coinor-mail listening", CFG["host"], CFG["port"], flush=True)
    srv.serve_forever()


# ----------------------------------------------------------------------------- the weekly digest
def excerpt(body, n=230):
    paras = [p.strip() for p in re.split(r"\n\s*\n", body) if p.strip()]
    first = next((p for i, p in enumerate(paras) if i > 0 and len(p) >= 140), paras[0] if paras else "")
    first = first.replace("*", "")
    return first if len(first) <= n else re.sub(r"\s+\S*$", "", first[:n - 1]) + "…"


def digest_episodes(feed, now, days=7):
    out = []
    for e in feed.get("episodes", []):
        if e.get("status") != "approved":
            continue
        try:
            t = dt.datetime.fromisoformat(e["published_at"])
        except (KeyError, ValueError):
            continue
        if (now - t).total_seconds() <= days * 86400:
            out.append(e)
    return sorted(out, key=lambda e: e["number"])


def digest_msg(to, token, eps):
    unsub = f'{CFG["api_base"]}/unsubscribe?t={token}'
    n = len(eps)
    m = base_msg(to, f"The Scroll from the Realm: {n} new chapter{'s' if n != 1 else ''}", unsub)
    lines, htmls = [], []
    for e in eps:
        link = f'{CFG["site"]}/episode/{e["number"]}'
        ex = excerpt(e["body"])
        lines.append(f'Episode {e["number"]}: {e["title"]}\n{ex}\nRead it: {link}\n')
        htmls.append(f'<h3 style="margin:18px 0 4px;font-weight:500">Episode {e["number"]}: {e["title"]}</h3>'
                     f'<p style="margin:0 0 6px">{ex}</p><p style="margin:0"><a href="{link}" style="color:#8a2b17">Read the chapter</a></p>')
    m.set_content("The Scroll from the Realm of Coinor\n\nNew chapters this week:\n\n" + "\n".join(lines) +
                  f"\nFiction, not financial advice. Written by AI and published after safety checks.\n\n{FOOT}\nUnsubscribe in one click: {unsub}\n")
    m.add_alternative('<div style="font-family:Georgia,serif;max-width:560px;color:#2a1b0d;line-height:1.6">'
                      '<h2 style="font-weight:500">The Scroll from the Realm of Coinor</h2><p>New chapters this week:</p>' + "".join(htmls) +
                      '<p style="font-size:13px;color:#5b4429;margin-top:22px">Fiction, not financial advice. Written by AI and published after safety checks.</p>'
                      f'<p style="font-size:12px;color:#5b4429">{FOOT}<br><a href="{unsub}" style="color:#5b4429">Unsubscribe in one click</a></p></div>', subtype="html")
    return m


def run_digest(store, now, sender=smtp_send, feed=None, dry=False, force=False, only=None, notify=lambda m: None, state_path=None, sleep=time.sleep):
    state_path = state_path or CFG["state"]
    feed = feed if feed is not None else json.load(open(CFG["feed"]))
    eps = digest_episodes(feed, now)
    if not eps:
        return {"outcome": "nothing_new"}
    try:
        last = dt.datetime.fromisoformat(json.load(open(state_path))["last_sent"])
    except (OSError, KeyError, ValueError):
        last = None
    if last and (now - last).days < CFG["digest_min_gap_days"] and not force and not only:
        return {"outcome": "too_soon", "last": last.isoformat()}
    subs = store.subscribers()
    if only:
        subs = [(e, t) for e, t in subs if e == only.lower()]
    if dry:
        return {"outcome": "dry_run", "episodes": [e["number"] for e in eps], "recipients": len(subs)}
    sent = failed = 0
    for email, token in subs:
        try:
            sender(digest_msg(email, token, eps)); sent += 1
        except Exception:
            failed += 1
        sleep(1.2)
    if sent and not only:
        os.makedirs(os.path.dirname(state_path), exist_ok=True)
        json.dump({"last_sent": now.isoformat(timespec="seconds"), "sent": sent, "failed": failed, "episodes": [e["number"] for e in eps]}, open(state_path, "w"))
    notify(f"Newsletter sent: {sent} delivered, {failed} failed, {len(eps)} chapters.")
    return {"outcome": "sent", "sent": sent, "failed": failed, "episodes": [e["number"] for e in eps]}


def notify_chairman(msg):
    cmd = ["hermes", "send", "--to", CFG["chairman"]]
    if os.geteuid() == 0:
        cmd = ["sudo", "-u", "hermes"] + cmd
    try:
        subprocess.run(cmd, input=msg, text=True, capture_output=True, timeout=30)
    except Exception:
        pass


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = ap.add_subparsers(dest="cmd", required=True)
    sub.add_parser("serve"); sub.add_parser("stats")
    d = sub.add_parser("digest"); d.add_argument("--dry-run", action="store_true"); d.add_argument("--force", action="store_true"); d.add_argument("--to")
    a = ap.parse_args(argv)
    if a.cmd == "serve":
        serve()
    elif a.cmd == "stats":
        print(json.dumps(PgStore().counts()))
    else:
        r = run_digest(PgStore(), dt.datetime.now(dt.timezone.utc), dry=a.dry_run, force=a.force, only=a.to, notify=notify_chairman)
        print(json.dumps(r))


if __name__ == "__main__":
    main()
