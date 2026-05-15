# V3 Real-Mail Expiry Audit Alignment — 2026-05-15

## Purpose

This milestone adds a read-only alignment update to the V3 Real-Mail Expiry Reason Audit.

The queue health audit counts all non-canary possible-real rows. The expiry reason audit previously highlighted only possible-real rows classified as `expired_by_future_safety_guard`. This caused a harmless count difference when a possible-real row was blocked for another reason, such as a historical mapping correction.

## Safety posture

- No Bolt call.
- No EDXEIX call.
- No AADE call.
- No database writes.
- No queue mutations.
- No route moves/deletes/redirects.
- Production Pre-Ride Tool remains untouched.
- Live EDXEIX submission remains disabled.

## Added counters

- `possible_real_mail_rows`
- `canary_rows`
- `possible_real_mail_non_expired_guard_rows`
- `possible_real_mail_mapping_correction_rows`
- `possible_real_mail_other_blocked_rows`
- `queue_health_vs_expiry_count_mismatch_explained`
- `queue_health_vs_expiry_count_mismatch_note`
- `classification_counts`

## Expected interpretation

If queue health reports `possible_real=12` and expiry audit reports `possible_real_expired=11`, the difference should be explained by `possible_real_mail_non_expired_guard_rows=1`, usually the historical mapping-correction row.

No submission is recommended by this audit.
