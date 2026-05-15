# V3.2.10 — Maildir Fixture Writer Preflight Audit

## Purpose

Adds a read-only Maildir fixture writer preflight audit for the V3 real future candidate capture readiness toolchain.

The audit checks whether the target Maildir `new` and `tmp` paths exist, are directories, are readable, and are writable by the current process. It also confirms the real-format fixture preview remains future-timestamped and free of demo/test/canary body tokens.

## Safety posture

- Production Pre-Ride Tool untouched.
- No SQL changes.
- No Maildir writes.
- No write probe.
- No DB writes.
- No queue mutations.
- No Bolt calls.
- No EDXEIX calls.
- No AADE calls.
- No cron jobs.
- No notifications.
- No executable Maildir writer added.
- Live EDXEIX submission remains disabled.

## CLI

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --maildir-writer-preflight-json
```

Aliases:

```bash
--fixture-writer-preflight-json
--maildir-path-audit-json
```

## Expected result

```text
snapshot_mode=read_only_maildir_fixture_writer_preflight_audit
preflight_only=true
executable_mail_writer_added=false
maildir_write_allowed_now=false
maildir_write_made=false
write_probe_performed=false
future_patch_required_for_maildir_write=true
live_submit_allowed_now=false
live_submit_blocked_by_design=true
```
