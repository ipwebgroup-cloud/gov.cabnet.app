# Ops UI Shell Phase 23 — Maintenance Center — 2026-05-11

Adds `/ops/maintenance-center.php`, a read-only maintenance checklist page for the gov.cabnet.app operations console.

## Safety contract

- Does not modify `/ops/pre-ride-email-tool.php`.
- Does not call Bolt.
- Does not call EDXEIX.
- Does not call AADE.
- Does not read or display secrets.
- Does not write database rows.
- Does not stage jobs.
- Does not enable live EDXEIX submission.

## Purpose

The page gives operators/admins a central place for safe maintenance checks before and after manual cPanel uploads:

- Critical file presence and safe fingerprints.
- Pre-upload checklist.
- Post-upload checklist.
- Copy/paste syntax checks.
- Copy/paste auth checks.
- Optional root-only backup commands.
- Server-only file reminders.

## Upload path

`public_html/gov.cabnet.app/ops/maintenance-center.php`

→ `/home/cabnet/public_html/gov.cabnet.app/ops/maintenance-center.php`

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/maintenance-center.php
```

Open:

```text
https://gov.cabnet.app/ops/maintenance-center.php
```
