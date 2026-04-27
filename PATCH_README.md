# Patch README — EDXEIX Disabled Live Submit Harness v3.4

## Summary

Adds a disabled live-submit harness / approval runbook page.

## Files

- `public_html/gov.cabnet.app/ops/edxeix-disabled-live-submit-harness.php`
- `docs/EDXEIX_DISABLED_LIVE_SUBMIT_HARNESS_V3_4.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload path

Upload:

`public_html/gov.cabnet.app/ops/edxeix-disabled-live-submit-harness.php`

to:

`/home/cabnet/public_html/gov.cabnet.app/ops/edxeix-disabled-live-submit-harness.php`

## SQL

None.

## Safety

No Bolt call. No EDXEIX call. No POST. Reads local config/session metadata and recent normalized bookings only. No database writes. No file writes. No job staging. No mapping update. No secret or passenger payload values printed. No live submission.
