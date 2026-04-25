# CABnet EDXEIX Session Capture — Firefox Extension

Private Firefox WebExtension for authorized CABnet operators.

## What it does

When clicked from the logged-in EDXEIX lease-agreement creation page, it captures:

- EDXEIX form action URL
- hidden `_token` CSRF value
- EDXEIX cookies through the Firefox cookies API

It then posts those values to:

```text
https://gov.cabnet.app/ops/edxeix-session-capture.php
```

The server saves them to server-only files and keeps live submission disabled.

## Install temporarily in Firefox

1. Open Firefox.
2. Go to `about:debugging`.
3. Click **This Firefox**.
4. Click **Load Temporary Add-on**.
5. Select `manifest.json` from this folder.

Temporary add-ons remain loaded until Firefox is restarted.

## Use

1. Log in to EDXEIX.
2. Open `https://edxeix.yme.gov.gr/dashboard/lease-agreement/create`.
3. Click the extension icon.
4. Click **Capture from EDXEIX tab**.
5. Type: `SAVE EDXEIX SESSION SERVER SIDE`.
6. Click **Save to gov.cabnet.app**.
7. Verify in `https://gov.cabnet.app/ops/edxeix-session.php`.

## Safety

- No EDXEIX submission is performed.
- No Bolt request is performed.
- Cookie and CSRF values are not displayed in the popup.
- Server forces `live_submit_enabled=false` and `http_submit_enabled=false`.
