# Patch README — EDXEIX Redirect-Follow GET Probe v2.8

## Summary

Adds a read-only EDXEIX Redirect-Follow GET Probe page.

## Files

- `public_html/gov.cabnet.app/ops/edxeix-redirect-probe.php`
- `docs/EDXEIX_REDIRECT_GET_PROBE_V2_8.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload path

Upload:

`public_html/gov.cabnet.app/ops/edxeix-redirect-probe.php`

to:

`/home/cabnet/public_html/gov.cabnet.app/ops/edxeix-redirect-probe.php`

## SQL

None.

## Safety

Default load is local metadata only. `probe=1` and `probe=1&follow=1` perform GET only. No EDXEIX POST, no Bolt call, no job staging, no mapping updates, no database writes, no file writes, no secrets/raw HTML printed, and no live submission.
