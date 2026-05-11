# Ops UI Shell Phase 17 — Deployment Center

Date: 2026-05-11
Project: gov.cabnet.app Bolt → EDXEIX bridge

## Purpose

Adds a read-only deployment center page for Andreas' current manual deployment workflow:

1. Code with ChatGPT.
2. Download patch zip.
3. Extract locally into GitHub Desktop repo.
4. Upload changed files manually to the server.
5. Verify on server.
6. Commit after production confirmation.

## Files

- `public_html/gov.cabnet.app/ops/_shell.php`
- `public_html/gov.cabnet.app/ops/deployment-center.php`

## Safety

This patch does not modify the production pre-ride email tool:

- `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php`

The new page is read-only and performs no external calls or writes.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/deployment-center.php
```

Open:

```text
https://gov.cabnet.app/ops/deployment-center.php
```
