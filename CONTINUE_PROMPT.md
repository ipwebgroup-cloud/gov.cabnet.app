Continue the gov.cabnet.app Bolt → EDXEIX bridge from v4.9 Final Dry-run Production Freeze.

Project constraints:
- Plain PHP, mysqli/MariaDB, cPanel/manual upload.
- Do not introduce frameworks, Composer, Node, or heavy dependencies.
- Live EDXEIX submission remains OFF unless Andreas explicitly approves a live-submit phase.
- Do not ask for or expose secrets.

Current posture:
- Mail intake cron active.
- Auto dry-run evidence cron active.
- Driver directory sync cron active.
- Driver email copy validated with real Bolt pre-ride email.
- Driver recipient resolution is by driver identity/name/identifier, not vehicle plate.
- Driver email copy format adjusted: end time = pickup + 30 minutes; price range reduced to first value.
- Launch readiness is hardening-ready dry-run.
- v4.8 credential-rotation gate is installed.
- v4.9 production-freeze gate is installed.

Important paths:
- `/home/cabnet/public_html/gov.cabnet.app/ops/launch-readiness.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/credential-rotation.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/production-freeze.php`
- `/home/cabnet/gov.cabnet.app_app/cli/mark_credential_rotation.php`
- `/home/cabnet/gov.cabnet.app_app/cli/freeze_dry_run_production.php`

Next safest steps:
1. Verify v4.9 syntax and open production-freeze page.
2. Run dry-run freeze marker if checks pass:
   `/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/freeze_dry_run_production.php --by=Andreas`
3. Rotate credentials out of band.
4. Mark credential rotation only after actual rotation:
   `/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/mark_credential_rotation.php --ops-key --bolt --edxeix --mailbox --by=Andreas`
5. Keep live submit OFF.
6. Only after explicit approval, design v5.0 live EDXEIX submit worker with strict guards and kill switch.
