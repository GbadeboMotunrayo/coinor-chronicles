#!/usr/bin/env python3
"""Builds the thin n8n scheduler for the autonomous Coinor engine (v3).
   n8n only: (1) fires on schedule, (2) runs the engine over SSH, (3) turns a crash into an alert.
   python3 build_v3.py            -> coinor_v3_autopublish.json
   python3 build_v3.py --test-at "MIN HOUR DAY MONTH"   (adds a one-off trigger for an end-to-end test)
"""
import json, sys
SSH_CRED = {"sshPrivateKey": {"id": "MX558Fm6uLy3U0KX", "name": "SSH Private Key account"}}
test_at = sys.argv[sys.argv.index("--test-at") + 1] if "--test-at" in sys.argv else None

rules = [{"field": "cronExpression", "expression": "0 6,18 * * *"}]
if test_at:
    rules.append({"field": "cronExpression", "expression": test_at + " *"})

nodes = [
    {"parameters": {"content": "## CoinorChronicles v3 - Auto Publish\nRuns the engine at **06:00 and 18:00 Lagos time**: up to **2 episodes a day** (the engine enforces the cap, a 4-hour gap, and skips flat markets).\n\nAll logic lives in `/usr/local/lib/coinor/coinor.py` on the server (tested: 55+ tests). Every episode must pass the rule checker in code before it is published to the feed. If the engine crashes, this workflow raises an alert.\n\n**Kill switch:** `python3 /usr/local/lib/coinor/coinor.py pause on`", "width": 440, "height": 240},
     "id": "note", "name": "note", "type": "n8n-nodes-base.stickyNote", "typeVersion": 1, "position": [-40, -140]},
    {"parameters": {"rule": {"interval": rules}}, "id": "trig", "name": "Twice Daily", "type": "n8n-nodes-base.scheduleTrigger", "typeVersion": 1.2, "position": [0, 200]},
    {"parameters": {"authentication": "privateKey", "command": "python3 /usr/local/lib/coinor/coinor.py run"},
     "id": "ssh", "name": "Run Coinor Engine", "type": "n8n-nodes-base.ssh", "typeVersion": 1, "position": [260, 200], "credentials": SSH_CRED},
    {"parameters": {"conditions": {"options": {"caseSensitive": True, "leftValue": "", "typeValidation": "loose", "version": 2},
        "conditions": [{"id": "c1", "leftValue": "={{ $json.code }}", "rightValue": 0, "operator": {"type": "number", "operation": "notEquals"}}], "combinator": "and"}, "options": {}},
     "id": "if", "name": "Engine Crashed?", "type": "n8n-nodes-base.if", "typeVersion": 2.2, "position": [520, 200]},
    {"parameters": {"errorType": "errorMessage", "errorMessage": "={{ 'Coinor engine exited with code ' + $json.code + '. ' + String($json.stderr || '').slice(-500) }}"},
     "id": "stop", "name": "Raise Alert", "type": "n8n-nodes-base.stopAndError", "typeVersion": 1, "position": [780, 120]},
    {"parameters": {}, "id": "ok", "name": "Done", "type": "n8n-nodes-base.noOp", "typeVersion": 1, "position": [780, 300]},
]
conn = {
    "Twice Daily": {"main": [[{"node": "Run Coinor Engine", "type": "main", "index": 0}]]},
    "Run Coinor Engine": {"main": [[{"node": "Engine Crashed?", "type": "main", "index": 0}]]},
    "Engine Crashed?": {"main": [[{"node": "Raise Alert", "type": "main", "index": 0}], [{"node": "Done", "type": "main", "index": 0}]]},
}
wf = {"id": "coinorV3Auto01", "name": "CoinorChronicles v3 - Auto Publish", "active": False, "nodes": nodes, "connections": conn,
      "settings": {"executionOrder": "v1", "errorWorkflow": "coinorErrorAlert01", "timezone": "Africa/Lagos"}}
json.dump(wf, open("coinor_v3_autopublish.json", "w"), indent=2, ensure_ascii=False)

old = {"id": "coinorV2Pipeline01", "name": "SUPERSEDED - CoinorChronicles v2 (do not activate)", "active": False,
       "nodes": [{"parameters": {"content": "Superseded by **CoinorChronicles v3 - Auto Publish**. Kept empty on purpose so it cannot be activated by mistake.", "width": 360, "height": 100},
                  "id": "n", "name": "n", "type": "n8n-nodes-base.stickyNote", "typeVersion": 1, "position": [0, 0]}],
       "connections": {}, "settings": {"executionOrder": "v1"}}
json.dump(old, open("coinor_v2_superseded.json", "w"), indent=2)
print("wrote coinor_v3_autopublish.json", "(with test trigger %s)" % test_at if test_at else "", "and coinor_v2_superseded.json")
