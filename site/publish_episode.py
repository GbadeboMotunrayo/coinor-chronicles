#!/usr/bin/env python3
"""
Publish an APPROVED CoinorChronicles episode into site/episodes.json.

Usage
  python3 publish_episode.py draft.txt                 # add the episode in draft.txt
  python3 publish_episode.py draft.txt --number 5      # force the episode number
  python3 publish_episode.py --list                    # show what is published
  python3 publish_episode.py --unpublish 5             # take an episode down

draft.txt is simply the Telegram draft text, pasted into a file. The header lines
the pipeline adds ("CoinorChronicles - Episode N DRAFT", the clan line, the review
flag, "NOTHING has been published") are stripped automatically. Scene notes and the
[MEMORY] line are removed from the public text.

This only edits episodes.json on your laptop. Nothing goes live until you upload the
site files. A backup of the previous file is kept as episodes.json.bak.
"""
import argparse, json, os, re, shutil, sys
from datetime import date

HERE = os.path.dirname(os.path.abspath(__file__))
DB = os.path.join(HERE, "episodes.json")
CLAN_KEYS = {"ancients": "ancients", "swift": "swift", "meme lords": "meme_lords", "builders": "builders"}
COND_KEYS = {"golden season": "golden_season", "dark siege": "dark_siege", "waiting plains": "waiting_plains"}


def load():
    with open(DB, encoding="utf-8") as f:
        return json.load(f)


def save(data):
    if os.path.exists(DB):
        shutil.copyfile(DB, DB + ".bak")
    tmp = DB + ".tmp"
    with open(tmp, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=2)
        f.write("\n")
    os.replace(tmp, DB)


def parse(text):
    text = text.replace("\r\n", "\n")
    text = re.sub(r"^\(\d+/\d+\)\n", "", text, flags=re.M)          # Telegram chunk markers
    lines = text.strip().split("\n")
    header, meta = [], {"number": None, "clan": None, "condition": None}
    i = 0
    while i < len(lines) and lines[i].strip():                       # header = lines before the first blank line
        header.append(lines[i]); i += 1
    for h in header:
        m = re.search(r"Episode\s+(\d+)", h, re.I)
        if m and meta["number"] is None:
            meta["number"] = int(m.group(1))
        low = h.lower()
        for k, v in CLAN_KEYS.items():
            if k in low and meta["clan"] is None and ("clan" in low or "celebration" in low):
                meta["clan"] = v
        for k, v in COND_KEYS.items():
            if k.replace(" ", "") in low.replace("_", "").replace(" ", "") and meta["condition"] is None:
                meta["condition"] = v
    rest = "\n".join(lines[i:]).strip()
    rest = "\n".join(l for l in rest.split("\n") if not l.strip().startswith("[MEMORY]"))
    scene = ""
    si = rest.find("[SCENE NOTES]")
    if si >= 0:
        scene = rest[si:].replace("[SCENE NOTES]", "").strip()
        rest = rest[:si].strip()
    parts = rest.split("\n")
    title = re.sub(r"[*#]", "", parts[0]).strip()
    body = "\n".join(parts[1:]).strip()
    return meta, title, body, scene


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("draft", nargs="?")
    ap.add_argument("--number", type=int)
    ap.add_argument("--clan", choices=sorted(set(CLAN_KEYS.values())))
    ap.add_argument("--condition", choices=sorted(set(COND_KEYS.values())))
    ap.add_argument("--date")
    ap.add_argument("--replace", action="store_true", help="overwrite an existing episode with the same number")
    ap.add_argument("--list", action="store_true")
    ap.add_argument("--unpublish", type=int)
    a = ap.parse_args()
    data = load()
    eps = data.setdefault("episodes", [])

    if a.list:
        for e in sorted(eps, key=lambda x: x["number"]):
            print(f"{e['number']:>3}  {e['date']}  {e['status']:<9} {e['title']}")
        if not eps:
            print("(nothing published)")
        return
    if a.unpublish is not None:
        keep = [e for e in eps if e["number"] != a.unpublish]
        if len(keep) == len(eps):
            sys.exit(f"Episode {a.unpublish} is not published.")
        data["episodes"] = keep; save(data); print(f"Episode {a.unpublish} unpublished."); return
    if not a.draft:
        ap.error("give a draft file, or use --list / --unpublish")

    meta, title, body, scene = parse(open(a.draft, encoding="utf-8").read())
    number = a.number or meta["number"] or (max([e["number"] for e in eps], default=0) + 1)
    clan, cond = a.clan or meta["clan"], a.condition or meta["condition"]
    problems = []
    if not title: problems.append("could not find a title (first line after the header)")
    if len([p for p in body.split("\n\n") if p.strip()]) < 3: problems.append("body has fewer than 3 paragraphs")
    if not clan: problems.append("clan not found - pass --clan ancients|swift|meme_lords|builders")
    if not cond: problems.append("market condition not found - pass --condition golden_season|dark_siege|waiting_plains")
    if any(e["number"] == number for e in eps) and not a.replace:
        problems.append(f"episode {number} already exists - use --replace or --number")
    if problems:
        sys.exit("Not published:\n  - " + "\n  - ".join(problems))

    eps[:] = [e for e in eps if e["number"] != number]
    eps.append({"number": number, "title": title, "clan": clan, "condition": cond,
                "date": a.date or date.today().isoformat(), "status": "approved", "body": body, "scene_notes": scene})
    eps.sort(key=lambda e: e["number"])
    save(data)
    print(f"Published episode {number}: {title}  ({clan}, {cond})")
    print("Now upload the site files (see README_SITE.md). Nothing is live yet.")


if __name__ == "__main__":
    main()
