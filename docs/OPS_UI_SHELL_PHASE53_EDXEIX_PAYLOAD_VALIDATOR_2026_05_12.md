# Ops UI Shell Phase 53 — EDXEIX Submit Payload Validator

Date: 2026-05-12

## Summary

Adds a dry-run EDXEIX submit payload validator for the future mobile/server-side submit workflow.

## Files

- `gov.cabnet.app_app/src/Edxeix/EdxeixSubmitPayloadValidator.php`
- `public_html/gov.cabnet.app/ops/edxeix-submit-payload-validator.php`

## Safety

This phase does not call Bolt, EDXEIX, or AADE. It does not write workflow data, stage jobs, enable live submission, or display cookies, session values, credentials, or CSRF token values.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Edxeix/EdxeixSubmitPayloadValidator.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-payload-validator.php
```

Open:

```text
https://gov.cabnet.app/ops/edxeix-submit-payload-validator.php
```
