# gov.cabnet.app Patch — v6.7.0 EDXEIX Mail Preflight Bridge

## What changed

Adds a safe CLI bridge for creating local normalized EDXEIX preflight bookings from future pre-ride Bolt email intake rows.

Default mode is preview-only. DB writes only occur when `--create` is explicitly supplied.

## Files included

```text
gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php
docs/EDXEIX_MAIL_PREFLIGHT_BRIDGE.md
PATCH_README.md
```

## Upload paths

Upload:

```text
/home/cabnet/gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php
```

Local repo docs:

```text
docs/EDXEIX_MAIL_PREFLIGHT_BRIDGE.md
PATCH_README.md
```

## SQL

None.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php --limit=20 --json

mysql cabnet_gov -e "
SELECT COUNT(*) AS submission_jobs FROM submission_jobs;
SELECT COUNT(*) AS submission_attempts FROM submission_attempts;
"
```

## Expected result

```text
ok: true
version: v6.7.0
preview_only: true
queues_unchanged: true
submission_jobs = 0
submission_attempts = 0
```

If a future pre-ride email is present and unlinked, `preview_ready` should be greater than zero.

## Optional create command

Only after preview review:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php --intake-id=ID --create --json
```

Then run:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php --only-ready --future-hours=168 --limit=100 --json
```

## Safety

This patch does not:

- call EDXEIX;
- issue AADE receipts;
- create `submission_jobs`;
- create `submission_attempts`;
- enable live submission;
- expose secrets.

## Git commit title

```text
Add EDXEIX mail preflight bridge CLI
```

## Git commit description

```text
Adds a safe CLI bridge for the EDXEIX source path, using pre-ride Bolt email intake rows only.

The script previews future unlinked Bolt mail intake rows and can create local normalized EDXEIX preflight bookings only when --create is explicitly supplied.

Safety posture:
- Does not call EDXEIX.
- Does not issue AADE receipts.
- Does not create submission_jobs.
- Does not create submission_attempts.
- Does not expose session cookies, CSRF tokens, API keys, or private config values.

This preserves the source split: EDXEIX uses pre-ride Bolt email only, while AADE invoice issuing remains limited to the Bolt API pickup timestamp worker.
```
