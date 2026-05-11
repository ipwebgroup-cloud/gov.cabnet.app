# gov.cabnet.app patch — Ops UI Shell Phase 25 Ops Dashboard

## Files included

- `public_html/gov.cabnet.app/ops/ops-dashboard.php`
- `docs/OPS_UI_SHELL_PHASE25_OPS_DASHBOARD_2026_05_11.md`

## Upload paths

```text
public_html/gov.cabnet.app/ops/ops-dashboard.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/ops-dashboard.php
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/ops-dashboard.php
```

Open:

```text
https://gov.cabnet.app/ops/ops-dashboard.php
```

## Expected result

- Login required.
- Page opens in shared ops shell.
- Shows safe ops overview, user summary, auth/activity counts, file snapshot, and next safe actions.
- Production pre-ride email tool remains unchanged.

## Git commit title

```text
Add ops dashboard overview
```

## Git commit description

```text
Continues the unified EDXEIX-style /ops GUI by adding a read-only Ops Dashboard overview. The page summarizes the current operator, safe auth/activity counts, critical file status, and next safe actions without touching the production pre-ride route.

The production pre-ride email tool remains unchanged. No Bolt calls, EDXEIX calls, AADE calls, secret output, database writes, queue staging, or live submission behavior are added.
```
