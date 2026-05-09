# v6.7.2 — EDXEIX mail preflight bridge missing-id hardening

This patch hardens the EDXEIX mail preflight bridge so an explicit numeric `--intake-id` that does not exist returns `ok: false` with `requested_intake_id_not_found`.

Safety posture is unchanged: no EDXEIX calls, no AADE calls, no submission_jobs, no submission_attempts, and no secrets printed.

## Files

- `gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php`
- `docs/EDXEIX_MAIL_PREFLIGHT_BRIDGE.md`
- `PATCH_README.md`

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php --intake-id=123 --create --json || true
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php --limit=20 --json
mysql cabnet_gov -e "SELECT COUNT(*) AS submission_jobs FROM submission_jobs; SELECT COUNT(*) AS submission_attempts FROM submission_attempts;"
```
