# EDXEIX Firefox Session Capture Extension

## Purpose

This private Firefox extension reduces manual error when refreshing EDXEIX live-submit prerequisites.

It captures the active EDXEIX lease-agreement form action URL, hidden CSRF `_token`, and EDXEIX cookies, then sends them to the guarded gov.cabnet.app server endpoint.

## Server endpoint

```text
/ops/edxeix-session-capture.php
```

The endpoint saves only to server-only files:

```text
/home/cabnet/gov.cabnet.app_config/live_submit.php
/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json
```

## Extension folder

```text
tools/firefox-edxeix-session-capture/
```

Files:

```text
manifest.json
popup.html
popup.css
popup.js
README.md
```

## Operator workflow

1. Log in to EDXEIX.
2. Open the lease-agreement creation form:
   `https://edxeix.yme.gov.gr/dashboard/lease-agreement/create`
3. Click the Firefox extension icon.
4. Click **Capture from EDXEIX tab**.
5. Type the safety phrase:
   `SAVE EDXEIX SESSION SERVER SIDE`
6. Click **Save to gov.cabnet.app**.
7. Verify:
   `https://gov.cabnet.app/ops/edxeix-session.php`

## Safety behavior

The extension and endpoint do not call Bolt, do not submit to EDXEIX, do not create jobs, and do not enable live submission.

The server endpoint forces:

```php
'live_submit_enabled' => false,
'http_submit_enabled' => false,
```

The endpoint validates:

- POST only for writes.
- Exact confirmation phrase.
- HTTPS URL.
- Host must be `edxeix.yme.gov.gr`.
- Path must begin `/dashboard/lease-agreement`.
- Cookie and CSRF values must not be placeholder/example values.

The endpoint never prints cookie or CSRF values back to the browser.

## Installation note

For development/testing, load the extension temporarily in Firefox from `about:debugging` → **This Firefox** → **Load Temporary Add-on** → select `manifest.json`.

Temporary installation lasts until Firefox is restarted.

For persistent production installation, package/sign the extension or use a controlled Firefox profile where temporary loading is acceptable for the operators.
