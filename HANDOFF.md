# gov.cabnet.app Handoff — 2026-05-17 ASAP Automation Diagnostic Track

Current project posture after the queue 2398 closed test and the v3.2.20 diagnostic preparation:

- Stack remains plain PHP + mysqli/MariaDB with cPanel/manual upload workflow.
- EDXEIX live submission remains blocked by default.
- Queue 2398 one-shot automatic live-submit test is closed: one supervised POST attempt returned HTTP 302, but no remote/reference ID was captured and no saved EDXEIX contract was confirmed.
- HTTP 302 must not be treated as success by itself.
- `pre_ride_email_v3_single_row_live_submit_one_shot.php` is retired after the queue 2398 test and must not be reused without a new diagnostic patch.
- Database audit state from 2026-05-17 showed `submission_jobs = 0`, `submission_attempts = 0`, all `normalized_bookings.never_submit_live = 1`, all `normalized_bookings.edxeix_ready = 0`, and all V3 pre-ride queue rows blocked.
- AADE/myDATA receipt issuing is live production and duplicate-protected.
- KOUNTER mapping is present in the DB audit: driver Ioannis Kounter -> EDXEIX driver 7329 / lessor 2183; vehicles XZA3232 -> 3160 / lessor 2183 and XRM5435 -> 13191 / lessor 2183.
- Mercedes-Benz Sprinter / EMT8640 is permanently Admin Excluded: no invoicing, no AADE/myDATA receipt/invoice, no driver email, no voucher/receipt-copy email, and no automated EDXEIX processing.
- Handoff package tooling was privacy-hardened on 2026-05-17 to exclude runtime locks, receipt attachment PDFs, cPanel backup/broken files, and accidental root database exports from Git-safe workflows.

## v3.2.20 added direction

The next automation step is diagnostic, not unattended submit.

New patch package adds:

- `gov.cabnet.app_app/lib/edxeix_submit_diagnostic_lib.php`
- `gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php`
- `public_html/gov.cabnet.app/ops/edxeix-submit-diagnostic.php`
- `docs/EDXEIX_SUBMIT_DIAGNOSTIC_v3.2.20.md`
- updated `SCOPE.md`, `README.md`, `PROJECT_FILE_MANIFEST.md`, `PATCH_README.md`, and this handoff/continue prompt.

The web diagnostic page is dry-run/read-only only. It does not POST to EDXEIX.

The CLI transport mode remains blocked unless server-only live gates are explicitly enabled for one selected real future Bolt booking and the exact confirmation phrase is provided.

## Next safest major step

1. Upload the v3.2.20 diagnostic patch.
2. Run PHP syntax checks.
3. Open `/ops/edxeix-submit-diagnostic.php` and confirm it loads dry-run.
4. Run the CLI dry-run diagnostic against the current/latest candidate.
5. Wait for a real eligible future Bolt trip.
6. If Andreas explicitly authorizes it, enable a server-only one-shot diagnostic for that single booking.
7. Run the CLI transport trace once.
8. Use the classification to decide the next patch:
   - login/session -> improve session capture;
   - CSRF/session rejection -> capture correct token pair;
   - validation error -> adjust payload field names/required fields;
   - success candidate -> run read-only verifier/list proof;
   - unknown -> add browser-assisted proof capture.

Do not enable unattended live submit or cron workers yet.
