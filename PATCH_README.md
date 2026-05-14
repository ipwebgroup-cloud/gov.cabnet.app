# Patch README — v3.0.65 V3 Pre-Live Switchboard Web Direct DB Fix

## What changed

Updates only the Ops web page:

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-pre-live-switchboard.php
```

The page no longer depends on `shell_exec`, `exec`, or any local command runner. It reads the same V3 state directly from the database/config in read-only mode.

## Upload path

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-pre-live-switchboard.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-pre-live-switchboard.php
```

## SQL

No SQL required.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-pre-live-switchboard.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_switchboard.php --json"
```

Then open:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-pre-live-switchboard.php
```

## Expected result

The page loads and shows V3 state without command-runner errors.

Live submit remains blocked.

## Safety

No V0 changes. No EDXEIX call. No AADE call. No DB writes. No queue mutation. No production submission tables. No cron changes.
