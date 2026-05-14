# V3 Pre-live Proof Bundle Hotfix

Version: `v3.0.72-v3-proof-bundle-runner-and-ops-hotfix`

## Purpose

This patch fixes two safe operational issues found during the first V3 pre-live proof bundle test:

1. The Ops page hard-failed with `Ops auth include missing.` on the current cPanel layout.
2. The proof bundle CLI reported child command `exit_code=-1` even when JSON was captured and decoded successfully.

## Safety

This patch remains V3-only and does not perform live work.

It does not:

- call Bolt
- call EDXEIX
- call AADE
- write database rows
- change queue status
- write production submission tables
- touch V0
- enable live submit
- change SQL
- change cron schedules

## Runtime changes

### Ops page

The Ops page now matches the newer V3 Ops pattern:

- loads `_ops-auth.php`, `ops-auth.php`, or `_auth.php` when one exists;
- calls `gov_ops_require_auth()` or `ops_require_auth()` when available;
- does not hard-fail on older/manual cPanel layouts where auth is handled by the surrounding Ops login/session layer.

### CLI runner

The bundle exporter now preserves the child process exit code when `proc_get_status()` has already observed it before `proc_close()`. This prevents valid child runs from being reported as `exit_code=-1`.

The bundle safety calculation now treats closed live-submit gate blocks as expected proof state, not a command-runner failure, when the child JSON is valid and the safety markers show no external calls or writes.

## Expected result

The proof bundle should be able to show:

- storage check seen and OK
- adapter simulation seen
- payload consistency seen and OK
- DB payload hash matches latest package artifact hash
- adapter hash matches expected payload hash
- adapter live capable = no
- adapter submitted = no
- no Bolt call
- no EDXEIX call
- no AADE call
- no DB writes
- V0 untouched

Master gate blocks may still appear in the observed final blocks. That is expected because live submit remains disabled.
