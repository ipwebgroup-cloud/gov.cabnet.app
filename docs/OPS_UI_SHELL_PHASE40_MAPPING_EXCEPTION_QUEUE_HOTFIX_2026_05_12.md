# Ops UI Shell Phase 40 Hotfix — Mapping Exception Queue

Date: 2026-05-12

## Purpose

Fixes a runtime 500 error on `/ops/mapping-exceptions.php` while preserving the read-only mapping exception queue.

## Change

The exception queue is now more schema-safe:

- avoids aggregate SQL over optional columns
- detects columns through `information_schema.COLUMNS`
- counts mapping issues in PHP rather than with fragile SQL expressions
- disables mysqli strict report mode for this diagnostic page
- catches runtime errors and renders a safe diagnostic card when possible

## Safety

No production workflow changes.
No Bolt calls.
No EDXEIX calls.
No AADE calls.
No database writes.
No queue staging.
No live submission behavior.

## Upload

Upload:

```text
public_html/gov.cabnet.app/ops/mapping-exceptions.php
```

to:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/mapping-exceptions.php
```

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mapping-exceptions.php
```

Then open:

```text
https://gov.cabnet.app/ops/mapping-exceptions.php
```
