# Ops UI Shell Phase 38 — Mapping Audit

Date: 2026-05-12

## Purpose

Adds a read-only Mapping Audit page for Bolt → EDXEIX mapping governance.

The page is designed to prevent failures where the company, driver, and vehicle mappings are correct, but the starting point falls back to an incorrect global value.

## Added route

- `/ops/mapping-audit.php`

## Safety

This page:

- does not call Bolt
- does not call EDXEIX
- does not call AADE
- does not write database rows
- does not stage jobs
- does not enable live submission

## Main checks

The page audits:

- active drivers without EDXEIX IDs
- active vehicles without EDXEIX IDs
- active lessors without a lessor-specific starting point override
- starting point override IDs missing from the latest export snapshot
- known verified starting point expectations such as WHITEBLUE / 1756 → 612164
- global fallback starting point rows

## Upload path

`public_html/gov.cabnet.app/ops/mapping-audit.php`
→ `/home/cabnet/public_html/gov.cabnet.app/ops/mapping-audit.php`

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mapping-audit.php
```

Expected:

```text
No syntax errors detected
```

Open:

```text
https://gov.cabnet.app/ops/mapping-audit.php
```
