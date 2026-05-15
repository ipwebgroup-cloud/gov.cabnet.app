# gov.cabnet.app patch — v3.0.87 public utility reference cleanup phase 1

## What changed

No-delete reference cleanup for legacy guarded public-root utility endpoints.

The patch updates selected legacy ops pages and docs so direct operator/documentation references to old public-root utilities are replaced with links to the Public Utility Relocation Plan and Public Route Exposure Audit.

## Upload paths

Upload these files:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/bolt-live.php
/home/cabnet/public_html/gov.cabnet.app/ops/jobs.php
/home/cabnet/public_html/gov.cabnet.app/ops/submit.php
/home/cabnet/public_html/gov.cabnet.app/ops/test-booking.php
/home/cabnet/public_html/gov.cabnet.app/ops/help.php
/home/cabnet/public_html/gov.cabnet.app/ops/future-test.php
/home/cabnet/docs/OPS_SITEMAP_V3.md
/home/cabnet/docs/NOVICE_OPERATOR_GUIDE.md
/home/cabnet/docs/DRY_RUN_TEST_BOOKING_HARNESS.md
```

If your server keeps docs only in the repository docs folder, place the docs there instead.

## SQL

None.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/bolt-live.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/jobs.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/submit.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/test-booking.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/help.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/future-test.php

curl -I --max-time 10 https://gov.cabnet.app/ops/bolt-live.php
curl -I --max-time 10 https://gov.cabnet.app/ops/jobs.php
curl -I --max-time 10 https://gov.cabnet.app/ops/submit.php
curl -I --max-time 10 https://gov.cabnet.app/ops/test-booking.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/public_utility_relocation_plan.php --json" \
| php -r '$j=json_decode(stream_get_contents(STDIN), true); echo "ok=".(($j["ok"]??false)?"true":"false").PHP_EOL; echo "version=".($j["version"]??"").PHP_EOL; echo "cleanup_refs=".($j["summary"]["reference_cleanup_blocking_total"]??"?").PHP_EOL; echo "final_blocks=".json_encode($j["final_blocks"]??[], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;'
```

Expected unauthenticated curl result: `302 Found` to `/ops/login.php`.

## Safety

No production pre-ride tool changes. No route moves. No route deletion. No SQL. No live EDXEIX enablement.
