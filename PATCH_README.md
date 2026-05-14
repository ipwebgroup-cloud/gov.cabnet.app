# v3.0.64 — V3 Pre-Live Switchboard Ops 500 Hotfix

## Upload

Upload:

`public_html/gov.cabnet.app/ops/pre-ride-email-v3-pre-live-switchboard.php`

To:

`/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-pre-live-switchboard.php`

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-pre-live-switchboard.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_switchboard.php --json"
```

Open:

`https://gov.cabnet.app/ops/pre-ride-email-v3-pre-live-switchboard.php`

Expected: page loads and shows live-submit blocked. No EDXEIX/AADE calls.
