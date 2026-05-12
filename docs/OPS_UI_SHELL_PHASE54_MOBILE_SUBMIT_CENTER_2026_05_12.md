# Ops UI Shell Phase 54 — Mobile Submit Center

## Purpose

Adds a read-only Mobile Submit Center for the future mobile/server-side EDXEIX submit workflow.

The page centralizes the mobile submit development routes, submit research routes, connector dry-run routes, private class status, DB readiness, and sanitized capture status.

## Added file

- `public_html/gov.cabnet.app/ops/mobile-submit-center.php`

## Production safety

This page does not:

- modify `/ops/pre-ride-email-tool.php`
- call Bolt
- call EDXEIX
- call AADE
- write workflow data
- stage jobs
- enable live submit
- display cookies/session/token values
- display real config values

Live server-side EDXEIX submit remains disabled.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-center.php
```

Open:

```text
https://gov.cabnet.app/ops/mobile-submit-center.php
```

Expected:

- login required
- shared ops shell loads
- readiness overview displays
- submit route and private class status displays
- latest sanitized submit capture status displays if available
- no live submit controls exist
- production pre-ride tool remains unchanged
