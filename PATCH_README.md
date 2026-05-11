# gov.cabnet.app patch — Ops UI Shell Phase 23 Maintenance Center

## What changed

Adds a read-only shared-shell maintenance page:

- `public_html/gov.cabnet.app/ops/maintenance-center.php`

The page provides critical file snapshots, maintenance checklists, verification commands, optional backup commands, and server-only file reminders.

## Production safety

This patch does not modify:

- `public_html/gov.cabnet.app/ops/pre-ride-email-tool.php`

It does not call Bolt, EDXEIX, or AADE, does not read/display secrets, does not write DB rows, does not stage jobs, and does not enable live EDXEIX submission.

## Upload path

Upload:

- `public_html/gov.cabnet.app/ops/maintenance-center.php`

To:

- `/home/cabnet/public_html/gov.cabnet.app/ops/maintenance-center.php`

## SQL

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/maintenance-center.php
```

Open:

```text
https://gov.cabnet.app/ops/maintenance-center.php
```

## Expected result

- Login required.
- Page opens inside shared ops shell.
- Critical file snapshot appears.
- Maintenance commands are visible.
- Production pre-ride tool remains unchanged.

## Commit title

Add ops maintenance center

## Commit description

Continues the unified EDXEIX-style /ops GUI by adding a read-only Maintenance Center with critical file snapshots, maintenance checklists, verification commands, optional backup commands, and server-only file reminders.

The production pre-ride email tool remains unchanged. No Bolt calls, EDXEIX calls, AADE calls, secret output, database writes, queue staging, or live submission behavior are added.
