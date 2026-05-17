# gov.cabnet.app patch — v3.2.37 strict identity lock + validation capture

## Summary

Adds strict candidate identity locking and sanitized validation capture to the supervised pre-ride EDXEIX one-shot trace.

This patch exists because the previous latest-mail workflow selected a real current pre-ride email instead of the intended demo email. v3.2.37 requires explicit candidate ID plus expected customer, driver, vehicle, and pickup datetime before any POST.

## Files included

- `CONTINUE_PROMPT.md`
- `HANDOFF.md`
- `PATCH_README.md`
- `PROJECT_FILE_MANIFEST.md`
- `README.md`
- `SCOPE.md`
- `docs/EDXEIX_STRICT_IDENTITY_LOCK_VALIDATION_CAPTURE_v3.2.37.md`
- `gov.cabnet.app_app/cli/edxeix_create_form_token_diagnostic.php`
- `gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php`
- `gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_transport_trace_lib.php`
- `public_html/gov.cabnet.app/ops/edxeix-create-form-token-diagnostic.php`
- `public_html/gov.cabnet.app/ops/pre-ride-one-shot-transport-trace.php`

## SQL

None.

## Safety

No V0 production route change. No unattended automation. No cron. No AADE/myDATA call. No queue job. No normalized booking write. No live config write.
