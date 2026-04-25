# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

## Current baseline

Project: `gov.cabnet.app` Bolt Fleet API → normalized local bookings → EDXEIX preflight/queue/readiness workflow.

Stack:

- Plain PHP
- mysqli/MariaDB
- cPanel/manual upload workflow
- No frameworks, Composer, Node, or heavy dependencies

Expected server layout:

```text
/home/cabnet/public_html/gov.cabnet.app
/home/cabnet/gov.cabnet.app_app
/home/cabnet/gov.cabnet.app_config
/home/cabnet/gov.cabnet.app_sql
```

## Safety state

Live EDXEIX submission is still disabled and intentionally blocked.

Current live-submit gate state:

```text
/ops/live-submit.php installed
edxeix_live_submission_audit table installed
live_submit_enabled: false
http_submit_enabled: false
live_http_transport_enabled_in_this_patch: false
No EDXEIX HTTP request is performed
```

## Verified operational pages

```text
/ops/index.php          Guided safe operations console
/ops/help.php           Novice help/glossary/runbook
/ops/readiness.php      Main readiness audit
/ops/future-test.php    Real future Bolt test checklist
/ops/mappings.php       Mapping dashboard/editor
/ops/jobs.php           Local queue/attempt viewer
/ops/bolt-live.php      Bolt-side operational view
/ops/test-booking.php   LAB/local dry-run booking harness
/ops/cleanup-lab.php    LAB cleanup tool
/ops/live-submit.php    Disabled live-submit production gate
```

## Current readiness posture

Before the next real Bolt test, expected state is:

```text
READY_FOR_REAL_BOLT_FUTURE_TEST
READY TO CREATE REAL FUTURE TEST RIDE
Real future candidates: 0
LAB rows/jobs/attempts: 0
Local jobs: 0
Live attempts: 0
```

## Known mappings

Use for first real test:

```text
Filippos Giannakopoulos → EDXEIX driver ID 17585
EMX6874 → EDXEIX vehicle ID 13799
EHA2545 → EDXEIX vehicle ID 5949
```

Reference-only EDXEIX driver IDs:

```text
1658  — ΒΙΔΑΚΗΣ ΝΙΚΟΛΑΟΣ
17585 — ΓΙΑΝΝΑΚΟΠΟΥΛΟΣ ΦΙΛΙΠΠΟΣ
6026  — ΜΑΝΟΥΣΕΛΗΣ ΙΩΣΗΦ
```

Leave unmapped for now:

```text
Georgios Zachariou / +306944787864 / XRO7604
```

## Current blocker for live EDXEIX submission

No real future Bolt candidate exists yet. Andreas cannot create the test until Filippos is available/present.

Additional live blockers intentionally remain:

- EDXEIX session readiness still needs confirmation.
- Exact EDXEIX submit URL/form action still needs confirmation/configuration.
- Final live HTTP transport patch has not been added.
- Server-only live config remains disabled.

## Next safe step

When Filippos is available:

1. Create/schedule one real Bolt ride 40–60 minutes in the future.
2. Use Filippos plus EMX6874 or EHA2545.
3. Run Bolt sync.
4. Open `/ops/future-test.php`.
5. Open `/bolt_edxeix_preflight.php?limit=30`.
6. Stage/record dry-run only.
7. Confirm live attempts remain `0`.
8. Only then consider the final live HTTP transport patch.

## Hard safety rule

Do not enable or implement live EDXEIX submission unless Andreas explicitly asks for the final live-submit transport patch and a real eligible future Bolt booking exists.
