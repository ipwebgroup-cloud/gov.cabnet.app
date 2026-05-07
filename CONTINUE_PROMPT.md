Continue the gov.cabnet.app Bolt → EDXEIX bridge project from v4.8.

Current state:

- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload.
- Domain: https://gov.cabnet.app
- Live submit remains OFF.
- Dry-run remains ON.
- Driver email copy has been validated with a real Bolt pre-ride email.
- Driver recipient matching must use driver identity/name/identifier, not vehicle plate.
- Launch readiness panel is present at `/ops/launch-readiness.php`.
- Credential rotation panel is present at `/ops/credential-rotation.php`.
- No-secret credential acknowledgement CLI is present at `/home/cabnet/gov.cabnet.app_app/cli/mark_credential_rotation.php`.

Before live-submit design:

1. Rotate ops key, Bolt credentials, EDXEIX credentials/session, and mailbox-related credentials if exposed.
2. Run:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/mark_credential_rotation.php --ops-key --bolt --edxeix --mailbox --by=Andreas
```

3. Verify:

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/credential-rotation.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/launch-readiness.php
php -l /home/cabnet/gov.cabnet.app_app/cli/mark_credential_rotation.php
```

4. Confirm:

- `app.dry_run = true`
- `edxeix.live_submit_enabled = false`
- `submission_jobs = 0`
- `submission_attempts = 0`
- crons healthy
- credential rotation gate acknowledged

Do not enable live submit unless Andreas explicitly asks for v5.0 live-submit implementation.
