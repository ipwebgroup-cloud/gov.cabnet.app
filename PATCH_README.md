# Patch README — gov.cabnet.app v4.0 Synthetic Bolt Mail Test Harness

## What changed

Adds a safe synthetic Bolt `Ride details` email generator so parser/preflight testing can continue without rider-app payment transactions.

## Files included

- `gov.cabnet.app_app/src/Mail/BoltSyntheticMailFactory.php`
- `gov.cabnet.app_app/cli/create_synthetic_bolt_mail.php`
- `public_html/gov.cabnet.app/ops/mail-synthetic-test.php`
- `docs/BOLT_SYNTHETIC_MAIL_TEST.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload paths

- `/home/cabnet/gov.cabnet.app_app/src/Mail/BoltSyntheticMailFactory.php`
- `/home/cabnet/gov.cabnet.app_app/cli/create_synthetic_bolt_mail.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/mail-synthetic-test.php`

## SQL

No SQL migration is required.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Mail/BoltSyntheticMailFactory.php
php -l /home/cabnet/gov.cabnet.app_app/cli/create_synthetic_bolt_mail.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mail-synthetic-test.php
```

Open:

```text
https://gov.cabnet.app/ops/mail-synthetic-test.php?key=YOUR_INTERNAL_API_KEY
```

Or CLI:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/create_synthetic_bolt_mail.php --lead=15 --duration=30 --import-now
```

## Safety

This patch does not call Bolt, does not call EDXEIX, does not stage jobs, and does not submit live.
