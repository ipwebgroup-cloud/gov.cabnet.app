# V3.2.13 — Maildir Fixture Writer Go/No-Go CLI Dispatch Fix

## Purpose

Fix the read-only CLI dispatch for the Maildir fixture writer go/no-go snapshot. In v3.2.12 the go/no-go function existed and the help text advertised `--maildir-writer-go-no-go-json`, but the CLI flag variable was not initialized in the main dispatcher, so the command could fall through to the default human summary.

## Safety

- No Maildir writes.
- No write probe.
- No executable Maildir writer added.
- No DB writes.
- No queue mutation.
- No Bolt calls.
- No EDXEIX calls.
- No AADE calls.
- Live submit remains disabled.

## Verification

Run:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --maildir-writer-go-no-go-json
```

Expected JSON fields include:

```text
snapshot_mode=read_only_maildir_fixture_writer_go_no_go_snapshot
go_no_go_outcome=go_ready_for_explicit_separate_writer_patch_only
executable_mail_writer_added=false
maildir_write_made=false
write_probe_performed=false
live_submit_blocked_by_design=true
```
