# Live Public Route Exposure Audit — 2026-05-15

## Purpose

Add a read-only live-site audit tool that inspects public-root PHP endpoints and verifies the global authentication prepend posture.

This is part of the live de-bloat and safety alignment work for the gov.cabnet.app Bolt → EDXEIX bridge.

## Safety contract

- No Bolt call.
- No EDXEIX call.
- No AADE call.
- No database connection.
- No filesystem writes.
- No route deletion.
- No live-submit enablement.

## Added routes/files

- CLI: `/home/cabnet/gov.cabnet.app_app/cli/public_route_exposure_audit.php`
- Ops page: `https://gov.cabnet.app/ops/public-route-exposure-audit.php`

## What the audit checks

- Public-root PHP files directly under `/home/cabnet/public_html/gov.cabnet.app`.
- `.user.ini` presence and `auto_prepend_file` setting.
- `_auth_prepend.php` presence and expected login guard markers.
- `.htaccess` helper-file deny posture.
- Public route tokens indicating DB writes, network calls, submit/stage/sync/worker behavior.
- Route classification and recommended no-delete follow-up.

## Expected live posture

The live site should report:

- Global auth prepend active.
- `_auth_prepend.php` present.
- Login guard present.
- Internal key support present for machine/cron access.
- Public-root utility endpoints remain guarded by auth/internal key.
- Delete recommended now: `0`.

## Why this matters

The live audit found that the app is functional but has a wide public/ops surface. The production pre-ride tool remains untouched, but the public-root utility endpoints should continue to be watched so they do not become accidental unauthenticated action routes if PHP auth-prepend posture changes.

## Next safe action after verification

Use this audit to decide whether public-root utility endpoints should later be moved into `/ops` or private CLI-only paths. Do not delete or move anything until each route has an explicit replacement path and production verification plan.
