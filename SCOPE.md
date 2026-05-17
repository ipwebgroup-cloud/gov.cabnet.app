# Scope

## Goal

Build and harden a safe Bolt/pre-ride email → local readiness → EDXEIX workflow for gov.cabnet.app.

## In scope now

- Keep Production V0 unaffected.
- Parse Bolt pre-ride emails into diagnostic-only future candidates.
- Keep receipt-only mail rows blocked.
- Apply a 30 minute future guard.
- Check driver, vehicle, lessor, starting point mappings.
- Block Admin Excluded vehicles.
- Display pre-ride readiness in CLI and `/ops/` pages.
- Maintain dry-run/read-only behavior by default.

## Out of scope until explicit approval

- Unattended EDXEIX submission.
- Cron-enabled submit workers.
- Live form POSTs to EDXEIX.
- Automatic retry.
- Any weakening of future/terminal/cancelled/exclusion blockers.
