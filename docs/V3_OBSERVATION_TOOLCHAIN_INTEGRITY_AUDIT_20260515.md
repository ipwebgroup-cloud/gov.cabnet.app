# V3 Observation Toolchain Integrity Audit — 2026-05-15

This package adds v3.1.12, a read-only integrity audit for the V3 real-mail observation toolchain.

## Purpose

The audit verifies that the following are present and consistent:

- V3 Real-Mail Queue Health CLI/page.
- V3 Real-Mail Expiry Reason Audit CLI/page.
- V3 Next Real-Mail Candidate Watch CLI/page.
- V3 Real-Mail Observation Overview CLI/page.
- Shared ops shell navigation for the observation overview.
- Shared shell side-note normalization after the v3.1.11 typo cleanup.
- Absence of temporary public `_shell.php.bak_v3_1_8*`, `_shell.php.bak_v3_1_10*`, and `_shell.php.bak_v3_1_11*` files.

## Safety

This audit is read-only. It does not call Bolt, EDXEIX, or AADE. It does not write to the database, mutate queues, move routes, delete routes, add redirects, or enable live submission.

## Expected clean state

- `ok=true`
- `component_files_ok=true`
- `shell_nav_ok=true`
- `shell_note_ok=true`
- `public_backup_files_found=0`
- `overview_ok=true`
- `future_active_rows=0` unless a new real future pre-ride email has arrived
- `operator_review_candidates=0` unless a new complete future possible-real row has arrived
- `live_risk=false`
- `final_blocks=[]`

Live EDXEIX submission remains disabled.
