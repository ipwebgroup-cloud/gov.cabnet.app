# Patch README — EDXEIX Form Contract Verifier v3.2

## Summary

Adds a read-only EDXEIX Form Contract Verifier page.

## Files

- `public_html/gov.cabnet.app/ops/edxeix-form-contract.php`
- `docs/EDXEIX_FORM_CONTRACT_VERIFIER_V3_2.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload path

Upload:

`public_html/gov.cabnet.app/ops/edxeix-form-contract.php`

to:

`/home/cabnet/public_html/gov.cabnet.app/ops/edxeix-form-contract.php`

## SQL

None.

## Safety

No Bolt call. No EDXEIX call. No POST. Reads local config/session metadata and recent normalized bookings only. No database writes. No file writes. No job staging. No mapping update. No secret or passenger payload values printed. No live submission.
