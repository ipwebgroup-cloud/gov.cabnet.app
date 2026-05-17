# Scope

## Goal

Build and harden a safe Bolt Fleet API / pre-ride email → normalized local readiness → EDXEIX diagnostic/queue workflow.

## In scope now

- Sync Bolt drivers and vehicles.
- Sync recent Bolt fleet orders.
- Normalize orders into local tables.
- Map Bolt drivers/vehicles to EDXEIX IDs.
- Build EDXEIX payload previews.
- Block terminal/cancelled/old orders.
- Require a +30 minute future guard before any order or pre-ride email can be considered EDXEIX-safe.
- Stage local jobs only when explicitly requested.
- Maintain readiness/audit pages.
- Keep current behavior dry-run, local-only, preflight-only, or read-only.
- Parse pre-ride emails into separate `bolt_pre_ride_email` candidate diagnostics without changing production V0 behavior.
- Capture sanitized pre-ride candidate metadata only when explicitly requested with `--write=1`.

## v3.2.23 ASAP track

- Add diagnostics-only fallback parsing for Maildir pre-ride candidates whose labels are present but not line-start normalized.
- Keep the production parser file untouched.
- Continue blocking candidates until all future guard, parser, mapping, and exclusion checks pass.

## Out of scope until explicit approval

- Automatic EDXEIX submission.
- Cron-enabled submission workers.
- Unsupervised live form POSTs to EDXEIX.
- Treating receipt-only Bolt mail rows as EDXEIX candidates.
- Committing production credentials, cookies, API keys, real SQL dumps, or runtime sessions.

## Current live-test blocker

A real future pre-ride email or real future Bolt trip must pass parser, mapping, exclusion, duplicate, and +30 minute future guard checks before a supervised one-shot EDXEIX transport diagnostic can be considered.
