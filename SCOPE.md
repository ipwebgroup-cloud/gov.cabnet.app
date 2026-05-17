# Scope

## Goal

Build and harden a safe Bolt Fleet API → normalized local bookings → EDXEIX submission pipeline.

The ASAP automation track is active, but safety remains stricter than speed: server-side automation must only advance after we can prove what EDXEIX does after submit and can verify a saved contract/reference.

## In scope now

- Sync Bolt drivers and vehicles.
- Sync recent Bolt fleet orders.
- Normalize orders into local tables.
- Map Bolt drivers/vehicles to EDXEIX IDs.
- Build EDXEIX payload previews.
- Block terminal/cancelled/old orders.
- Require a future guard before any order can be considered submission-safe.
- Stage local jobs only when explicitly requested.
- Maintain readiness/audit pages.
- Keep normal behavior dry-run, local-only, preflight-only, read-only, or explicitly gated.
- Diagnose EDXEIX submit behavior without assuming HTTP 302 means success.
- Capture safe redirect-chain fingerprints for a supervised one-shot submit diagnostic.
- Classify EDXEIX submit outcomes as login/session, CSRF/session rejection, validation error, success candidate, or unknown.
- Use browser/extension proof capture as a fallback/helper when server-side EDXEIX automation lacks a reliable success signal.

## ASAP automation milestones

1. **Diagnostic redirect trace** — identify what EDXEIX HTTP 302 really means.
2. **Success proof** — confirm a saved contract/reference through a verifier/list match.
3. **Controlled one-shot** — one eligible future Bolt trip, one explicitly authorized attempt, no retry loop.
4. **Repeatable supervised flow** — prove the same success pattern more than once.
5. **Worker readiness** — only after proof, consider a disabled-by-default automatic worker.
6. **Browser-extension fallback** — keep as helper/proof bridge, not the primary automation brain.

## Out of scope until explicit approval

- Unattended automatic EDXEIX submission.
- Cron-enabled live submission workers.
- Retry loops for failed/unknown EDXEIX submits.
- Treating HTTP 302 alone as saved/confirmed.
- Committing production credentials, cookies, API keys, real SQL dumps, raw EDXEIX HTML, or runtime sessions.

## Current live-test blocker

Queue 2398 is closed as **not confirmed / not saved**. One supervised POST returned HTTP 302, but no remote/reference ID was captured and no EDXEIX list/search proof confirmed the expected contract.

A new live diagnostic requires a real eligible future Bolt ride and explicit one-shot authorization. Historical, cancelled, terminal, expired, invalid, receipt-only, lab/test, or past Bolt orders must never be submitted.
