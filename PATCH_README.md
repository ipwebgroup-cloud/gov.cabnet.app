# Patch README — v3.1.12 V3 Observation Toolchain Integrity Audit

## Changed files

- `gov.cabnet.app_app/cli/pre_ride_email_v3_observation_toolchain_integrity_audit.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-observation-toolchain-integrity-audit.php`
- `docs/V3_OBSERVATION_TOOLCHAIN_INTEGRITY_AUDIT_20260515.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload paths

- `/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_observation_toolchain_integrity_audit.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-observation-toolchain-integrity-audit.php`
- Optional docs mirror: `/home/cabnet/docs/V3_OBSERVATION_TOOLCHAIN_INTEGRITY_AUDIT_20260515.md`

## SQL

None.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_observation_toolchain_integrity_audit.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-observation-toolchain-integrity-audit.php

curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-observation-toolchain-integrity-audit.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_observation_toolchain_integrity_audit.php --json" \
| php -r '$j=json_decode(stream_get_contents(STDIN), true); echo "ok=".(($j["ok"]??false)?"true":"false").PHP_EOL; echo "version=".($j["version"]??"").PHP_EOL; echo "component_files_ok=".(($j["summary"]["component_files_ok"]??false)?"true":"false").PHP_EOL; echo "shell_nav_ok=".(($j["summary"]["shell_nav_ok"]??false)?"true":"false").PHP_EOL; echo "shell_note_ok=".(($j["summary"]["shell_note_ok"]??false)?"true":"false").PHP_EOL; echo "public_backups=".($j["summary"]["public_backup_files_found"]??"?").PHP_EOL; echo "overview_ok=".(($j["summary"]["overview_ok"]??false)?"true":"false").PHP_EOL; echo "live_risk=".(($j["summary"]["live_risk_detected"]??false)?"true":"false").PHP_EOL; echo "final_blocks=".json_encode($j["final_blocks"]??[], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;'
```

Expected: no syntax errors, HTTP 302 to `/ops/login.php`, `ok=true`, `live_risk=false`, and `final_blocks=[]`.
