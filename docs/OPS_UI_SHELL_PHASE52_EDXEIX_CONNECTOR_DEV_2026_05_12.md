# Ops UI Shell Phase 52 — EDXEIX Submit Connector Dev

Date: 2026-05-12

## Summary

Adds a disabled dry-run connector contract for the future server-side EDXEIX submit workflow.

## Files

- `gov.cabnet.app_app/src/Edxeix/EdxeixSubmitConnector.php`
- `public_html/gov.cabnet.app/ops/edxeix-submit-connector-dev.php`

## Safety

This phase does not:

- call Bolt
- call EDXEIX
- call AADE
- write workflow data
- stage jobs
- enable live EDXEIX submission
- store cookies, sessions, credentials, or CSRF token values

The connector class prepares a request preview only. The `submitDisabled()` method always returns a blocked result.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Edxeix/EdxeixSubmitConnector.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-connector-dev.php
```

Expected:

```text
No syntax errors detected
```

Open:

```text
https://gov.cabnet.app/ops/edxeix-submit-connector-dev.php
```

Expected:

- login required
- page opens inside shared ops shell
- latest/pasted email can be parsed
- EDXEIX IDs display
- sanitized submit capture displays if available
- preflight gate displays
- connector request preview displays
- live submit remains blocked
- production pre-ride tool remains unchanged
