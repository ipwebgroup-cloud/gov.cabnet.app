# Ops UI Shell Phase 31 — EDXEIX Submit Capture

Adds a sanitized research metadata capture page for the future server-side EDXEIX submitter.

## Route

- `/ops/edxeix-submit-capture.php`

## Purpose

The page records only safe metadata needed to design the eventual mobile/server-side EDXEIX submitter:

- form method
- EDXEIX action host/path
- CSRF field name only, never token value
- map/address field names
- required field names
- select/dropdown field names
- sanitized notes

## Safety

This phase does not:

- call Bolt
- call EDXEIX
- call AADE
- modify `/ops/pre-ride-email-tool.php`
- stage queue jobs
- enable live submission
- store cookies, session values, CSRF token values, passwords, or credentials

## SQL

Run:

```bash
mysql -u cabnet_gov -p cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_05_12_ops_edxeix_submit_captures.sql
```

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-capture.php
```

Open:

```text
https://gov.cabnet.app/ops/edxeix-submit-capture.php
```

## Next phase

Phase 32 should build a server-side dry-run payload builder that consumes:

- a saved sanitized EDXEIX capture
- parsed pre-ride email fields
- resolved EDXEIX IDs

and shows what would be posted without making an EDXEIX request.
