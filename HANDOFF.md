# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

## Current validated baseline

Domain: `https://gov.cabnet.app`  
Repo: `https://github.com/ipwebgroup-cloud/gov.cabnet.app`  
Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload.

Expected cPanel layout:

```text
/home/cabnet/public_html/gov.cabnet.app
/home/cabnet/gov.cabnet.app_app
/home/cabnet/gov.cabnet.app_config
/home/cabnet/gov.cabnet.app_sql
```

## Current state

- Ops access guard is active.
- Safe operations landing page is installed at `/ops/index.php`.
- Novice operator help page is installed at `/ops/help.php`.
- Future-test checklist is installed at `/ops/future-test.php`.
- Mapping dashboard/editor is installed at `/ops/mappings.php`.
- Live-submit gate is installed at `/ops/live-submit.php`, but live HTTP transport is still blocked.
- EDXEIX session readiness helper is installed at `/ops/edxeix-session.php`.
- EDXEIX session capture templates are now included.
- LAB dry-run data was cleaned.
- Live EDXEIX submission is still disabled and unauthorized.

## Current known mappings

Driver:

```text
Filippos Giannakopoulos → EDXEIX driver ID 17585
```

Vehicles:

```text
EMX6874 → EDXEIX vehicle ID 13799
EHA2545 → EDXEIX vehicle ID 5949
```

Known EDXEIX reference-only driver IDs:

```text
1658  — ΒΙΔΑΚΗΣ ΝΙΚΟΛΑΟΣ
17585 — ΓΙΑΝΝΑΚΟΠΟΥΛΟΣ ΦΙΛΙΠΠΟΣ
6026  — ΜΑΝΟΥΣΕΛΗΣ ΙΩΣΗΦ
```

Do not map Georgios Zachariou until his exact EDXEIX driver ID is independently confirmed.

## Latest patch state

Patch name: `gov_edxeix_session_capture_prep_patch_rooted.zip`

Purpose:

- add EDXEIX session JSON templates,
- update live submit config example,
- update `/ops/help.php` with session/submit URL preparation steps,
- document production secrets rules,
- keep all real credentials and session values server-only.

Files changed/added:

```text
public_html/gov.cabnet.app/ops/help.php
gov.cabnet.app_config_examples/edxeix_session.example.json
gov.cabnet.app_config_examples/live_submit.example.php
gov.cabnet.app_app/storage/runtime/edxeix_session.example
docs/EDXEIX_SESSION_CAPTURE_TEMPLATE.md
docs/EDXEIX_PRODUCTION_SECRETS_RULES.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Real server-only session/config files

The real files must remain ignored and must not be committed:

```text
/home/cabnet/gov.cabnet.app_config/live_submit.php
/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json
```

The current `.gitignore` ignores:

```text
/gov.cabnet.app_config/live_submit.php
/gov.cabnet.app_app/storage/runtime/*
```

and allows the tracked `.example` runtime template.

## Verification URLs

```text
https://gov.cabnet.app/ops/
https://gov.cabnet.app/ops/help.php
https://gov.cabnet.app/ops/readiness.php
https://gov.cabnet.app/ops/future-test.php
https://gov.cabnet.app/ops/mappings.php
https://gov.cabnet.app/ops/live-submit.php
https://gov.cabnet.app/ops/edxeix-session.php
https://gov.cabnet.app/ops/edxeix-session.php?format=json
```

Expected current EDXEIX session status until values are prepared:

```text
live_submit.php exists: yes
session cookie/CSRF ready: no
submit URL configured: no
calls_edxeix: false
prints_secrets: false
```

## Remaining blockers before final live EDXEIX submit

1. A real future Bolt candidate must exist.
2. Filippos must be available for the real future Bolt test.
3. The test must use Filippos and mapped vehicle `EMX6874` or `EHA2545`.
4. The EDXEIX submit URL must be configured server-side.
5. The EDXEIX session file must contain valid server-only `cookie_header` and `csrf_token` values.
6. Preflight and dry-run path must pass.
7. Final HTTP live transport patch must be separately prepared and explicitly approved.
8. `live_submit_enabled` and `http_submit_enabled` must remain false until the approved one-shot test.

## Safety boundary

Do not enable live EDXEIX submission unless Andreas explicitly asks for the final live-submit update and a real eligible future Bolt trip exists. Historical, cancelled, terminal, expired, invalid, or past Bolt orders must never be submitted.
