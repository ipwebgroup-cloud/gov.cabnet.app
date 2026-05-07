# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

Current phase: v4.8 Credential Rotation + Final Dry-run Handoff.

## Production-safe status

- Mail intake cron: ON
- Auto dry-run evidence cron: ON
- Bolt driver directory sync cron: ON
- Driver pre-ride copy: validated with real Bolt email
- Driver recipient resolution: driver identity/name/identifier, not vehicle plate
- Driver directory coverage: validated at 100% during testing
- Launch readiness verdict before v4.8: HARDENING_READY_DRY_RUN
- `app.dry_run = true`
- `edxeix.live_submit_enabled = false`
- `submission_jobs = 0`
- `submission_attempts = 0`

## v4.8 additions

- `/ops/credential-rotation.php` read-only credential rotation gate.
- `/ops/launch-readiness.php` updated to display credential rotation acknowledgement.
- `cli/mark_credential_rotation.php` creates a no-secret marker after manual rotation.

Marker path:

```text
/home/cabnet/gov.cabnet.app_app/storage/security/credential_rotation_ack.json
```

Acknowledgement command, only after real rotation:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/mark_credential_rotation.php --ops-key --bolt --edxeix --mailbox --by=Andreas
```

## Required before any live-submit phase

Rotate:

1. INTERNAL_API_KEY / ops key
2. Bolt API credentials or tokens if exposed
3. EDXEIX credentials/session
4. Mailbox/forwarding credentials if exposed

Then verify launch readiness still shows dry-run safe posture and zero submission jobs/attempts.

## Do not do yet

Do not enable live EDXEIX submission. Do not create a live-submit worker until Andreas explicitly approves a v5.0 live-submit design.
