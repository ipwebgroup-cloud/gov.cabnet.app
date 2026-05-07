# gov.cabnet.app — v4.9 Final Dry-run Production Freeze

## Purpose

v4.9 adds a final dry-run production freeze gate for the Bolt mail → driver copy → normalized booking → dry-run evidence workflow.

This phase does **not** enable live EDXEIX submission. It freezes the current safe dry-run production posture and creates a no-secret marker that can be reviewed later before any separate v5.0 live-submit design.

## Added files

- `public_html/gov.cabnet.app/ops/production-freeze.php`
- `gov.cabnet.app_app/cli/freeze_dry_run_production.php`

## Read-only production freeze panel

URL:

```text
https://gov.cabnet.app/ops/production-freeze.php?key=INTERNAL_API_KEY
```

JSON:

```text
https://gov.cabnet.app/ops/production-freeze.php?key=INTERNAL_API_KEY&format=json
```

The page checks:

- dry-run mode is enabled
- live submit is disabled
- submission jobs are zero
- submission attempts are zero
- driver identity directory is complete
- driver notification proof exists
- dry-run evidence proof exists
- production crons are healthy
- Maildir is readable
- credential rotation status is visible as a manual gate

## Freeze CLI

Run after reviewing the page:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/freeze_dry_run_production.php --by=Andreas
```

This writes:

```text
/home/cabnet/gov.cabnet.app_app/storage/security/production_dry_run_freeze.json
```

The marker contains no secrets. It records only non-sensitive safety checks, counts, cron freshness, and whether credential rotation has been acknowledged.

## Safety contract

v4.9 does not:

- enable live submit
- call Bolt
- call EDXEIX
- import mail
- send driver email
- create normalized bookings
- create dry-run evidence
- create submission jobs
- create submission attempts
- store secrets

## Expected current verdicts

Before running the freeze CLI:

```text
DRY_RUN_FREEZE_READY
```

After running the freeze CLI, if credential rotation is still pending:

```text
DRY_RUN_PRODUCTION_FROZEN_CREDENTIAL_ROTATION_PENDING
```

After credential rotation is acknowledged:

```text
DRY_RUN_PRODUCTION_FROZEN_CREDENTIALS_ROTATED
```

Live submit must remain off in all v4.9 verdicts.
