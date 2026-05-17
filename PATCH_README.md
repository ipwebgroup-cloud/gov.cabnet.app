# Patch README — gov-edxeix-create-form-token-diagnostic-v3.2.35

## Summary

Fixes the EDXEIX create-form token diagnostic classification after the browser session was saved successfully. The server can now reach `/dashboard/lease-agreement/create`, but v3.2.33 selected the logout form and treated generic login/CSRF text as blockers. v3.2.35 selects the best lease-agreement form by expected field score and uses field aliases for `starting_point_id` and `lessee[name]`.

## Upload paths

- `/home/cabnet/CONTINUE_PROMPT.md`
- `/home/cabnet/HANDOFF.md`
- `/home/cabnet/PATCH_README.md`
- `/home/cabnet/PROJECT_FILE_MANIFEST.md`
- `/home/cabnet/README.md`
- `/home/cabnet/SCOPE.md`
- `/home/cabnet/docs/EDXEIX_CREATE_FORM_TOKEN_DIAGNOSTIC_v3.2.35.md`
- `/home/cabnet/gov.cabnet.app_app/cli/edxeix_create_form_token_diagnostic.php`
- `/home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php`
- `/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_transport_trace_lib.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/edxeix-create-form-token-diagnostic.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-one-shot-transport-trace.php`

## SQL

None.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_transport_trace_lib.php
php -l /home/cabnet/gov.cabnet.app_app/cli/edxeix_create_form_token_diagnostic.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-create-form-token-diagnostic.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-one-shot-transport-trace.php

php /home/cabnet/gov.cabnet.app_app/cli/edxeix_create_form_token_diagnostic.php --json
```

## Safety

No EDXEIX POST. No AADE. No queue job. No normalized booking write. No live config write. No V0 production change.
