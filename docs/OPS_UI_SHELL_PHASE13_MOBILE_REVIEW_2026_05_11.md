# Ops UI Shell Phase 13 — Mobile Pre-Ride Review — 2026-05-11

## Purpose

Adds a mobile-friendly read-only pre-ride review route:

- `/ops/pre-ride-mobile-review.php`

This page allows logged-in staff to paste or load a Bolt pre-ride email, parse it, review fields, and run the existing read-only EDXEIX ID lookup from a mobile/tablet browser.

## Production safety

This patch does not modify:

- `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php`

The production pre-ride email tool remains unchanged.

This page does not:

- call Bolt
- call EDXEIX
- call AADE
- stage jobs
- write workflow data
- enable live EDXEIX submission

## Operational rule

Mobile review is for checking only.

Actual EDXEIX form fill/save remains desktop/laptop Firefox only with both current helper extensions loaded.

## Files

- `public_html/gov.cabnet.app/ops/_shell.php`
- `public_html/gov.cabnet.app/ops/pre-ride-mobile-review.php`
- `public_html/gov.cabnet.app/assets/css/gov-ops-shell.css`

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-mobile-review.php
```

Open:

- `https://gov.cabnet.app/ops/pre-ride-mobile-review.php`

Expected:

- login required
- page opens inside shared ops shell
- mobile-friendly paste/load review form appears
- parsing and read-only EDXEIX ID lookup work
- page clearly warns that EDXEIX save remains desktop/laptop only
