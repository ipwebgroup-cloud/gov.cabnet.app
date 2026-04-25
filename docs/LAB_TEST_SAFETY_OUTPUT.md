# LAB/Test Safety Output Patch

This patch makes the dry-run/local LAB workflow clearer in JSON output.

## Why this exists

The first dry-run harness correctly blocked LAB rows from normal staging, but some endpoints used the field `submission_safe` to mean “technically valid.” That could be confusing because a LAB row can have valid mapping and future timing while still being forbidden for live EDXEIX submission.

This patch separates those meanings.

## New output fields

- `technical_payload_valid`: driver mapping, vehicle mapping, future guard, and non-terminal status passed.
- `dry_run_allowed`: local dry-run validation is allowed in the current endpoint/mode.
- `dry_run_stage_allowed`: local dry-run queue staging is allowed in the staging endpoint.
- `live_submission_allowed`: true only for non-LAB, non-test, non-never-live rows that pass all technical checks.
- `technical_blockers`: blockers that make the payload invalid.
- `stage_blockers`: blockers for local dry-run staging.
- `dry_run_blockers`: blockers for the dry-run worker.
- `live_blockers`: blockers that prevent live submission.

`submission_safe` is now aligned with `live_submission_allowed`.

## Expected LAB row behavior

A valid LAB row should show:

```json
{
  "technical_payload_valid": true,
  "dry_run_allowed": true,
  "live_submission_allowed": false,
  "submission_safe": false,
  "live_blockers": ["lab_row_blocked", "never_submit_live"]
}
```

## Safety

This patch still does not call EDXEIX. The worker remains dry-run only and live submission remains intentionally unimplemented.
