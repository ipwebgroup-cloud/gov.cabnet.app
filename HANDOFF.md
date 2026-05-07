# HANDOFF — gov.cabnet.app Bolt Mail Bridge v4.4

Current state:

- Gmail/Bolt pre-ride emails forward to `bolt-bridge@gov.cabnet.app`.
- Maildir importer runs every minute.
- Auto dry-run evidence worker runs every minute.
- `bolt_mail_intake` parses and classifies emails.
- Future guard is configured at 2 minutes.
- Stale open candidates expire automatically.
- Synthetic test harness is available.
- Manual Mail Preflight can create local `source='bolt_mail'` normalized bookings.
- Auto dry-run can create local `source='bolt_mail'` bookings and `bolt_mail_dry_run_evidence` rows for valid active future candidates.
- v4.4 improves dashboard monitoring, evidence detail links, raw preflight guard display, and synthetic-only cleanup tools.

Safety:

- `app.dry_run=true`.
- `edxeix.live_submit_enabled=false`.
- No live EDXEIX POST exists in the mail automation path.
- v4.4 does not create `submission_jobs`.
- v4.4 does not create `submission_attempts`.
- v4.4 does not enable live submit.
- Synthetic cleanup is restricted to `CABNET TEST` / synthetic-marker rows and requires typing `DELETE_SYNTHETIC_ONLY`.

Changed files in v4.4:

- `public_html/gov.cabnet.app/ops/mail-status.php`
- `public_html/gov.cabnet.app/ops/mail-auto-dry-run.php`
- `public_html/gov.cabnet.app/ops/mail-dry-run-evidence.php`
- `public_html/gov.cabnet.app/bolt_edxeix_preflight.php`
- `gov.cabnet.app_app/src/Mail/BoltMailDryRunEvidenceService.php`
- `gov.cabnet.app_app/lib/bolt_sync_lib.php`
- `docs/BOLT_MAIL_PRODUCTION_MONITOR_V4_4.md`

Verification after upload:

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDryRunEvidenceService.php
php -l /home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mail-status.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mail-dry-run-evidence.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mail-auto-dry-run.php
php -l /home/cabnet/public_html/gov.cabnet.app/bolt_edxeix_preflight.php
```

Next safest step:

1. Upload v4.4.
2. Verify syntax and config posture.
3. Monitor the next real future Bolt Ride details email.
4. Confirm import cron imports it within 1 minute.
5. Confirm auto dry-run creates local booking + dry-run evidence only.
6. Confirm `submission_jobs` and `submission_attempts` remain zero/unchanged.
7. Do not enable live EDXEIX submission until Andreas explicitly requests a live-submit patch.
