# EDXEIX Session Capture Template

This document prepares the server-only EDXEIX session and submit URL values needed for the final live-submit phase.

This is a preparation step only. It does not enable live EDXEIX submission.

## Safety rules

- Never commit real EDXEIX cookies, CSRF tokens, passwords, API keys, or session files.
- Never paste real session values into ChatGPT, GitHub, screenshots, or public notes.
- Keep `live_submit_enabled` and `http_submit_enabled` set to `false` until the final one-shot live test is explicitly approved.
- The current live-submit gate still blocks HTTP transport by design.

## Files

Tracked examples included in this patch:

```text
/gov.cabnet.app_config_examples/edxeix_session.example.json
/gov.cabnet.app_app/storage/runtime/edxeix_session.example
/gov.cabnet.app_config_examples/live_submit.example.php
```

Real server-only files:

```text
/home/cabnet/gov.cabnet.app_config/live_submit.php
/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json
```

Both real files must remain ignored by Git.

## Create the real session file

Run on the server:

```bash
cp /home/cabnet/gov.cabnet.app_config_examples/edxeix_session.example.json \
  /home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json
chown cabnet:cabnet /home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json
chmod 600 /home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json
```

Then edit the real file on the server only:

```json
{
  "cookie_header": "PASTE_REAL_COOKIE_HEADER_SERVER_ONLY",
  "csrf_token": "PASTE_REAL_CSRF_TOKEN_SERVER_ONLY",
  "updated_at": "2026-04-25 17:30:00",
  "saved_at": "2026-04-25 17:30:00",
  "notes": "server-only; do not commit"
}
```

## Configure the submit URL

Edit:

```text
/home/cabnet/gov.cabnet.app_config/live_submit.php
```

Set the exact EDXEIX form action URL only after confirming it from the authenticated EDXEIX page:

```php
'edxeix_submit_url' => 'PASTE_EXACT_EDXEIX_FORM_ACTION_URL_HERE',
'edxeix_form_method' => 'POST',
'live_submit_enabled' => false,
'http_submit_enabled' => false,
```

The flags must stay false until the approved live test.

## Verify

Open:

```text
https://gov.cabnet.app/ops/edxeix-session.php
https://gov.cabnet.app/ops/edxeix-session.php?format=json
```

Expected before session setup:

```text
Session cookie/CSRF ready: no
Submit URL configured: no
```

Expected after server-only setup:

```text
Session cookie/CSRF ready: yes
Submit URL configured: yes
No secrets displayed
No EDXEIX call performed
```

## Remaining blockers after this preparation

Even when session and URL are ready, the first live submit still requires:

- one real future Bolt candidate,
- mapped Filippos driver ID `17585`,
- mapped vehicle `EMX6874 → 13799` or `EHA2545 → 5949`,
- preflight payload verified,
- dry-run path completed,
- duplicate protection clear,
- final HTTP transport patch installed,
- explicit approval by Andreas.
