# Scope

## Goal

Build and harden a safe Bolt Fleet API → normalized local bookings → EDXEIX submission pipeline.

## In scope now

- Sync Bolt drivers and vehicles.
- Sync recent Bolt fleet orders.
- Normalize orders into local tables.
- Map Bolt drivers/vehicles to EDXEIX IDs.
- Build EDXEIX payload previews.
- Block terminal/cancelled/old orders.
- Require a +30 minute future guard before any order can be considered submission-safe.
- Stage local jobs only when explicitly requested.
- Maintain readiness/audit pages.
- Keep all current behavior dry-run, local-only, preflight-only, or read-only.

## Out of scope until explicit approval

- Automatic EDXEIX submission.
- Cron-enabled submission workers.
- Live form POSTs to EDXEIX.
- Committing production credentials, cookies, API keys, real SQL dumps, or runtime sessions.

## Current live-test blocker

A real Bolt ride must be scheduled at least 40–60 minutes in the future before a true live-safe EDXEIX candidate can exist.
