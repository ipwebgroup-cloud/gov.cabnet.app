# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

Updated: 2026-05-15  
Current live milestone: v3.0.80 navigation de-bloat prepared after Sophion live-site audit

## Project identity

- Domain: https://gov.cabnet.app
- Repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- Live server is not a cloned Git repo.

Expected server layout:

```text
/home/cabnet/public_html/gov.cabnet.app
/home/cabnet/gov.cabnet.app_app
/home/cabnet/gov.cabnet.app_config
/home/cabnet/gov.cabnet.app_sql
/home/cabnet/tools/firefox-edxeix-autofill-helper
```

## Current safety posture

- Live EDXEIX submission remains disabled.
- The V3 `edxeix_live` adapter remains skeleton-only/non-live.
- V3 proof/contract tooling is read-only/closed-gate.
- No SQL cleanup has been applied.
- No V0 production workflow has been changed.

## Latest verified V3 milestone

- `v3.0.75-v3-live-adapter-contract-test` passed on production for queue `#716`.
- Payload hash: `e784e788532fc57824a46dad90debec9d0ad5a24f94679538c37d1d164e9f472`
- Contract test result: `ok=true`, `contract_safe=true`, `final_blocks=[]`.
- No adapter submit call, no EDXEIX call, no AADE call, no DB writes.

## Live audit findings

A private Sophion DB/route audit package was used for live-site evaluation only. It is not a Git package.

Findings:

- Public/ops PHP surface: 171 route files.
- PHP syntax: clean in the audited package.
- V3 queue rows: closed/blocked after expiry; no V3 submitted rows.
- `submission_jobs` and `submission_attempts`: empty.
- Main DB cleanup candidate: `backup_normalized_bookings_v6_2_2_bad_20260508_120503`.
- No DB cleanup should occur without a fresh backup and explicit approval.

## Package hygiene

- Handoff Center is at `v3.0.78-v3-git-safe-db-audit-option`.
- Runtime/session/proof artifacts are scrubbed from generated packages.
- DB audit packages may include `DATABASE_EXPORT.sql` and are private only.
- Git-safe continuity packages remain DB-free.

## Current patch prepared

`v3.0.80-navigation-debloat`:

- Updates `/ops/_shell.php` to reduce daily sidebar clutter.
- Keeps daily operator links visible.
- Keeps V3 proof/readiness links visible.
- Moves dev/test/mobile/evidence/package/helper routes into a collapsed Developer Archive.
- Updates `/ops/route-index.php` as a static live route inventory and developer archive map.

## Next safest step

Upload the v3.0.80 patch, verify syntax/auth, and visually confirm the sidebar is cleaner while all routes remain accessible.
