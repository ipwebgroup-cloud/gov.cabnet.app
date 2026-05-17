# Scope

## Goal

Build and harden a safe Bolt Fleet API / pre-ride email → normalized local readiness → EDXEIX preflight/queue/readiness pipeline.

## In scope now

- Sync Bolt drivers and vehicles.
- Sync recent Bolt fleet orders.
- Normalize orders into local tables.
- Map Bolt drivers/vehicles to EDXEIX IDs.
- Build EDXEIX payload previews.
- Block terminal/cancelled/old orders.
- Require a +30 minute future guard before any order/candidate can be considered submission-safe.
- Stage local jobs only when explicitly requested.
- Maintain readiness/audit pages.
- Keep current submit behavior dry-run, local-only, preflight-only, or read-only.
- Diagnose EDXEIX submit redirects without treating HTTP 302 as proof of saved contract.
- Parse Bolt pre-ride emails into a separate dry-run `bolt_pre_ride_email` future candidate preview.
- Optionally capture sanitized pre-ride candidate metadata in `edxeix_pre_ride_candidates` after additive SQL migration.

## Out of scope until explicit approval

- Unattended automatic EDXEIX submission.
- Cron-enabled submission workers.
- Live form POSTs to EDXEIX except a supervised one-shot diagnostic explicitly approved by Andreas.
- Treating `bolt_mail` receipt-only rows as EDXEIX candidates.
- Creating EDXEIX submission jobs from pre-ride emails without a future readiness patch and approval.
- Storing raw pre-ride email bodies.
- Committing production credentials, cookies, API keys, real SQL dumps, or runtime sessions.

## Current automation blocker

No real future EDXEIX-ready candidate exists in the latest normalized Bolt rows. The ASAP path is now to use the pre-ride email stream as a separate future candidate source, while keeping receipt-only mail rows blocked.
