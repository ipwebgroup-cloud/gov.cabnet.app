# HANDOFF — gov.cabnet.app Bolt Mail Bridge v4.3

Current state:

- Gmail/Bolt pre-ride emails forward to `bolt-bridge@gov.cabnet.app`.
- Maildir importer runs every minute.
- `bolt_mail_intake` parses and classifies emails.
- Future guard is configured at 2 minutes.
- Stale open candidates are expired automatically.
- Synthetic test harness is available.
- Mail Preflight can create local `source='bolt_mail'` normalized bookings manually.
- v4.2 dry-run evidence table/page can record payload evidence without EDXEIX calls.
- v4.3 adds an optional auto worker to create local preflight bookings and dry-run evidence from valid active `future_candidate` rows.

Safety:

- `app.dry_run=true`.
- `edxeix.live_submit_enabled=false`.
- v4.3 refuses to run if dry-run is off or live submit is enabled.
- No `submission_jobs` or `submission_attempts` are created by mail automation.
- No live EDXEIX POST exists in this path.

New files:

- `gov.cabnet.app_app/src/Mail/BoltMailAutoDryRunService.php`
- `gov.cabnet.app_app/cli/auto_bolt_mail_dry_run.php`
- `public_html/gov.cabnet.app/ops/mail-auto-dry-run.php`

Next safe step:

1. Upload v4.3.
2. Syntax check files.
3. Run CLI with `--preview-only --json`.
4. Use Synthetic Test to create a future candidate.
5. Run auto dry-run worker.
6. Verify local booking + dry-run evidence exist and `submission_jobs` remains empty.
