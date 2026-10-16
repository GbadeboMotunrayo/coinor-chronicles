#!/usr/bin/env python3
"""Run this ONCE on your own laptop to let CoinorChronicles upload to YOUR YouTube channel.

  python3 get_youtube_token.py

It asks for the OAuth client id and secret (typed hidden, never saved except in the file below), opens Google's own sign-in page
in your browser, and then writes the three values the server needs into  yt_keys.env  (readable only by you).
Nothing is sent anywhere except to Google. Standard library only.
"""
import base64, getpass, hashlib, http.server, json, os, secrets, sys, threading, urllib.parse, urllib.request, webbrowser

SCOPE = "https://www.googleapis.com/auth/youtube.upload"
cid = input("OAuth client ID: ").strip()
sec = getpass.getpass("OAuth client secret (hidden): ").strip()
verifier = secrets.token_urlsafe(48)
challenge = base64.urlsafe_b64encode(hashlib.sha256(verifier.encode()).digest()).rstrip(b"=").decode()
state = secrets.token_urlsafe(16)
got = {}

class H(http.server.BaseHTTPRequestHandler):
    def log_message(self, *a): pass
    def do_GET(self):
        q = urllib.parse.parse_qs(urllib.parse.urlparse(self.path).query)
        if q.get("state", [""])[0] == state and "code" in q:
            got["code"] = q["code"][0]
        self.send_response(200); self.send_header("Content-Type", "text/html"); self.end_headers()
        self.wfile.write(b"<h2>Done. You can close this tab and go back to the terminal.</h2>")

srv = http.server.HTTPServer(("127.0.0.1", 0), H)
redirect = f"http://127.0.0.1:{srv.server_address[1]}"
url = "https://accounts.google.com/o/oauth2/v2/auth?" + urllib.parse.urlencode({
    "client_id": cid, "redirect_uri": redirect, "response_type": "code", "scope": SCOPE, "access_type": "offline",
    "prompt": "consent", "state": state, "code_challenge": challenge, "code_challenge_method": "S256"})
print("\nOpening Google sign-in. Choose the account that owns your YouTube channel.\nIf the browser does not open, paste this link into it:\n" + url + "\n")
webbrowser.open(url)
while "code" not in got:
    srv.handle_request()
data = urllib.parse.urlencode({"client_id": cid, "client_secret": sec, "code": got["code"], "code_verifier": verifier,
                               "grant_type": "authorization_code", "redirect_uri": redirect}).encode()
try:
    tok = json.loads(urllib.request.urlopen(urllib.request.Request("https://oauth2.googleapis.com/token", data), timeout=30).read())
except Exception as e:
    sys.exit(f"Google refused the code: {e}")
if not tok.get("refresh_token"):
    sys.exit("Google gave no refresh token. Remove this app at myaccount.google.com/permissions and run the script again.")
fd = os.open("yt_keys.env", os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
with os.fdopen(fd, "w") as f:
    f.write(f"YT_CLIENT_ID={cid}\nYT_CLIENT_SECRET={sec}\nYT_REFRESH_TOKEN={tok['refresh_token']}\n")
print("\nSaved yt_keys.env (only you can read it). Now send it to the server without showing it, then delete it:\n"
      "  ssh mgvps 'sudo tee -a /home/hermes/.hermes/.env >/dev/null' < yt_keys.env && shred -u yt_keys.env")
