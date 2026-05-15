# V3 EDXEIX Payload Preview / Dry-Run Preflight — v3.2.3

Date: 2026-05-15

## Purpose

v3.2.3 adds a read-only dry-run preview of the normalized fields that would feed an EDXEIX pre-ride contract submission for the current complete future candidate.

This is a closed-gate operator review tool only. It does not submit to EDXEIX.

## CLI

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --edxeix-preview-json
```

Aliases:

```bash
--payload-preview-json
--dry-run-preflight-json
```

## Safety posture

- No Bolt calls.
- No EDXEIX calls.
- No AADE calls.
- No DB writes.
- No queue mutations.
- No SQL changes.
- No cron jobs.
- No notifications.
- No live-submit enablement.
- Production Pre-Ride Tool remains untouched.

## Output

The preview includes:

- queue candidate identity
- EDXEIX mapping IDs
- pickup/end times
- pickup/drop-off route
- price amount and currency
- masked passenger preview fields
- driver/vehicle context
- dry-run preflight checks
- preflight blocks, if any
- preview hash

Raw payloads, parsed JSON, message headers, hashes, full mailbox paths, credentials, and unmasked customer phone numbers are not included in the preview.

## Interpretation

A `preflight_outcome` of:

```text
dry_run_preview_passed_live_submit_still_blocked
```

means the candidate is suitable for closed-gate preview only. It does not mean live submission is enabled.

Live EDXEIX submission remains blocked until Andreas explicitly requests a separate live-submit update and all gates pass.
