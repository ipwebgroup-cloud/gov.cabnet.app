# EDXEIX Pre-Ride Readiness Watch v3.2.28

Generated for Andreas on 2026-05-17.

## Purpose

This patch adds a safe pre-ride readiness watch layer for the gov.cabnet.app Bolt → EDXEIX automation track.

The immediate production lesson from candidate `2` was that a candidate can be fully ready and then become blocked later when the 30-minute future guard window passes. v3.2.28 helps operators catch the next ready candidate earlier.

## Safety posture

- No EDXEIX HTTP transport.
- No AADE/myDATA call.
- No queue job.
- No `normalized_bookings` write.
- Optional write is sanitized metadata only in `edxeix_pre_ride_candidates` and only when `--capture-ready` or the POST capture action is explicitly used.
- Live-submit config is not changed.
- Cron is not installed by this patch.

## New files

- `gov.cabnet.app_app/lib/edxeix_pre_ride_readiness_watch_lib.php`
- `gov.cabnet.app_app/cli/pre_ride_readiness_watch.php`
- `public_html/gov.cabnet.app/ops/pre-ride-readiness-watch.php`

## CLI usage

Dry-run latest Maildir check plus latest captured readiness packet:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_readiness_watch.php --json
```

Capture sanitized metadata only if the latest Maildir candidate is ready:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_readiness_watch.php --json --capture-ready
```

Optional source-structure debug:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_readiness_watch.php --json --debug-source
```

## Web usage

```text
https://gov.cabnet.app/ops/pre-ride-readiness-watch.php
https://gov.cabnet.app/ops/pre-ride-readiness-watch.php?auto_refresh=30
```

The page has a POST-only button for sanitized metadata capture.

## Expected classifications

- `WATCH_READY_NOT_CAPTURED`
- `WATCH_CAPTURED_READY_PACKET`
- `WATCH_EXISTING_READY_PACKET`
- `WATCH_CAPTURED_PACKET_BLOCKED`
- `WATCH_NO_READY_CANDIDATE`
- `WATCH_NO_PRE_RIDE_EMAIL_SOURCE`
- `WATCH_ERROR`

## Next step after this patch

When the watch page reports `WATCH_CAPTURED_READY_PACKET` while the trip is still at least 30 minutes in the future, the project may proceed to a later supervised one-shot transport patch only if Andreas explicitly approves live EDXEIX transport.
