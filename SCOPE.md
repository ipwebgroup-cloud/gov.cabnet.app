# Scope

## Goal

Build and harden a safe Bolt Fleet API → normalized local bookings → EDXEIX preflight/queue/readiness/submit-diagnostic pipeline, moving toward full automation only after confirmed proof that EDXEIX accepts and saves a real future trip.

## In scope now

- Sync Bolt drivers and vehicles.
- Sync recent Bolt fleet orders.
- Normalize orders into local tables.
- Map Bolt drivers/vehicles to EDXEIX IDs.
- Build EDXEIX payload previews.
- Block terminal/cancelled/old orders.
- Require a +30 minute minimum future guard before any order can be considered submit-diagnostic safe.
- Stage local jobs only when explicitly requested.
- Maintain readiness/audit pages.
- Maintain dry-run/local audit behavior by default.
- Run EDXEIX submit diagnostics to classify HTTP 302/redirect behavior without treating redirect as proof.
- Discover real future Bolt candidates and show why each candidate is or is not eligible.
- Use browser-extension/operator proof capture only as a fallback/helper layer when EDXEIX browser state is required.

## ASAP automation track

1. Keep server-side queue/preflight as the automation brain.
2. Use v3.2.21 candidate discovery to find only real future Bolt candidates.
3. Correct any mapping/config/session blockers visible in the diagnostic.
4. When a real future candidate exists, run dry-run diagnostics against that explicit booking.
5. Only after explicit approval, run one supervised diagnostic transport trace.
6. Classify the final EDXEIX result and verify saved contract/list proof.
7. Promote to one-shot controlled live submit only after proof is reliable.
8. Promote to unattended worker only after repeated proof and duplicate protection are confirmed.

## Out of scope until explicit approval

- Unattended automatic EDXEIX submission.
- Cron-enabled live submission workers.
- Live form POSTs to EDXEIX without one-shot authorization.
- Treating HTTP 302 alone as success.
- Committing production credentials, cookies, API keys, real SQL dumps, or runtime sessions.

## Current live-test blocker

A real Bolt ride must be scheduled sufficiently in the future before a true live-safe EDXEIX candidate can exist. The diagnostic now enforces a +30 minute minimum guard even if current config is lower.
