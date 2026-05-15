# gov.cabnet.app — Bolt → EDXEIX Bridge Handoff

Current patch: v3.2.11 — Maildir Fixture Writer Authorization Packet
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
- No Maildir file is created by v3.2.11.
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

## v3.2.11 changes

- Adds `--maildir-writer-authorization-json`.
- Adds aliases `--fixture-writer-authorization-json` and `--one-shot-maildir-authorization-json`.
- Adds an Ops page section named “Maildir Fixture Writer Authorization Packet”.
- Consolidates fixture preview, Maildir writer design, Maildir preflight, authorization gates, future runbook, non-goals, and safety posture.
- Confirms a future writer still requires an explicit Andreas request and a separate patch.

## Verification command

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --maildir-writer-authorization-json
```

Expected safety posture: `authorization_packet_only=true`, `executable_mail_writer_added=false`, `maildir_write_allowed_now=false`, `maildir_write_made=false`, `future_patch_required_for_maildir_write=true`, and live submit blocked.
