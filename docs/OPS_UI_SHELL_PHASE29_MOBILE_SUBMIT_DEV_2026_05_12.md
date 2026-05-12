# Ops UI Shell Phase 29 — Mobile Submit Dev — 2026-05-12

Adds `/ops/mobile-submit-dev.php` as the first mobile-first development route toward eventual mobile EDXEIX submission.

## Safety contract

- Does not modify `/ops/pre-ride-email-tool.php`.
- Does not call Bolt.
- Does not call EDXEIX.
- Does not call AADE.
- Does not write workflow data.
- Does not stage jobs.
- Does not enable live submission.

## Purpose

The route gives Andreas and operators a realistic mobile-first preview of the future submit workflow:

1. Load latest server email or paste Bolt pre-ride email.
2. Parse ride details with the existing parser.
3. Resolve EDXEIX IDs using the existing read-only mapping lookup.
4. Show passenger, driver, vehicle, pickup, drop-off, pickup time, end time, price, and order reference.
5. Show future/past/too-soon safety state.
6. Show current blockers.
7. Show disabled submit gate.

## Final target

The final mobile solution should become server-side EDXEIX submission behind strict gates:

- login required
- role/permission required
- mapped lessor/driver/vehicle required
- eligible future ride required
- duplicate prevention required
- exact pickup map point confirmation required
- operator confirmation required
- audit logging required
- AADE separation preserved

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-dev.php
```

Then open:

```text
https://gov.cabnet.app/ops/mobile-submit-dev.php
```
