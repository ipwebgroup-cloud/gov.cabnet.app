# gov.cabnet.app Patch — Pre-Ride Readiness Watch v3.2.28

Generated for Andreas on 2026-05-17.

## What changed

Adds a safe readiness watch layer so the next future pre-ride candidate can be caught before the 30-minute future guard expires.

The patch adds:

- CLI readiness watch: `pre_ride_readiness_watch.php`
- Ops page: `/ops/pre-ride-readiness-watch.php`
- Watch library that combines latest Maildir pre-ride candidate analysis with existing one-shot readiness packet checks.
- Optional metadata capture only when explicitly requested.

## Files included

- `CONTINUE_PROMPT.md`
- `HANDOFF.md`
- `PATCH_README.md`
- `PROJECT_FILE_MANIFEST.md`
- `README.md`
- `SCOPE.md`
- `docs/EDXEIX_PRE_RIDE_READINESS_WATCH_v3.2.28.md`
- `gov.cabnet.app_app/lib/edxeix_pre_ride_readiness_watch_lib.php`
- `gov.cabnet.app_app/cli/pre_ride_readiness_watch.php`
- `public_html/gov.cabnet.app/ops/pre-ride-readiness-watch.php`

## Exact upload paths

Upload/replace:

```text
/home/cabnet/CONTINUE_PROMPT.md
/home/cabnet/HANDOFF.md
/home/cabnet/PATCH_README.md
/home/cabnet/PROJECT_FILE_MANIFEST.md
/home/cabnet/README.md
/home/cabnet/SCOPE.md
/home/cabnet/docs/EDXEIX_PRE_RIDE_READINESS_WATCH_v3.2.28.md
/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_readiness_watch_lib.php
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_readiness_watch.php
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-readiness-watch.php
```

For local GitHub Desktop repo, extract this ZIP at the repository root. The ZIP root mirrors the repo/live layout directly and has no wrapper folder.

## SQL to run

None.

The `edxeix_pre_ride_candidates` table was already installed in v3.2.22 and validated by captured candidate ID 2.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_readiness_watch_lib.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_readiness_watch.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-readiness-watch.php

php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_readiness_watch.php --json
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_readiness_watch.php --json --capture-ready
```

## Verification URL

```text
https://gov.cabnet.app/ops/pre-ride-readiness-watch.php
https://gov.cabnet.app/ops/pre-ride-readiness-watch.php?auto_refresh=30
```

## Expected result

The watch should return one of:

```text
WATCH_READY_NOT_CAPTURED
WATCH_CAPTURED_READY_PACKET
WATCH_EXISTING_READY_PACKET
WATCH_CAPTURED_PACKET_BLOCKED
WATCH_NO_READY_CANDIDATE
```

No EDXEIX transport is performed.

## Git commit title

Add pre-ride readiness watch

## Git commit description

Adds a safe pre-ride readiness watch CLI and ops page so future Bolt pre-ride Maildir candidates can be detected, optionally captured as sanitized metadata, and checked with the existing one-shot readiness packet before the 30-minute future guard expires.

No SQL changes. No EDXEIX transport, AADE call, queue job, normalized booking write, cron installation, or live-submit config change.
