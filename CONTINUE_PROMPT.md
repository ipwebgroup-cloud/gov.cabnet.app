# Continue Prompt — gov.cabnet.app Bolt → EDXEIX Bridge

You are continuing the gov.cabnet.app Bolt → EDXEIX bridge project.

Treat latest uploaded files/screenshots/SQL output as source of truth, then HANDOFF.md and this file.

## Project

- Domain: https://gov.cabnet.app
- Repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload
- Expected paths:
  - `/home/cabnet/public_html/gov.cabnet.app`
  - `/home/cabnet/gov.cabnet.app_app`
  - `/home/cabnet/gov.cabnet.app_config`
  - `/home/cabnet/gov.cabnet.app_sql`

## Current state

The app is safe, guarded, and production-prep ready, but live EDXEIX submission is not enabled.

Validated tools:

- `/ops/index.php` guided operations console
- `/ops/help.php` novice guide/glossary
- `/ops/readiness.php` readiness audit
- `/ops/future-test.php` real future Bolt test checklist
- `/ops/mappings.php` mapping coverage/editor
- `/ops/jobs.php` local queue viewer
- `/ops/live-submit.php` disabled live-submit gate

Latest refinement: `/ops/live-submit.php` now distinguishes analyzed historical rows from real future candidates. It should not auto-select old blocked rows as candidates when no real future candidate exists.

## Known mappings

- Filippos Giannakopoulos → `17585`
- EMX6874 → `13799`
- EHA2545 → `5949`

Leave Georgios Zachariou unmapped for now.

## Critical rule

Do not implement or enable live EDXEIX HTTP submission unless Andreas explicitly approves the final live-submit transport patch and there is a real eligible future Bolt trip.

Default to read-only, dry-run, preflight, queue visibility, and guarded one-shot safety gates.
