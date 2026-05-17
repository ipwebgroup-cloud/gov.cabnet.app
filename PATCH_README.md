# Patch README — gov-edxeix-fresh-form-token-transport-v3.2.36

## Summary

Adds fresh EDXEIX create-form token integration to the supervised pre-ride one-shot transport trace. The transport path now fetches `/dashboard/lease-agreement/create` immediately before POST, validates the authenticated form context, extracts the hidden `_token` internally, injects it into the one POST attempt, and never prints/stores the raw token.

Candidate 4 remains archived/manual V0 submitted and retry-blocked. This patch is for the next new future candidate only.

## Upload paths

- `/home/cabnet/CONTINUE_PROMPT.md`
- `/home/cabnet/HANDOFF.md`
- `/home/cabnet/PATCH_README.md`
- `/home/cabnet/PROJECT_FILE_MANIFEST.md`
- `/home/cabnet/README.md`
- `/home/cabnet/SCOPE.md`
- `/home/cabnet/docs/EDXEIX_FRESH_FORM_TOKEN_TRANSPORT_INTEGRATION_v3.2.36.md`
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
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php --candidate-id=4 --json
```

Expected for candidate 4: blocked because it is manually submitted via V0.

## Next new candidate workflow

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_readiness_watch.php --json --capture-ready
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php --candidate-id=NEW_ID --json
```

Only if armable:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php \
  --candidate-id=NEW_ID \
  --transport=1 \
  --expected-payload-hash=HASH_FROM_DRY_RUN \
  --confirm='I UNDERSTAND POST THIS ONE PRE-RIDE CANDIDATE TO EDXEIX' \
  --json
```

## Safety

No unattended automation. No cron. No AADE. No queue job. No normalized booking write. No live config write. No V0 production change. Raw cookie/CSRF/token/body are not printed or stored.
