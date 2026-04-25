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
- `/ops/index.php` is a safe guided operations dashboard.
- `/ops/help.php` is a novice help page and includes EDXEIX session preparation steps.
- `/ops/readiness.php` and `/ops/future-test.php` are the main readiness checks.
- `/ops/mappings.php` contains the mapping dashboard/editor.
- `/ops/live-submit.php` is a disabled live-submit gate; live HTTP transport is still blocked.
- `/ops/edxeix-session.php` checks EDXEIX session and submit URL readiness without printing secrets.
- `edxeix_live_submission_audit` table exists.
- Live EDXEIX submission is disabled and unauthorized.

## Latest patch state

Patch name: `gov_edxeix_placeholder_session_detection_patch_rooted.zip`

Purpose:

- prevent copied example/template EDXEIX session values from being counted as ready,
- update `/ops/edxeix-session.php` with placeholder detection,
- update `edxeix_live_submit_gate.php` so the live-submit gate also treats placeholders as not ready,
- keep all real session data server-only and never displayed.

Expected current status if the template was copied to the runtime file:

```text
Session file exists: yes
JSON valid: yes
Cookie header present: yes
CSRF token present: yes
Placeholder/example values: detected
Session cookie/CSRF ready: no
```

That is correct until real EDXEIX browser/session values are entered server-side.

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

## Remaining blockers before actual live EDXEIX submission

```text
1. Real future Bolt candidate using Filippos + mapped vehicle.
2. Real server-side EDXEIX cookie/CSRF session values, not placeholders.
3. Exact EDXEIX submit URL configured server-side.
4. Final HTTP transport patch, explicitly approved by Andreas.
5. One-shot live config enablement only after preflight/dry-run validation.
```

## Safety

Do not ask for or expose real cookies, CSRF tokens, API keys, DB passwords, or session files. Real config/session files stay server-only and ignored by Git.
