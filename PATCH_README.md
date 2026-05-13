# gov.cabnet.app patch — V3 live-submit operator approval gate

## What changed

Adds a V3-only operator approval ledger and ops page for rows that reach `live_submit_ready`.

## Files included

```text
gov.cabnet.app_sql/2026_05_13_pre_ride_email_v3_live_submit_approvals.sql
gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_approval_audit.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-approval.php
docs/PRE_RIDE_EMAIL_TOOL_V3_LIVE_APPROVAL_GATE.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_sql/2026_05_13_pre_ride_email_v3_live_submit_approvals.sql
→ /home/cabnet/gov.cabnet.app_sql/2026_05_13_pre_ride_email_v3_live_submit_approvals.sql

gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_approval_audit.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_approval_audit.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-approval.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-approval.php
```

## SQL

```bash
mysql cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_05_13_pre_ride_email_v3_live_submit_approvals.sql
```

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_approval_audit.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-approval.php
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_approval_audit.php --limit=20
```

Open:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-live-approval.php
```

## Safety

- No EDXEIX calls.
- No AADE calls.
- No production submission table writes.
- No production tool changes.
- Approval writes only to the V3 approval ledger and V3 events.
