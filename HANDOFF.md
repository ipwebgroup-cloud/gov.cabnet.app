# gov.cabnet.app — Bolt → EDXEIX Bridge HANDOFF

Last updated: 2026-04-25  
Project: gov.cabnet.app Bolt → EDXEIX bridge  
Domain: https://gov.cabnet.app  
Repository: https://github.com/ipwebgroup-cloud/gov.cabnet.app  
Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow.

## Current validated baseline

The project is in a safe pre-production state. The EDXEIX preparation prerequisites that do not require a real Bolt ride have been completed.

Current state:

- `/ops/index.php` is a safe guided Operations Console landing page.
- `/ops/help.php` exists and explains the novice/operator workflow.
- `/ops/future-test.php` exists and shows the real future Bolt test checklist.
- `/ops/mappings.php` exists and includes guarded mapping coverage/editor behavior.
- `/ops/jobs.php` exists for queue/attempt visibility.
- `/ops/cleanup-lab.php` exists for LAB dry-run cleanup.
- `/ops/edxeix-session.php` exists and can save EDXEIX submit URL, Cookie header, and CSRF token to server-only files.
- `/ops/live-submit.php` exists as the disabled live-submit gate.
- EDXEIX submit URL is configured server-side.
- EDXEIX Cookie/CSRF session is saved server-side and currently reports ready.
- Placeholder/example session detection is active and working.
- Live EDXEIX HTTP transport is still intentionally blocked.
- No real future Bolt candidate currently exists.
- No live EDXEIX submission has been performed by this app.

## Server paths

Expected cPanel/server layout:

```text
/home/cabnet/public_html/gov.cabnet.app
/home/cabnet/gov.cabnet.app_app
/home/cabnet/gov.cabnet.app_config
/home/cabnet/gov.cabnet.app_sql
```

Important server-only files:

```text
/home/cabnet/gov.cabnet.app_config/live_submit.php
/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json
```

These files may contain sensitive session/config values and must never be committed, zipped for public delivery, pasted into chat, or shown in screenshots.

## Current ops URLs

Use these pages for operations:

```text
https://gov.cabnet.app/ops/
https://gov.cabnet.app/ops/readiness.php
https://gov.cabnet.app/ops/future-test.php
https://gov.cabnet.app/ops/mappings.php
https://gov.cabnet.app/ops/jobs.php
https://gov.cabnet.app/ops/edxeix-session.php
https://gov.cabnet.app/ops/live-submit.php
https://gov.cabnet.app/ops/help.php
```

JSON checks:

```text
https://gov.cabnet.app/ops/edxeix-session.php?format=json
https://gov.cabnet.app/ops/live-submit.php?format=json
https://gov.cabnet.app/ops/future-test.php?format=json
https://gov.cabnet.app/bolt_readiness_audit.php
https://gov.cabnet.app/bolt_edxeix_preflight.php?limit=30
```

## Latest confirmed production-prep status

From Andreas' latest verification:

```text
EDXEIX URL configured: yes
EDXEIX session ready: yes
Real future candidates: 0
Live-eligible rows: 0
Live HTTP execution: no
```

Expected remaining blockers on `/ops/live-submit.php`:

```text
live_submit_config_disabled
http_submit_config_disabled
no_real_future_candidate
no_selected_real_future_candidate
http_transport_not_enabled_in_this_patch
```

These blockers are correct and intentional.

## Known mappings / test constraints

Known good driver mapping:

```text
Filippos Giannakopoulos → EDXEIX driver ID 17585
```

Known good vehicle mappings:

```text
EMX6874 → EDXEIX vehicle ID 13799
EHA2545 → EDXEIX vehicle ID 5949
```

Known reference-only EDXEIX driver IDs:

```text
1658 — ΒΙΔΑΚΗΣ ΝΙΚΟΛΑΟΣ
17585 — ΓΙΑΝΝΑΚΟΠΟΥΛΟΣ ΦΙΛΙΠΠΟΣ
6026 — ΜΑΝΟΥΣΕΛΗΣ ΙΩΣΗΦ
```

Do not use Georgios Zachariou for the first real test. He is intentionally left unmapped for now.

## What has been validated

Validated so far:

- Bolt API sync can import historical/completed/cancelled Bolt rows.
- Normalized bookings exist and feed the readiness/preflight tools.
- LAB/local future booking dry-run path was tested.
- LAB cleanup was tested and returned the system to clean state.
- Queue staging and dry-run worker audit were tested with local data.
- Mapping dashboard/editor exists and is guarded.
- Live submit gate correctly refuses historical/terminal rows.
- Live submit gate no longer auto-selects old finished/cancelled rows.
- EDXEIX session readiness helper exists.
- EDXEIX session web form can save server-only prerequisites.
- EDXEIX session and submit URL now report globally ready.
- Live HTTP transport remains blocked in code.

## What is still not done

Not yet done:

- No real future Bolt ride has been created/tested.
- No real future Bolt candidate has appeared in the system.
- Final EDXEIX HTTP transport is not implemented/enabled.
- No live EDXEIX submission has been performed.
- No cron/automated near-real-time polling is enabled yet.

## Current production blocker

The next real operational blocker is:

```text
A real future Bolt ride must be created with Filippos and a mapped vehicle.
```

This requires Filippos to be available/present.

Recommended first real test:

```text
Driver: Filippos Giannakopoulos / EDXEIX 17585
Vehicle: EMX6874 / EDXEIX 13799
Alternative vehicle: EHA2545 / EDXEIX 5949
Start time: 40–60 minutes in the future
```

The ride must be real, future-dated, not completed, not cancelled, not expired, and not terminal.

## Real future test procedure

When Filippos is available:

1. Open `/ops/readiness.php`.
2. Confirm readiness is clean.
3. Open `/ops/future-test.php`.
4. Confirm the system is waiting for a real future Bolt ride.
5. Create/schedule one real Bolt ride 40–60 minutes in the future using Filippos and EMX6874 or EHA2545.
6. Run `/bolt_sync_orders.php`.
7. Reopen `/ops/future-test.php`.
8. Confirm a real future candidate appears.
9. Open `/bolt_edxeix_preflight.php?limit=30`.
10. Confirm mapping-ready, future-guard-passed, and non-terminal status.
11. Stage local dry-run job only.
12. Run dry-run worker/attempt path.
13. Confirm live attempts remain zero.
14. Stop before live HTTP submission.

## Final live-submit phase

Actual live EDXEIX submission requires a separate final patch and explicit approval.

Before final live HTTP transport:

- A real future Bolt candidate must exist.
- Preflight must pass.
- EDXEIX session must still be ready and fresh.
- EDXEIX submit URL must still be configured.
- Duplicate protection must be clear.
- Server live flag must be deliberately enabled for one controlled test.
- Server HTTP flag must be deliberately enabled after final approval.
- Final HTTP transport code must be implemented.
- Submission must be one-shot, audited, and then disabled again.

Current live-submit page is intentionally a gate/scaffold only.

## Safety rules

Continue following these rules:

- Default to read-only, dry-run, preview, audit, queue visibility, and preflight behavior.
- Do not enable live EDXEIX submission unless Andreas explicitly asks for the final live-submit patch.
- Historical, cancelled, terminal, expired, invalid, LAB, test, or past Bolt orders must never be submitted to EDXEIX.
- Never expose API keys, DB passwords, cookies, CSRF tokens, session files, or private credentials.
- Real config/session files remain server-only and ignored by Git.
- Patch zips must have the live/repository structure at zip root; never wrap files in an extra package folder.
