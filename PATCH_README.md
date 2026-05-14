# v3.0.39 — V3 Storage Check and V0/V3 Boundary

## What changed

This is a V3-only operational hardening patch.

It adds a simple storage prerequisite check for the V3 pulse runner after the live test exposed a missing/unwritable lock directory:

```text
/home/cabnet/gov.cabnet.app_app/storage/locks/pre_ride_email_v3_fast_pipeline_pulse.lock
```

It also documents the V0/V3 boundary:

- V0 remains the laptop/manual production helper.
- V3 remains the PC/server-side development/test automation path.
- This patch does not touch V0 production files or dependencies.
- No software fallback decision logic is added.

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php
gov.cabnet.app_app/storage/locks/.gitkeep
public_html/gov.cabnet.app/ops/_ops-nav.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-storage-check.php
docs/OPS_SITEMAP_V3.md
docs/V0_V3_OPERATIONS_BOUNDARY.md
docs/V3_STORAGE_AND_PULSE_CHECK.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Exact upload paths

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php

gov.cabnet.app_app/storage/locks/.gitkeep
→ /home/cabnet/gov.cabnet.app_app/storage/locks/.gitkeep

public_html/gov.cabnet.app/ops/_ops-nav.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-storage-check.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-storage-check.php
```

Docs should be kept in the local GitHub Desktop repo:

```text
docs/OPS_SITEMAP_V3.md
docs/V0_V3_OPERATIONS_BOUNDARY.md
docs/V3_STORAGE_AND_PULSE_CHECK.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## SQL

No SQL required.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-storage-check.php
```

Run the read-only CLI check:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php --json
```

Optional repair mode if needed:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php --fix --owner=cabnet --group=cabnet
```

Ops URL:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-storage-check.php
```

Unauthenticated requests should redirect to Ops login if access control is active.

## Expected result

The storage check should show:

```text
storage: ok
storage/locks: ok
logs: ok
pulse cli: ok
pulse cron worker: ok
```

Live-submit posture remains unchanged:

```text
Live EDXEIX submit: disabled
V0 production/manual helper: untouched
```

## Git commit title

```text
Add V3 storage check and boundary docs
```

## Git commit description

```text
Adds a V3-only storage prerequisite checker and read-only Ops page to verify the pulse runner lock/log directories and pulse files.

Documents the operational boundary between V0 laptop/manual production helper and V3 PC/server-side automation development.

Includes a storage/locks .gitkeep so the lock directory is preserved in packages.

No V0 files, live-submit behavior, queue mutation logic, AADE calls, EDXEIX calls, production submission tables, or SQL schema are changed.
```
