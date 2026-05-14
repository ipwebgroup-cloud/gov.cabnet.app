# Patch README — v3.0.56-v3-adapter-contract-probe

## What changed

Adds a V3-only adapter contract probe CLI and Ops page.

## Files

- `gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_contract_probe.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-adapter-contract-probe.php`
- `docs/V3_ADAPTER_CONTRACT_PROBE.md`
- `docs/V3_AUTOMATION_NEXT_STEPS.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`
- `PATCH_README.md`

## Upload paths

- `gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_contract_probe.php` → `/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_contract_probe.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-adapter-contract-probe.php` → `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-adapter-contract-probe.php`

Docs stay in the local GitHub Desktop repo unless intentionally uploaded.

## SQL

No SQL required.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_contract_probe.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-adapter-contract-probe.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_contract_probe.php"
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_contract_probe.php --json"
```

## Safety

No Bolt call. No EDXEIX call. No AADE call. No DB writes. No queue status changes. No production submission tables. V0 untouched.
