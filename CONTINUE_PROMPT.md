# Continue Prompt — gov.cabnet.app V3 Automation

Continue assisting Andreas with the gov.cabnet.app Bolt → EDXEIX bridge.

Current focus: V3 pre-live proof bundle export.

Important rules:

- Plain PHP/mysqli/cPanel only.
- Do not touch V0 production or dependencies.
- Do not enable live EDXEIX submission unless Andreas explicitly asks for a live-submit update.
- Live submit remains blocked by master gate/config/adapter controls.
- Historical, cancelled, expired, invalid, or past trips must never be submitted.
- Never expose or request real credentials.
- Patch zips must mirror repository/live folder structure directly, no wrapper folder.

Current patch:

```text
v3.0.71-v3-pre-live-proof-bundle-export
```

Verification commands:

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-pre-live-proof-bundle-export.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php"
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php --json"
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php --write"
```

Expected: bundle safe yes, adapter not live-capable, submitted false, no external calls.
