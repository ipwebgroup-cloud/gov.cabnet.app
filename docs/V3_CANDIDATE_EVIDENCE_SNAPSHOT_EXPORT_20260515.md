# V3 Candidate Evidence Snapshot Export — v3.2.2

Date: 2026-05-15

## Purpose

v3.2.2 adds a sanitized candidate evidence snapshot for the existing read-only V3 real future candidate capture readiness layer.

The snapshot is designed for closed-gate operator review after a future possible-real pre-ride email has been ingested into the V3 queue.

## Safety posture

- Production Pre-Ride Tool untouched.
- Live EDXEIX submission remains disabled.
- No Bolt calls.
- No EDXEIX calls.
- No AADE calls.
- No DB writes.
- No queue mutations.
- No filesystem writes.
- No SQL changes.
- No cron jobs.
- No notifications.

## New CLI mode

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --evidence-json
```

Alias:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --candidate-evidence-json
```

## Sanitization

The evidence snapshot intentionally excludes:

- payload_json
- parsed_fields_json
- raw_email_preview
- source_hash
- email_hash
- full source mailbox path
- unmasked customer phone
- raw message headers
- secrets or credentials

## Operator value

When a candidate is visible, the snapshot shows:

- queue ID
- pickup/end time and minutes remaining
- readiness/completeness/missing fields
- parser/mapping/future flags
- closed-gate review qualification
- masked customer phone presence
- driver/vehicle/lessor/starting point mapping IDs
- pickup/drop-off and price
- live gate safety confirmation

This is still review-only. It does not submit to EDXEIX.
