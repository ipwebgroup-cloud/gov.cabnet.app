# gov.cabnet.app HANDOFF — v3.0.97 Legacy Stats Source Audit Navigation

The project remains in safe closed-gate posture. Live EDXEIX submission is disabled. Production Pre-Ride Tool is untouched.

Latest change: v3.0.97 adds the read-only Legacy Stats Source Audit page to the Developer Archive navigation. This is a navigation-only update in `/ops/_shell.php`.

No routes were moved or deleted. No redirects were added. No SQL changes were made.

Verification target:

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
curl -I --max-time 10 https://gov.cabnet.app/ops/legacy-public-utility-stats-source-audit.php
grep -n "v3.0.97\|Legacy Stats Source Audit" /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```
