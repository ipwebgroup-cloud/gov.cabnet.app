# gov.cabnet.app patch — V3 live-submit gate + approval enforcement

## Files included

- `gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_worker.php`
- `gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_cron_worker.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-submit.php`
- `gov.cabnet.app_sql/2026_05_13_pre_ride_email_v3_live_submit_approvals.sql`
- `docs/PRE_RIDE_EMAIL_TOOL_V3_LIVE_SUBMIT_GATE_APPROVAL_ENFORCEMENT.md`

## Upload paths

- `gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_worker.php` → `/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_worker.php`
- `gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_cron_worker.php` → `/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_cron_worker.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-submit.php` → `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-submit.php`
- `gov.cabnet.app_sql/2026_05_13_pre_ride_email_v3_live_submit_approvals.sql` → `/home/cabnet/gov.cabnet.app_sql/2026_05_13_pre_ride_email_v3_live_submit_approvals.sql`

## SQL

```bash
mysql cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_05_13_pre_ride_email_v3_live_submit_approvals.sql
```

Safe/idempotent. Uses `CREATE TABLE IF NOT EXISTS`.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_worker.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_cron_worker.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-submit.php

php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_worker.php --limit=20
```

## Safety

- Production `pre-ride-email-tool.php` is untouched.
- No EDXEIX calls.
- No AADE calls.
- No production submission table writes.
- Live submit remains hard-disabled by code.
