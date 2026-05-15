# gov.cabnet.app — Bolt → EDXEIX Bridge Handoff

Current patch: v3.2.12 — Maildir Fixture Writer Go/No-Go Snapshot
Date: 2026-05-15

## Project identity

- Domain: https://gov.cabnet.app
- Repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow.
- Production Pre-Ride Tool remains untouched: `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php`.

## Production safety posture

- Live EDXEIX submit remains disabled.
- V3 live gate remains closed.
- No executable Maildir writer has been added.
- No Maildir file is created by v3.2.12.
- No write probe is performed.
- No DB writes, queue mutations, SQL changes, Bolt calls, EDXEIX calls, AADE calls, cron jobs, notifications, route moves, route deletes, or redirects.

## Current V3 observation toolchain

The V3 toolchain now includes:

- real future candidate capture readiness
- watch/status-line snapshot
- sanitized evidence snapshot
- EDXEIX payload preview / dry-run preflight
- expired candidate safety regression audit
- controlled live-submit readiness checklist
- single-row live-submit design draft
- controlled live-submit authorization packet
- real-format demo mail fixture preview
- controlled Maildir fixture writer design
- Maildir fixture writer preflight audit
- Maildir fixture writer authorization packet
- Maildir fixture writer go/no-go snapshot

## v3.2.12 changes

- Adds `--maildir-writer-go-no-go-json`.
- Adds aliases `--fixture-writer-go-no-go-json` and `--maildir-write-readiness-json`.
- Adds an Ops page section named “Maildir Fixture Writer Go/No-Go Snapshot”.
- Aggregates fixture preview, writer design, path preflight, authorization packet, and live gate posture.
- Confirms a future writer still requires an explicit Andreas request and a separate patch.

## Verification command

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --maildir-writer-go-no-go-json
```

Expected safety posture: `go_ready_for_future_explicit_writer_patch_only=true` only means read-only gates are ready for a separate explicit writer patch. It still must show `executable_mail_writer_added=false`, `maildir_write_allowed_now=false`, `maildir_write_made=false`, `write_probe_performed=false`, and live submit blocked.
