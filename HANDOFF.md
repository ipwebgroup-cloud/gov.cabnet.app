# gov.cabnet.app Handoff — 2026-05-17 Safe Blocked / Audit Posture

Current project posture after inspecting `gov_cabnet_git_safe_with_db_audit_20260517_114052.zip`:

- Stack remains plain PHP + mysqli/MariaDB with cPanel/manual upload workflow.
- EDXEIX live submission must remain blocked unless Andreas explicitly authorizes a new supervised live-submit diagnostic.
- Queue 2398 one-shot automatic live-submit test is closed: one supervised POST attempt returned HTTP 302, but no remote/reference ID was captured and no saved EDXEIX contract was confirmed.
- `pre_ride_email_v3_single_row_live_submit_one_shot.php` is retired after the queue 2398 test and must not be reused without a new diagnostic patch.
- Database audit state from 2026-05-17 shows `submission_jobs = 0`, `submission_attempts = 0`, all `normalized_bookings.never_submit_live = 1`, all `normalized_bookings.edxeix_ready = 0`, and all V3 pre-ride queue rows blocked.
- AADE/myDATA receipt issuing is live production and duplicate-protected; latest audit package showed 96 issued receipt attempts and continued production activity.
- KOUNTER mapping is present in the DB audit: driver Ioannis Kounter -> EDXEIX driver 7329 / lessor 2183; vehicles XZA3232 -> 3160 / lessor 2183 and XRM5435 -> 13191 / lessor 2183.
- Mercedes-Benz Sprinter / EMT8640 is permanently Admin Excluded: no invoicing, no AADE/myDATA receipt/invoice, no driver email, no voucher/receipt-copy email, and no automated EDXEIX processing.
- Handoff package tooling was privacy-hardened on 2026-05-17 to exclude runtime locks, receipt attachment PDFs, cPanel backup/broken files, and accidental root database exports from Git-safe workflows.

Next safest major step:

1. Upload the privacy hardening patch.
2. Run PHP syntax checks for the changed PHP files.
3. Build one DB-free safe handoff package and validate it.
4. Confirm the generated DB-free package contains no `DATABASE_EXPORT.sql`, no receipt PDFs, no lock files, and no broken/backup files.
5. Commit only the patch files after production verification.
