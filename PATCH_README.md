# gov.cabnet.app v4.9 — Final Dry-run Production Freeze Patch

## What changed

Adds a final dry-run production freeze gate:

- `public_html/gov.cabnet.app/ops/production-freeze.php`
- `gov.cabnet.app_app/cli/freeze_dry_run_production.php`
- `docs/BOLT_PRODUCTION_FREEZE_V4_9.md`
- updated `HANDOFF.md`
- updated `CONTINUE_PROMPT.md`

## Upload paths

```text
public_html/gov.cabnet.app/ops/production-freeze.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/production-freeze.php

gov.cabnet.app_app/cli/freeze_dry_run_production.php
→ /home/cabnet/gov.cabnet.app_app/cli/freeze_dry_run_production.php
```

Docs are for repository/package continuity.

## SQL

None.

## Verify syntax

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/production-freeze.php
php -l /home/cabnet/gov.cabnet.app_app/cli/freeze_dry_run_production.php
```

## Open

```text
https://gov.cabnet.app/ops/production-freeze.php?key=INTERNAL_API_KEY
https://gov.cabnet.app/ops/production-freeze.php?key=INTERNAL_API_KEY&format=json
```

## Freeze dry-run posture

After reviewing the page, run:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/freeze_dry_run_production.php --by=Andreas
```

Expected marker:

```text
/home/cabnet/gov.cabnet.app_app/storage/security/production_dry_run_freeze.json
```

## Safety

This patch does not enable live submit, call Bolt, call EDXEIX, import mail, send driver email, create bookings, create evidence, create jobs, create attempts, or store secrets.
