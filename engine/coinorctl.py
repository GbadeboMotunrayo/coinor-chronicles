#!/usr/bin/env python3
"""coinorctl - what a digital staff member can do with CoinorChronicles, from inside their sandbox.

READ (instant, local):   status | episodes | read N | log [N]
ACT  (queued, ~1 minute): pause | resume | unpublish N | direction "text" | direction --clear

Nothing here can publish an episode, edit an episode's text, or skip a rule check. Acting is a request
file dropped in a mailbox; the engine on the server answers it, tells the Chairman on Telegram, and
enforces who may do what (Maya: everything. Prometheus: pause/resume. Raven and Echo: direction).

The sandbox must mount (see docs): /var/lib/coinor -> /coinor/state (read-only) with its inbox
sub-folder writable, /var/www/coinor/feed -> /coinor/feed, /var/log/coinor -> /coinor/log,
and set COINOR_ACTOR to the staff member's name.
"""
import json, os, re, sys, time, uuid

BASE = os.environ.get("COINOR_BASE", "/coinor")
STATE = os.path.join(BASE, "state")
FEED = os.path.join(BASE, "feed", "episodes.json")
LOG = os.path.join(BASE, "log", "run.log")
INBOX = os.path.join(STATE, "inbox")
OUTBOX = os.path.join(STATE, "outbox")
ACTOR = re.sub(r"[^a-z]", "", os.environ.get("COINOR_ACTOR", "").lower())


def load(path, default=None):
    try:
        with open(path, encoding="utf-8") as f:
            return json.load(f)
    except (OSError, ValueError):
        return default


def episodes():
    return (load(FEED, {"episodes": []}) or {}).get("episodes", [])


def cmd_status():
    last = load(os.path.join(STATE, "last_run.json"), {})
    st = load(os.path.join(STATE, "state.json"), {})
    eps = episodes()
    direction = load(os.path.join(STATE, "direction.json"), None)
    print(json.dumps({
        "episodes_in_feed": len(eps),
        "latest": ({"number": eps[-1]["number"], "title": eps[-1]["title"], "clan": eps[-1]["clan"],
                    "published_at": eps[-1].get("published_at")} if eps else None),
        "last_run": last,
        "episode_counter": st.get("episode"),
        "pending_direction": (direction or {}).get("text"),
        "note": "For the authoritative pause state and today's count, use: coinorctl check",
    }, indent=2))


def cmd_episodes():
    for e in reversed(episodes()):
        print(f"{e['number']:>4}  {e.get('date','')}  {e.get('clan',''):<11} {e['title']}")


def cmd_read(n):
    for e in episodes():
        if e["number"] == n:
            print(f"# Episode {e['number']}: {e['title']}  ({e['clan']}, {e.get('condition','')}, {e.get('date','')})\n")
            print(e["body"])
            return
    sys.exit(f"Episode {n} is not in the feed.")


def cmd_log(n):
    try:
        lines = open(LOG, encoding="utf-8").read().splitlines()[-n:]
    except OSError:
        sys.exit("No log available.")
    print("\n".join(lines))


def ask(verb, **kw):
    if not ACTOR:
        sys.exit("COINOR_ACTOR is not set in this sandbox; cannot send a request.")
    rid = f"{int(time.time())}-{uuid.uuid4().hex[:8]}"
    name = f"req-{rid}.json"
    tmp = os.path.join(INBOX, f".{name}.tmp")
    with open(tmp, "w", encoding="utf-8") as f:
        json.dump(dict(verb=verb, by=ACTOR, **kw), f)
    os.replace(tmp, os.path.join(INBOX, name))
    print(f"Request sent ({verb}). Waiting for the engine (up to 90 seconds)...", flush=True)
    for _ in range(45):
        time.sleep(2)
        r = load(os.path.join(OUTBOX, name))
        if r is not None:
            print(("DONE: " if r.get("ok") else "REFUSED: ") + str(r.get("message")))
            if r.get("status"):
                print(json.dumps(r["status"], indent=2))
            sys.exit(0 if r.get("ok") else 1)
    sys.exit("No answer yet. The request is queued and will run; check again with: coinorctl check")


def main(a):
    if not a or a[0] in ("-h", "--help", "help"):
        print(__doc__)
        return
    c = a[0]
    if c == "status": cmd_status()
    elif c == "check": ask("status")
    elif c == "episodes": cmd_episodes()
    elif c == "read" and len(a) == 2 and a[1].isdigit(): cmd_read(int(a[1]))
    elif c == "log": cmd_log(int(a[1]) if len(a) > 1 and a[1].isdigit() else 30)
    elif c == "pause": ask("pause_on")
    elif c == "resume": ask("pause_off")
    elif c == "unpublish" and len(a) == 2 and a[1].isdigit(): ask("unpublish", number=int(a[1]))
    elif c == "direction" and len(a) >= 2 and a[1] == "--clear": ask("direction_clear")
    elif c == "direction" and len(a) >= 2: ask("direction_set", text=" ".join(a[1:]))
    else:
        sys.exit("Unknown command. Run: coinorctl help")


if __name__ == "__main__":
    main(sys.argv[1:])
