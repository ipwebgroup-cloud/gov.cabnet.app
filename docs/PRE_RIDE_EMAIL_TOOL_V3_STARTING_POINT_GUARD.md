# V3 Starting-Point Guard

Adds an isolated V3-only guard for EDXEIX starting-point IDs.

## Purpose

The V3 helper diagnostic proved that lessor `2307` did not offer starting point `6467495` on the live EDXEIX form. The verified EDXEIX form options were:

- `1455969` — `ΧΩΡΑ ΜΥΚΟΝΟΥ`
- `9700559` — `ΕΠΑΝΩ ΔΙΑΚΟΦΤΗΣ`

This patch adds a V3-only verified-options table and a guard worker that blocks active V3 queue rows if their `starting_point_id` is known-invalid for the selected lessor.

## Safety

- Production `pre-ride-email-tool.php` is untouched.
- No EDXEIX calls.
- No AADE calls.
- No production `submission_jobs` writes.
- No production `submission_attempts` writes.
- Default guard mode is SELECT-only.
- `--commit` writes only to `pre_ride_email_v3_queue` and `pre_ride_email_v3_queue_events`.

## Files

- `gov.cabnet.app_sql/2026_05_13_pre_ride_email_v3_starting_point_options.sql`
- `gov.cabnet.app_app/cli/pre_ride_email_v3_starting_point_guard.php`
- `gov.cabnet.app_app/cli/pre_ride_email_v3_starting_point_guard_cron_worker.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-starting-point-guard.php`

## Suggested cron

Run after the intake cron and before relying on submit dry-run readiness:

```cron
* * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_starting_point_guard_cron_worker.php >> /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_starting_point_guard_cron.log 2>&1
```
