# Patch README — v3.0.53-v3-operator-approval-visibility

## Summary

Adds a V3-only read-only operator approval visibility page.

## Files included

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-operator-approvals.php
docs/V3_OPERATOR_APPROVAL_VISIBILITY.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload path

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-operator-approvals.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-operator-approvals.php
```

Docs are intended for the local GitHub Desktop repo.

## SQL

No SQL required.

## Verification commands

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-operator-approvals.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php"

tail -n 120 /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_fast_pipeline_pulse.log | egrep "cron start|ERROR|Pulse summary|finish exit_code" || true
```

## Verification URL

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-operator-approvals.php
```

## Expected result

The page loads and shows:

- approval table exists/missing
- approval records if present
- queue rows and approval linkage
- master gate state
- gate block reasons

## Safety

No Bolt call, no EDXEIX call, no AADE call, no DB writes, no production submission tables, no V0 changes, no SQL changes, and no live-submit enabling.

## Commit title

```text
Add V3 operator approval visibility
```

## Commit description

```text
Adds a V3-only read-only operator approval visibility page for inspecting approval table state, latest approval records, queue rows, and closed master gate blocks.

This prepares the operator approval layer for the closed-gate live adapter preparation phase.

No V0 files, live-submit enabling, EDXEIX calls, AADE behavior, queue status changes, production submission tables, cron schedules, or SQL schema are changed.
```
