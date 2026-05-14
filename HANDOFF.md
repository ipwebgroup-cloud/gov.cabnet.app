# HANDOFF — gov.cabnet.app V3 Automation

## Current checkpoint

Latest package: `v3.0.54-v3-closed-gate-adapter-diagnostics`

## Verified V3 status

- V3 forwarded-email readiness path was proven.
- Proof row reached `live_submit_ready` before expiry.
- Payload audit returned `PAYLOAD-READY`.
- Final rehearsal correctly blocked by the closed master gate.
- Historical proof dashboard preserves the proof after expiry.
- V3 local live package export works and writes JSON/TXT artifacts only.
- V3 operator approval visibility page is installed.
- V3 pulse cron is healthy.
- V3 pulse lock file is `cabnet:cabnet` with `0660` permissions.
- Live submit remains disabled.
- V0 laptop/manual helper remains untouched.

## v3.0.54 purpose

Adds read-only closed-gate diagnostics for the future live adapter path.

New CLI:

```text
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_closed_gate_adapter_diagnostics.php
```

New page:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-closed-gate-adapter-diagnostics.php
```

It checks gate, adapter wiring, selected queue row, required fields, starting point, approval state, package export state, and final live-submit block reasons.

## Critical safety rules

- Do not enable live EDXEIX submission unless Andreas explicitly asks for a live-submit gate-opening update.
- Do not touch V0 laptop/manual production helper or dependencies.
- Do not submit historical, expired, cancelled, terminal, invalid, synthetic, or past rows.
- Keep all new V3 work read-only, dry-run, package-export, diagnostic, approval-visible, or closed-gate until explicitly approved.
- Never request or expose credentials.

## Next safe step

Verify v3.0.54, then consider a closed-gate live adapter skeleton that always returns blocked while the gate is closed.
