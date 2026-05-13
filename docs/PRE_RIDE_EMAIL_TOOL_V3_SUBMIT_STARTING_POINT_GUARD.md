# V3 Submit Starting-Point Guard

This patch adds the operator-verified V3 starting-point options table into the submit preflight and submit dry-run worker checks.

## Purpose

A V3 test showed that a queued row may contain a starting point ID that is not available in the EDXEIX lessor form. The helper cannot select an option that EDXEIX does not offer.

This patch prevents those rows from being marked submit-dry-run-ready.

## Changed files

- `gov.cabnet.app_app/cli/pre_ride_email_v3_submit_preflight.php`
- `gov.cabnet.app_app/cli/pre_ride_email_v3_submit_dry_run_worker.php`

## Safety

- No EDXEIX calls.
- No AADE calls.
- No production `submission_jobs` writes.
- No production `submission_attempts` writes.
- Production `/ops/pre-ride-email-tool.php` is untouched.
- Preflight remains SELECT-only.
- Dry-run worker commit mode still writes only V3 queue/status/events.

## Behavior

A row is submit-ready only when its `lessor_id` and `starting_point_id` match an active row in:

```sql
pre_ride_email_v3_starting_point_options
```

Rows with a lessor that has no verified options, or a starting point not in the verified options, are blocked from submit dry-run readiness.
