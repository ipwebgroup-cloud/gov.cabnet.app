# Legacy Public Utility Quiet-Period Audit Stable Fields — 2026-05-15

Version: `v3.0.95-legacy-public-utility-quiet-period-stable-fields`

This patch improves the read-only legacy public utility quiet-period audit output by adding stable route-level JSON fields for operator CLI checks.

## Stable route fields

- `quiet_period_classification`
- `classification`
- `status`
- `stub_review_candidate`
- `compatibility_stub_review_candidate`
- `usage_evidence_unknown_date`
- `unknown_date`
- `recent_usage_inside_quiet_window`
- `historical_usage_outside_quiet_window`
- `last_seen_normalized`

## Safety

- No Bolt call.
- No EDXEIX call.
- No AADE call.
- No database connection.
- No filesystem writes.
- No route moves.
- No route deletions.
- No redirects.
- Production pre-ride tool remains untouched.
