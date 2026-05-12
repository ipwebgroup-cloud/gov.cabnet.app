# Ops UI Shell Phase 30 — EDXEIX Submit Research

Date: 2026-05-12

## Purpose

Adds a read-only research page for the future server-side EDXEIX submit connector:

```text
/ops/edxeix-submit-research.php
```

This page supports the agreed mobile-submit direction:

```text
Mobile web app + controlled server-side EDXEIX submitter
```

## Safety contract

This phase does not:

- call Bolt
- call EDXEIX
- call AADE
- write workflow database rows
- stage queue jobs
- enable live EDXEIX submission
- read or display cookies, sessions, tokens, credentials, or private config secrets
- modify `/ops/pre-ride-email-tool.php`

## Included behavior

The page shows:

- agreed mobile-submit architecture
- current readiness boundary
- submit connector research checklist
- detected Firefox helper manifests under safe `/home/cabnet/tools` paths
- safe helper source signals without displaying source code
- route/file status for related pages
- recommended next phase: sanitized EDXEIX submit capture schema

## Upload path

```text
public_html/gov.cabnet.app/ops/edxeix-submit-research.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-research.php
```

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-research.php
```

Expected:

```text
No syntax errors detected
```

Open:

```text
https://gov.cabnet.app/ops/edxeix-submit-research.php
```

Expected:

- login required
- page opens in shared ops shell
- no live submit controls
- no EDXEIX network call
- production pre-ride tool remains unchanged

## Git commit title

```text
Add EDXEIX submit research page
```

## Git commit description

```text
Adds a read-only EDXEIX Submit Research page for the future mobile server-side submit workflow. The page documents the submit connector blueprint, required research facts, helper manifest inventory, safe helper source signals, and route status without calling EDXEIX or enabling live submission.

The production pre-ride email tool remains unchanged. No Bolt calls, EDXEIX calls, AADE calls, secret output, database writes, queue staging, or live submission behavior are added.
```
