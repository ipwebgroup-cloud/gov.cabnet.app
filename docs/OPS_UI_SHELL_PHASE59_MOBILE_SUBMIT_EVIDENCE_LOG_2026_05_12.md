# Ops UI Shell Phase 59 — Mobile Submit Evidence Log

Date: 2026-05-12

## Purpose

Adds an admin-only Mobile Submit Evidence Log for storing sanitized evidence JSON produced by the mobile/server-side EDXEIX submit dry-run workstream.

The log is intended to preserve proof of parser, mapping, starting-point, preflight, disabled connector, and payload-validator results without storing raw email bodies or secrets.

## Files

- `public_html/gov.cabnet.app/ops/mobile-submit-evidence-log.php`
- `gov.cabnet.app_sql/2026_05_12_mobile_submit_evidence_log.sql`

## Safety contract

This phase does not:

- modify `/ops/pre-ride-email-tool.php`
- call Bolt
- call EDXEIX
- call AADE
- stage jobs
- enable live EDXEIX submission
- store raw email text
- store cookies
- store session values
- store CSRF token values
- store credentials
- store real config values

The page stores sanitized JSON evidence only, and blocks obvious raw email / secret patterns.

## SQL

Run:

```bash
mysql -u cabnet_gov -p cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_05_12_mobile_submit_evidence_log.sql
```

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-evidence-log.php
```

Open:

```text
https://gov.cabnet.app/ops/mobile-submit-evidence-log.php
```

Expected:

- login required
- admin-only page opens inside shared ops shell
- table readiness displays
- sanitized evidence JSON can be pasted and saved after SQL migration
- saved records can be downloaded as JSON
- production pre-ride tool remains unchanged
