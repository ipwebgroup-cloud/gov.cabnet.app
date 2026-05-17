# gov.cabnet.app Handoff — 2026-05-17 EDXEIX Diagnostic v3.2.21

Current posture after v3.2.20 server validation and v3.2.21 patch preparation:

- Stack remains plain PHP + mysqli/MariaDB with cPanel/manual upload workflow.
- EDXEIX live submission remains blocked unless Andreas explicitly authorizes a supervised one-shot diagnostic for a real eligible future Bolt trip.
- Queue 2398 one-shot automatic live-submit test is closed: one supervised POST attempt returned HTTP 302, but no remote/reference ID was captured and no saved EDXEIX contract was confirmed.
- v3.2.20 installed successfully and syntax checks passed on the server.
- v3.2.20 dry-run returned `DRY_RUN_DIAGNOSTIC_ONLY`, no EDXEIX transport, session file ready, and correctly blocked booking ID 2 as finished/past/test-like.
- v3.2.20 also showed current config effectively reporting `started_at_not_0_min_future`; v3.2.21 adds a diagnostic +30 minute guard floor and candidate discovery so stale/past rows are not selected by default.
- `pre_ride_email_v3_single_row_live_submit_one_shot.php` remains retired after the queue 2398 test and must not be reused without a new diagnostic patch.
- AADE/myDATA receipt issuing is live production and duplicate-protected.
- KOUNTER mapping is present in the DB audit: driver Ioannis Kounter -> EDXEIX driver 7329 / lessor 2183; vehicles XZA3232 -> 3160 / lessor 2183 and XRM5435 -> 13191 / lessor 2183.
- Mercedes-Benz Sprinter / EMT8640 is permanently Admin Excluded: no invoicing, no AADE/myDATA receipt/invoice, no driver email, no voucher/receipt-copy email, and no automated EDXEIX processing.

Next safest major step:

1. Upload v3.2.21 patch files.
2. Run PHP syntax checks.
3. Run candidate discovery:
   `php /home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php --json --list-candidates=1 --limit=75`
4. Confirm the diagnostic reports either `NO_SAFE_CANDIDATE_AVAILABLE` or a real future Bolt candidate only.
5. If current config guard is below +30, keep transport blocked until server-only config is corrected.
6. Do not run transport until a real future booking is explicitly selected and Andreas approves the one-shot test.
