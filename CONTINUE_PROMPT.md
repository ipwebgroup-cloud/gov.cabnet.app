# CONTINUE PROMPT — gov.cabnet.app Bolt → EDXEIX Bridge

You are continuing the gov.cabnet.app Bolt → EDXEIX bridge project.

Treat the latest uploaded files in the new chat as the primary source of truth. Then inspect this file and `HANDOFF.md`.

## Project context

- Domain: https://gov.cabnet.app
- Repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload
- No frameworks, Composer, Node, or heavy dependencies unless Andreas explicitly approves

## Server layout

```text
/home/cabnet/public_html/gov.cabnet.app
/home/cabnet/gov.cabnet.app_app
/home/cabnet/gov.cabnet.app_config
/home/cabnet/gov.cabnet.app_sql
```

## Current state

The system has:

- Guided ops console
- Novice help page
- Readiness audit
- Future test checklist
- Mapping dashboard/editor
- Sanitized mapping JSON
- Known EDXEIX driver reference panel
- LAB dry-run harness and cleanup
- Ops access guard
- Disabled live-submit gate
- `edxeix_live_submission_audit` table

Live EDXEIX submission is still disabled and intentionally blocked.

## Important pages

```text
/ops/index.php
/ops/help.php
/ops/readiness.php
/ops/future-test.php
/ops/mappings.php
/ops/jobs.php
/ops/live-submit.php
/bolt_readiness_audit.php
/bolt_edxeix_preflight.php?limit=30
```

## Known good first-test mappings

```text
Filippos Giannakopoulos → EDXEIX driver ID 17585
EMX6874 → EDXEIX vehicle ID 13799
EHA2545 → EDXEIX vehicle ID 5949
```

Do not map/use Georgios Zachariou yet.

## Current blocker

The real Bolt → EDXEIX live path cannot be fully tested until Filippos is available and a real future Bolt ride can be created 40–60 minutes in the future.

## Live-submit policy

Do not add actual EDXEIX HTTP submission unless Andreas explicitly asks for the final live-submit transport patch.

The current `/ops/live-submit.php` page is a disabled gate only. It should continue to show why live submission is blocked, what requirements are missing, and what must pass before the first live submit.

## Safe next work if no real Bolt candidate exists

Prefer documentation, GUI clarity, safety checks, runbooks, and production checklist refinements. Do not introduce live transport, auto-submit, or cron auto-staging without explicit approval.
