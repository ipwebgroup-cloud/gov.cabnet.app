# Patch README — EDXEIX Submit Readiness Probe v2.6

## Summary
Adds a read-only submit-preparation page at `/ops/edxeix-submit-readiness.php`.

## Files
- `public_html/gov.cabnet.app/ops/edxeix-submit-readiness.php`
- `docs/EDXEIX_SUBMIT_READINESS_V2_6.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload path
Upload `public_html/gov.cabnet.app/ops/edxeix-submit-readiness.php` to `/home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-readiness.php`.

## SQL
None.

## Safety
No Bolt call. No EDXEIX POST. No job staging. No mapping update. No database write. Live submission remains disabled.
