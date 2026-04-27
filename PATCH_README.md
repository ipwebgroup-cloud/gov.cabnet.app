# Patch README — EDXEIX Session / Form GET Probe v2.7

## Summary

Adds a read-only EDXEIX Session / Form GET Probe page.

## Files

- `public_html/gov.cabnet.app/ops/edxeix-session-probe.php`
- `docs/EDXEIX_SESSION_FORM_GET_PROBE_V2_7.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload path

Upload:

`public_html/gov.cabnet.app/ops/edxeix-session-probe.php`

to:

`/home/cabnet/public_html/gov.cabnet.app/ops/edxeix-session-probe.php`

## SQL

None.

## Safety

Default page load is local metadata only. `probe=1` performs GET only. It does not POST to EDXEIX, does not call Bolt, does not stage jobs, does not update mappings, does not write database rows, and does not expose cookies/tokens/raw HTML.
