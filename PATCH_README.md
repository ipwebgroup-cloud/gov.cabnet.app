# Patch v3.2.7 — Controlled Live-Submit Runbook / Authorization Packet

## Changed files

- `gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php`
- `public_html/gov.cabnet.app/ops/_shell.php`
- `public_html/gov.cabnet.app/ops/_ops-nav.php`
- `docs/V3_CONTROLLED_LIVE_SUBMIT_AUTHORIZATION_PACKET_20260515.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`
- `PATCH_README.md`

## SQL

None.

## Safety

No live-submit enablement, no DB writes, no queue mutations, no external calls.
