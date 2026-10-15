# Mailing list: signup, double opt-in, weekly digest

One Python file (`coinor_mail.py`, standard library only) on the VPS, run as `coinor-mail.service`.

```
site form (subscribe.js) --POST--> https://coinor.2-24-128-252.sslip.io/api/subscribe   (nginx -> 127.0.0.1:8099)
   -> Paperclip `leads` row, status `pending`, one-time token, consent text + time
   -> touches /var/lib/coinor/allowlist.trigger  (systemd path unit runs the allowlist sync at once)
   -> confirmation email through the normal Postfix submission port (the Chairman's allowlist still applies)
   -> reader opens /api/confirm?t=...  ->  status `subscribed`  ->  /notice/confirmed
Sundays 08:00 Lagos: coinor-digest.timer -> `coinor_mail.py digest` -> this week's chapters from the public feed, to confirmed readers
every email: one-click unsubscribe (/api/unsubscribe?t=...), List-Unsubscribe headers
```

Safe by design: no tracking pixels or IP addresses stored; honeypot field; per-IP and global rate limits; unconfirmed requests are
deleted after 7 days; the same reply whatever state an address is in (no probing). The digest never starts twice within 5 days.

Commands (on the VPS, as hermes): `coinor_mail.py stats`, `coinor_mail.py digest --dry-run`, `digest --to ADDRESS` (test send, does not start the clock), `digest --force`.
`migration_leads_double_optin.sql` is the table change that was applied. 24 tests: `python3 -m unittest test_mail`.
