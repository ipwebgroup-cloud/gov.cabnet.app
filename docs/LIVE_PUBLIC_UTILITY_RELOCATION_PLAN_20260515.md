# gov.cabnet.app — Live Public Utility Relocation Plan

Date: 2026-05-15  
Version: v3.0.83-public-utility-relocation-plan

## Purpose

This patch adds a read-only no-break planning tool for six guarded public-root utility endpoints identified by the live public route exposure audit.

It does **not** move, delete, disable, rewrite, or relocate any route.

## Target public-root utility endpoints

- `/bolt-api-smoke-test.php`
- `/bolt-fleet-orders-watch.php`
- `/bolt_stage_edxeix_jobs.php`
- `/bolt_submission_worker.php`
- `/bolt_sync_orders.php`
- `/bolt_sync_reference.php`

## Safety contract

- No Bolt call.
- No EDXEIX call.
- No AADE call.
- No database connection.
- No filesystem writes.
- No route moves.
- No route deletion.
- No live-submit enablement.
- Production pre-ride tool remains untouched.

## Recommended future direction

The planner classifies each utility for later relocation into private CLI and/or supervised `/ops` routes, with compatibility stubs kept in the old public-root locations until cron, monitors, bookmarks, and operator workflows are checked.

The next step after installing this patch is to run the dependency search commands shown by the tool and review whether any cron or external process still calls these public-root scripts.

## Live URL

```text
https://gov.cabnet.app/ops/public-utility-relocation-plan.php
```

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/public_utility_relocation_plan.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/public-utility-relocation-plan.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/route-index.php

curl -I --max-time 10 https://gov.cabnet.app/ops/public-utility-relocation-plan.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/public_utility_relocation_plan.php --json"
```

Expected:

- Syntax checks pass.
- Ops page redirects unauthenticated users to login.
- CLI returns `ok=true`.
- `delete_recommended_now=0`.
- `move_recommended_now=0`.
