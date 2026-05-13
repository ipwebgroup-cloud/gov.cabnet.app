# V3 Live-Submit Operator Approval Gate

Adds a V3-only approval ledger for rows that reach `live_submit_ready`.

## Safety

- Does not call EDXEIX.
- Does not call AADE.
- Does not write to production `submission_jobs` or `submission_attempts`.
- Does not modify the production pre-ride email tool.
- Approval writes only to `pre_ride_email_v3_live_submit_approvals` and V3 queue events.

## Files

- `gov.cabnet.app_sql/2026_05_13_pre_ride_email_v3_live_submit_approvals.sql`
- `gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_approval_audit.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-approval.php`

## Required phrase

`APPROVE V3 LIVE SUBMIT HANDOFF`

## Purpose

This creates the human approval checkpoint that a future live-submit worker can require after a row has passed all previous gates.
