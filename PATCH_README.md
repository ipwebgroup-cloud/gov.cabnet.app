# Patch README — EDXEIX Target Matrix v2.9

## Summary

Adds a read-only EDXEIX Target URL Probe Matrix page.

## Files

- `public_html/gov.cabnet.app/ops/edxeix-target-matrix.php`
- `docs/EDXEIX_TARGET_MATRIX_V2_9.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload path

Upload:

`public_html/gov.cabnet.app/ops/edxeix-target-matrix.php`

to:

`/home/cabnet/public_html/gov.cabnet.app/ops/edxeix-target-matrix.php`

## SQL

None.

## Safety

Default load is local metadata only. `probe=1&follow=1` performs GET-only requests. No EDXEIX POST, no Bolt call, no job staging, no mapping updates, no database writes, no file writes, no secrets/raw HTML printed, and no live submission.
