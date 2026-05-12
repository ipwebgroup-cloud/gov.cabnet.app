# Ops UI Shell Phase 32 — EDXEIX Submit Dry-Run Builder

Adds `/ops/edxeix-submit-dry-run.php`.

## Purpose

This route builds a read-only preview of the future server-side EDXEIX submit payload using:

- parsed Bolt pre-ride email data,
- DB-backed EDXEIX ID mapping,
- latest sanitized submit capture metadata from `ops_edxeix_submit_captures`.

## Safety contract

The page does not:

- call Bolt,
- call EDXEIX,
- call AADE,
- write workflow data,
- stage jobs,
- enable live submit,
- read/display cookies, sessions, passwords, CSRF token values, or real credentials,
- modify `/ops/pre-ride-email-tool.php`.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-dry-run.php
```

Open:

```text
https://gov.cabnet.app/ops/edxeix-submit-dry-run.php
```

Expected: login required, dry-run page loads, latest email/pasted email can be parsed, canonical payload preview appears, blockers list includes `live_edxeix_submit_not_implemented_in_dry_run_builder`.
