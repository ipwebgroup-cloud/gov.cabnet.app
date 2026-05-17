# gov.cabnet.app Handoff — 2026-05-17 v3.2.22 ASAP Automation Track

Current project posture after v3.2.21 server validation and v3.2.22 patch generation:

- Stack remains plain PHP + mysqli/MariaDB with cPanel/manual upload workflow.
- Production V0 remains unaffected.
- EDXEIX live submission remains blocked unless Andreas explicitly authorizes a supervised one-shot live-submit diagnostic for a real eligible future candidate.
- v3.2.21 diagnostic validation passed on server:
  - PHP syntax checks passed.
  - `transport_performed = false`.
  - EDXEIX session file exists, cookie present, CSRF present, no placeholders.
  - `future_start_guard_minutes` is now 30 in both server config files.
  - `configured_future_guard_minutes = 30`, `effective_future_guard_minutes = 30`, `future_guard_floor_applied = false`.
  - No safe candidate exists in the latest 75 normalized booking rows.
- Queue 2398 one-shot automatic live-submit test remains closed: HTTP 302 was returned, but no remote/reference ID was captured and no saved EDXEIX contract was confirmed.
- Existing `bolt_mail` receipt-only rows remain blocked from EDXEIX automation.
- AADE/myDATA receipt issuing remains live production and duplicate-protected.
- Mercedes-Benz Sprinter / EMT8640 remains permanently Admin Excluded: no invoicing, no AADE/myDATA receipt/invoice, no driver email, no voucher/receipt-copy email, and no automated EDXEIX processing.

v3.2.22 patch purpose:

- Add a separate `bolt_pre_ride_email` future candidate path.
- Parse pre-ride email data into a sanitized EDXEIX candidate preview.
- Apply +30 minute future guard, mapping checks, and Admin Excluded vehicle blocks.
- Optionally capture sanitized candidate metadata into an additive `edxeix_pre_ride_candidates` table.
- Keep all behavior dry-run/read-only unless `--write=1` is explicitly used for metadata capture.
- Do not submit to EDXEIX, call AADE, create queue jobs, or mutate `normalized_bookings`.

Next safest major step:

1. Upload v3.2.22 patch files.
2. Run PHP syntax checks.
3. Optionally run the additive SQL migration for `edxeix_pre_ride_candidates`.
4. Run dry-run pre-ride candidate diagnostics with latest Maildir email.
5. When a real future pre-ride email exists and classifies as `PRE_RIDE_READY_CANDIDATE`, capture sanitized metadata with `--write=1` only if approved.
6. Prepare the next supervised one-shot readiness patch only after a ready future pre-ride candidate exists.
