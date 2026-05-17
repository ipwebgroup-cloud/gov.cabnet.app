# Patch README — gov-edxeix-create-form-token-diagnostic-v3.2.33

## What changed

Adds a read-only EDXEIX create-form token diagnostic after the v3.2.30 HTTP 419 result. The diagnostic fetches `/dashboard/lease-agreement/create`, follows redirects safely, fingerprints the final body, extracts only hashed token diagnostics, summarizes form fields, and keeps server-side transport on hold.

## Upload paths

```text
/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_transport_trace_lib.php
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php
/home/cabnet/gov.cabnet.app_app/cli/edxeix_create_form_token_diagnostic.php
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-one-shot-transport-trace.php
/home/cabnet/public_html/gov.cabnet.app/ops/edxeix-create-form-token-diagnostic.php
/home/cabnet/docs/EDXEIX_CREATE_FORM_TOKEN_DIAGNOSTIC_v3.2.33.md
/home/cabnet/PATCH_README.md
/home/cabnet/HANDOFF.md
/home/cabnet/CONTINUE_PROMPT.md
/home/cabnet/SCOPE.md
/home/cabnet/README.md
/home/cabnet/PROJECT_FILE_MANIFEST.md
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_transport_trace_lib.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php
php -l /home/cabnet/gov.cabnet.app_app/cli/edxeix_create_form_token_diagnostic.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-one-shot-transport-trace.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-create-form-token-diagnostic.php

php /home/cabnet/gov.cabnet.app_app/cli/edxeix_create_form_token_diagnostic.php --json
```

## URLs

```text
https://gov.cabnet.app/ops/edxeix-create-form-token-diagnostic.php
https://gov.cabnet.app/ops/pre-ride-one-shot-transport-trace.php
```

## Safety

No EDXEIX POST, AADE call, queue job, normalized booking write, live config write, cron, or V0 production change.
