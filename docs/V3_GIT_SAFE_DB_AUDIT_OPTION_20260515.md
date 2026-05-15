# V3 Git-Safe + DB Audit package option — 2026-05-15

## Purpose

Adds an optional database-including package button inside the Git-Safe Continuity ZIP section of `/ops/handoff-center.php`.

This is for live-site and database audit work. It is not for GitHub commits.

## Package modes after this patch

1. **Private Operational ZIP**
   - Includes `DATABASE_EXPORT.sql` when the builder succeeds.
   - Private operational recovery/continuity package.
   - Never commit to GitHub.

2. **Git-Safe Continuity ZIP**
   - Builds with `include_database=false`.
   - Defensively removes `DATABASE_EXPORT.sql` if present.
   - Scrubs runtime/session/cookie files, storage proof artifacts, backup files, and temporary package residue.
   - Intended for local repo continuity review before commit.

3. **Git-Safe + DB Audit ZIP**
   - Builds with `include_database=true`.
   - Keeps `DATABASE_EXPORT.sql` for live-site database audit.
   - Still scrubs runtime/session/cookie files, storage proof artifacts, backup files, and temporary package residue.
   - Adds `GIT_SAFE_WITH_DB_AUDIT_NOTICE.md`.
   - Private audit only; never commit to GitHub.

## Safety posture

- No Bolt calls.
- No EDXEIX calls.
- No AADE calls.
- No live-submit changes.
- No SQL migration required.
- V3 live gate remains closed.
