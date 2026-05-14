# v3.0.40 — V3 Pulse Lock Owner Hardening

## What changed

This patch hardens the V3 storage check after a real operational finding: the pulse cron was failing because the pulse lock file was owned by `root:root`, while the normal cPanel cron runs as `cabnet`.

The patch remains V3-only and read-only by default.

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-storage-check.php
docs/V3_STORAGE_AND_PULSE_CHECK.md
docs/V0_V3_OPERATIONS_BOUNDARY.md
PATCH_README.md
```

## Exact upload paths

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-storage-check.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-storage-check.php
```

Keep docs in the local GitHub Desktop repo unless intentionally publishing docs.

## SQL

No SQL required.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-storage-check.php

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php --json
```

## Ops URL

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-storage-check.php
```

## Expected result

The storage check should show the pulse lock file as present, writable, and owned by `cabnet:cabnet`.

The pulse log should show:

```text
Pulse summary: cycles_run=5 ok=5 failed=0
V3 fast pipeline pulse cron finish exit_code=0
```

## Important operator note

Do not run the V3 pulse cron worker as root. Test it as `cabnet`:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_pulse_cron_worker.php"
```

## Safety

- No V0 files touched.
- No live-submit changes.
- No EDXEIX call.
- No AADE call.
- No queue mutation logic changed.
- No SQL.

## Git commit title

```text
Harden V3 pulse lock ownership checks
```

## Git commit description

```text
Adds V3-only detection for pulse lock file owner and writability after the pulse cron was blocked by a root-owned lock file.

Updates the storage check CLI and Ops page to report the pulse lock file, expected cabnet:cabnet ownership, permissions, and remediation steps.

Documents that the V3 pulse cron worker should be manually tested as the cabnet user, not root.

No V0 production helper files, live-submit behavior, EDXEIX calls, AADE behavior, queue mutation logic, or SQL schema are changed.
```
