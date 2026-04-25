# EDXEIX Session Web Form

This patch adds a guarded web form to `/ops/edxeix-session.php` for two authorized operators to save EDXEIX session/config prerequisites server-side.

## Safety behavior

- GET remains diagnostic.
- POST can write only server-only files.
- No cookies or CSRF tokens are printed back to the browser.
- No Bolt request is made.
- No EDXEIX request is made.
- No database write is made.
- Live submit flags are forced disabled by the form.
- Existing files are backed up before overwrite.
- Placeholder/example values are rejected.

## Files written by the web form

- `/home/cabnet/gov.cabnet.app_config/live_submit.php`
- `/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json`

Both files are server-only and must not be committed.

## Confirmation phrase

The form requires:

```text
SAVE EDXEIX SESSION SERVER SIDE
```

## Required values

- EDXEIX submit URL: must be HTTPS and host `edxeix.yme.gov.gr`.
- Cookie header: full authenticated EDXEIX Cookie request header.
- CSRF token: token from the EDXEIX lease agreement form.

## What remains blocked

Even after valid values are saved, live EDXEIX submission remains blocked until:

- a real future Bolt candidate exists,
- the final HTTP transport patch is approved,
- one-shot live flags are deliberately enabled,
- duplicate protection passes,
- Andreas explicitly approves the live submit test.
