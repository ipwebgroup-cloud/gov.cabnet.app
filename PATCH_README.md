# gov.cabnet.app V3 live-submit disabled scaffold patch

## Upload paths

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_worker.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_worker.php

gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_cron_worker.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_cron_worker.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-submit.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-submit.php
```

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_worker.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_cron_worker.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-submit.php

php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_worker.php --limit=20
```

## Suggested cron

```bash
* * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_cron_worker.php >> /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_live_submit_cron.log 2>&1
```

## Safety

This patch does not submit to EDXEIX. It is a hard-disabled scaffold only.
