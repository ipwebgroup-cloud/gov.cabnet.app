# Ops UI Shell Phase 61 — Mobile Submit Evidence Center

Date: 2026-05-12

## Summary

Adds a read-only hub page for the future mobile/server-side EDXEIX submit evidence workflow:

- `/ops/mobile-submit-evidence-center.php`

The page centralizes trial run, sanitized evidence generation, evidence log, evidence review, synthetic scenarios, disabled connector preview, and payload validator routes.

## Safety contract

This patch does not:

- modify `/ops/pre-ride-email-tool.php`
- call Bolt
- call EDXEIX
- call AADE
- write database rows
- stage jobs
- enable live EDXEIX submission
- display raw email text
- display cookies, session values, CSRF token values, credentials, or real config values

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-evidence-center.php
```

Expected:

```text
No syntax errors detected
```

Open:

```text
https://gov.cabnet.app/ops/mobile-submit-evidence-center.php
```

Expected:

- login required
- shared ops shell loads
- evidence route cards display
- private class readiness displays
- evidence log table status displays
- recent saved evidence records display if Phase 59 SQL is installed
- no live submit controls exist
- production pre-ride tool remains unchanged
