# V3 Automation Readiness Report

Version: v3.0.32-automation-readiness-report

This patch adds a read-only, consolidated readiness report for the isolated V3 Bolt pre-ride email automation chain.

## Purpose

The report gives one place to confirm:

- V3 queue tables exist.
- Starting-point guard options exist.
- Operator approval table exists.
- Cron logs are present and fresh.
- Queue status counts are visible.
- Live-submit master gate config is visible.
- Live submit remains hard-disabled unless explicitly opened later.

## Safety

This patch is read-only.

It does not:

- Call EDXEIX.
- Call AADE.
- Write to the database.
- Write to production `submission_jobs`.
- Write to production `submission_attempts`.
- Modify `public_html/gov.cabnet.app/ops/pre-ride-email-tool.php`.

## Files

- `gov.cabnet.app_app/src/BoltMailV3/AutomationReadinessReportV3.php`
- `gov.cabnet.app_app/cli/pre_ride_email_v3_automation_readiness.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-automation-readiness.php`

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/AutomationReadinessReportV3.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_automation_readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-automation-readiness.php

php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_automation_readiness.php
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_automation_readiness.php --json
```

Open:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-automation-readiness.php
```

## Expected current state

With the disabled live-submit config installed, expected output is:

- Config loaded: yes.
- Enabled: no.
- Mode: disabled.
- Adapter: disabled.
- Hard enable live submit: no.
- Ready for future live submit: no.
- Ready for V3 manual handoff: yes if all V3 crons are fresh.

