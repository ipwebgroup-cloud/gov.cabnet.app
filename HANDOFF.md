# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

## Current state

The bridge is in safe preproduction mode. Live EDXEIX HTTP submission is still blocked.

Installed guarded ops tools include:

- `/ops/` guided console
- `/ops/readiness.php`
- `/ops/future-test.php`
- `/ops/mappings.php`
- `/ops/jobs.php`
- `/ops/live-submit.php`
- `/ops/edxeix-session.php`
- `/ops/help.php`

## Latest change

`/ops/edxeix-session.php` now includes a guarded web form for authorized operators to save:

- EDXEIX submit URL,
- EDXEIX Cookie request header,
- EDXEIX CSRF token.

The form writes only to server-only files and never displays secret values back.

Server-only files:

```text
/home/cabnet/gov.cabnet.app_config/live_submit.php
/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json
```

The form forces:

```text
live_submit_enabled = false
http_submit_enabled = false
```

So this does not enable live submission.

## Current blockers before real live EDXEIX submission

1. A real future Bolt candidate must exist.
2. EDXEIX session values must be real, not placeholders.
3. EDXEIX submit URL must be configured.
4. Final HTTP transport patch must be approved and installed.
5. Live flags must be enabled only for the approved one-shot test.

## Known first test mappings

```text
Filippos Giannakopoulos → EDXEIX driver 17585
EMX6874 → EDXEIX vehicle 13799
EHA2545 → EDXEIX vehicle 5949
```

Leave Georgios Zachariou unmapped for now.

## Safety rules

Do not submit LAB/test rows, historical trips, cancelled trips, finished trips, terminal trips, invalid trips, or past trips to EDXEIX.
Do not expose cookies, CSRF tokens, API keys, DB passwords, or sessions in chat, screenshots, GitHub, or email.
