# Patch: v3.0.36-ops-ui-sitemap-dashboard

## Purpose

Add a coherent V3 Operations UI entry point and sitemap for the gov.cabnet.app Bolt pre-ride email automation work.

This patch is intentionally read-only and additive.

## What changed

Added:

```text
public_html/gov.cabnet.app/ops/_ops-nav.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php
docs/OPS_SITEMAP_V3.md
PATCH_README.md
```

## Safety posture

This patch does **not**:

- enable live EDXEIX submission
- change the live-submit config
- open the master gate
- modify queue rows
- modify cron workers
- modify mappings
- modify SQL schema/data
- call Bolt
- call EDXEIX
- submit any form

The new dashboard performs read-only database queries when the existing bootstrap/database connection is available.

## Upload paths

Upload files exactly as follows:

```text
public_html/gov.cabnet.app/ops/_ops-nav.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php

docs/OPS_SITEMAP_V3.md
→ /home/cabnet/public_html/gov.cabnet.app/docs/OPS_SITEMAP_V3.md
```

If your local GitHub Desktop repo stores `docs/` at repository root, keep:

```text
docs/OPS_SITEMAP_V3.md
```

in the repo root. The server upload location may remain under the public site only if you intentionally expose docs publicly. If not, keep the docs file in Git only.

## SQL

No SQL required.

## Verification commands

Run on the server after upload:

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php
```

Optional read-only smoke check:

```bash
curl -I https://gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php
```

## Verification URL

Open:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php
```

Also keep open during the pending test:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-queue-watch.php
https://gov.cabnet.app/ops/pre-ride-email-v3-fast-pipeline-pulse.php
```

## Expected result

The new V3 dashboard should show:

- production/read-only/live-submit-disabled posture
- current V3 queue metrics
- latest V3 queue rows
- live-submit gate/config state
- safety guard links
- known verified starting-point facts
- EMT8640 exemption reminder
- next action for the pending test

Expected live-submit status remains:

```text
Enabled: no
Mode: disabled
Adapter: disabled
Hard enable live submit: no
OK for future live submit: no
```

## Rollback

Remove these two public files if needed:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php
```

No SQL rollback is needed.

## Git commit title

```text
Add V3 Ops dashboard and sitemap
```

## Git commit description

```text
Adds a read-only V3 Pre-Ride Automation Control Center for gov.cabnet.app operations.

The dashboard groups queue monitoring, pulse runner status, safety guards, live-submit locked state, known starting-point facts, EMT8640 exemption visibility, and latest queue rows into a coherent operator view.

Also adds a shared Ops navigation partial and a V3 sitemap document for the planned Operations UI structure.

No live-submit behavior, cron logic, queue mutation, mapping, or SQL changes are included.
```
