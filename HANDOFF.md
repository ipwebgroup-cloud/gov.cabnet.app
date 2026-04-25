# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

## Project identity

- Domain: `https://gov.cabnet.app`
- GitHub repo: `https://github.com/ipwebgroup-cloud/gov.cabnet.app`
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- Public webroot: `/home/cabnet/public_html/gov.cabnet.app`
- Private app folder: `/home/cabnet/gov.cabnet.app_app`
- Private config folder: `/home/cabnet/gov.cabnet.app_config`
- SQL folder: `/home/cabnet/gov.cabnet.app_sql`

## Absolute safety rule

Live EDXEIX submission is **disabled and unauthorized**. Do not add automatic or live submission behavior unless Andreas explicitly asks for a separate live-submit patch after a real eligible future Bolt trip has passed preflight.

Historical, cancelled, terminal, expired, invalid, LAB/test, or past Bolt orders must never be submitted to EDXEIX.

## Current validated baseline

- Bolt API connection works.
- Bolt reference sync works.
- Bolt order sync works.
- Dry-run future booking harness was validated end-to-end.
- LAB cleanup was validated.
- Ops access guard is active through `.user.ini` and `/home/cabnet/gov.cabnet.app_config/ops.php`.
- Legacy `/ops/index.php` was replaced with a safe operations landing page.
- Guided operations dashboard and novice help page were added.
- Real future-test checklist exists and is read-only.
- Mapping dashboard/editor exists and is guarded.
- Mapping JSON output is sanitized and excludes `raw_payload_json`.

## Current expected statuses

- `/ops/readiness.php`: `READY_FOR_REAL_BOLT_FUTURE_TEST`
- `/ops/future-test.php`: `READY TO CREATE REAL FUTURE TEST RIDE`
- Real future candidates: `0`
- LAB/test normalized rows: `0`
- Staged LAB jobs: `0`
- Local submission jobs: `0`
- Submission attempts: `0`
- Live EDXEIX attempts indicated: `0`
- Live submission authorization: `0`

## Current mappings

Driver:

```text
Filippos Giannakopoulos → EDXEIX driver ID 17585
```

Vehicles:

```text
EMX6874 → EDXEIX vehicle ID 13799
EHA2545 → EDXEIX vehicle ID 5949
```

Leave unmapped for now:

```text
Georgios Zachariou / +306944787864 / XRO7604
```

Reference-only EDXEIX driver IDs:

```text
1658  — ΒΙΔΑΚΗΣ ΝΙΚΟΛΑΟΣ
17585 — ΓΙΑΝΝΑΚΟΠΟΥΛΟΣ ΦΙΛΙΠΠΟΣ
6026  — ΜΑΝΟΥΣΕΛΗΣ ΙΩΣΗΦ
```

## Key current pages

```text
/ops/index.php           Guided operations console
/ops/help.php            Novice operator help and glossary
/ops/readiness.php       Readiness dashboard
/ops/future-test.php     Real future Bolt test checklist
/ops/mappings.php        Mapping coverage/editor
/ops/jobs.php            Local queue viewer
/ops/bolt-live.php       Bolt live/status view
/ops/test-booking.php    Local dry-run test harness
/ops/cleanup-lab.php     LAB dry-run cleanup
/bolt_readiness_audit.php
/bolt_edxeix_preflight.php?limit=30
/bolt_jobs_queue.php?limit=50
```

## Remaining blocker

Andreas cannot run the real future Bolt ride test yet because Filippos must be present/available. Until then, do not enable live submission.

## Next real operational step

When Filippos is available:

1. Create a real Bolt ride 40–60 minutes in the future using Filippos and a mapped vehicle.
2. Run `/bolt_sync_orders.php`.
3. Open `/ops/future-test.php`.
4. Open `/bolt_edxeix_preflight.php?limit=30`.
5. Review preflight only.
6. Optionally stage/record dry-run only.
7. Confirm live attempts remain zero.
8. Stop before live submission.

## Do not commit

- `/gov.cabnet.app_config/config.php`
- `/gov.cabnet.app_config/bolt.php`
- `/gov.cabnet.app_config/database.php`
- `/gov.cabnet.app_config/app.php`
- `/gov.cabnet.app_config/edxeix.php`
- `/gov.cabnet.app_config/ops.php`
- real keys, cookies, tokens, session files, logs, runtime files, artifacts, raw SQL dumps, temporary public diagnostic scripts
