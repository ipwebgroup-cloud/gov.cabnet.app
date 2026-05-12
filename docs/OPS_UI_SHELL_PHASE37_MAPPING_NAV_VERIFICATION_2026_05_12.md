# Ops UI Shell Phase 37 — Mapping Navigation + Verification Register

## Purpose

Mappings are now treated as an operational control area because an incorrect starting-point fallback caused an EDXEIX form to select the wrong starting point even though company, driver, and vehicle were correct.

This phase adds:

- a shared mapping navigation partial
- an upgraded Mapping Center hub
- a Mapping Verification Register
- an additive SQL table for verified mapping decisions

## Safety

This phase does not modify the production pre-ride tool.

No Bolt calls, EDXEIX calls, AADE calls, queue staging, or live submission behavior are added.

The only write-capable route is `/ops/mapping-verification.php`, and only admin users can write sanitized verification records to `mapping_verification_status`.

## Files

- `public_html/gov.cabnet.app/ops/_mapping_nav.php`
- `public_html/gov.cabnet.app/ops/mapping-center.php`
- `public_html/gov.cabnet.app/ops/mapping-verification.php`
- `gov.cabnet.app_sql/2026_05_12_mapping_verification_register.sql`

## Verification

Run:

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_mapping_nav.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mapping-center.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mapping-verification.php
```

Then open:

- `https://gov.cabnet.app/ops/mapping-center.php`
- `https://gov.cabnet.app/ops/mapping-verification.php`
- `https://gov.cabnet.app/ops/mapping-verification.php?lessor=1756`
