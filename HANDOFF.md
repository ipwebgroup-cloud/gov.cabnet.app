# gov.cabnet.app — Handoff through v3.2.13

Current verified direction: V3 observation and Maildir fixture readiness toolchain only. Live EDXEIX submission remains disabled. The production Pre-Ride Tool remains untouched.

Latest patch: v3.2.13 — Maildir Fixture Writer Go/No-Go CLI Dispatch Fix.

Safety posture:
- No live EDXEIX submit enabled.
- No executable Maildir writer added.
- No Maildir writes.
- No write probe.
- No DB writes or queue mutations.
- No Bolt / EDXEIX / AADE calls.

Important verification command:
```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --maildir-writer-go-no-go-json
```

Expected: JSON output with `snapshot_mode=read_only_maildir_fixture_writer_go_no_go_snapshot`.
