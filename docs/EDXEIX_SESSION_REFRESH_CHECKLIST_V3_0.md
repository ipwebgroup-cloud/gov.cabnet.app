# EDXEIX Session Refresh Checklist v3.0

Adds `/ops/edxeix-session-refresh-checklist.php`.

## Purpose

Provide a safe operator checklist for refreshing the browser-captured EDXEIX session and verifying it using the existing GET-only probes.

## Safety

Checklist-only:
- no Bolt call
- no EDXEIX call
- no POST
- no database read/write
- no file write
- no job staging
- no mapping update
- no secret output
- no live submission

## Current known blockers

- EDXEIX authenticated lease form access is not confirmed.
- Current target matrix resolves protected routes to LOGIN_OR_SESSION_PAGE.
- No eligible future-safe Bolt candidate exists yet.
