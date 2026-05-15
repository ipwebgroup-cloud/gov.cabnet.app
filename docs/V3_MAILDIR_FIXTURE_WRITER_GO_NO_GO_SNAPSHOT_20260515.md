# V3.2.12 — Maildir Fixture Writer Go/No-Go Snapshot

Date: 2026-05-15

## Purpose

Adds a read-only final go/no-go snapshot for the controlled Maildir fixture writer path.

This layer aggregates:

- real-format demo mail fixture preview
- controlled Maildir fixture writer design
- Maildir path preflight audit
- Maildir fixture writer authorization packet
- live gate closed posture
- no-write/no-writer safety posture

## CLI modes

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --maildir-writer-go-no-go-json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --fixture-writer-go-no-go-json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --maildir-write-readiness-json
```

## Safety posture

- No executable Maildir writer is added.
- No Maildir file is created.
- No write probe is performed.
- No DB write is made.
- No queue mutation is made.
- No Bolt, EDXEIX, or AADE call is made.
- Live EDXEIX submission remains disabled.
- A separate explicit Andreas request and separate patch are still required before any one-shot writer can exist.

## Expected output when ready

```text
go_no_go_outcome=go_ready_for_explicit_separate_writer_patch_only
go_ready_for_future_explicit_writer_patch_only=true
executable_mail_writer_added=false
maildir_write_allowed_now=false
maildir_write_made=false
write_probe_performed=false
future_patch_required_for_maildir_write=true
requires_explicit_andreas_maildir_write_request=true
live_submit_allowed_now=false
live_submit_blocked_by_design=true
```

## Operator interpretation

If the snapshot says ready, it means only this:

> The read-only gates are ready for Andreas to explicitly request a separate one-shot writer patch.

It does not mean a writer exists, is armed, or can write a mail file.
