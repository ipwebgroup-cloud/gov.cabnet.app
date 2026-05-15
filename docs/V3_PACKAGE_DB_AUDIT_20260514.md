# V3 Package and Database Audit — 2026-05-14

## Scope

Audited the uploaded Git-Safe Continuity ZIP and the uploaded `cabnet_gov` SQL export.

## Critical finding

The uploaded Git-Safe Continuity ZIP was DB-free, but it still included live runtime EDXEIX session material under:

```text
gov.cabnet.app_app/storage/runtime/edxeix_session.json
gov.cabnet.app_app/storage/runtime/edxeix_session.json.bak.*
```

Those files contain cookie/session/CSRF material and must not be committed, shared, or retained in Git-safe packages.

## Immediate operational guidance

- Do not commit the uploaded Git-Safe Continuity ZIP.
- Delete that ZIP from local working folders after audit.
- Rotate/refresh the EDXEIX browser session before any future live/manual workflow.
- Apply the package hygiene hotfix before generating another Git-Safe Continuity ZIP.

## DB vs repo SQL summary

- SQL dump contains 30 tables.
- Repo/package SQL migrations reference the live schema for 29 of those 30 tables.
- One leftover backup table exists in the DB without a repo migration or code reference:
  `backup_normalized_bookings_v6_2_2_bad_20260508_120503`
- Do not drop it without a fresh backup and explicit approval.

## V3 schema status

Confirmed present in the DB dump and covered by repo SQL:

```text
pre_ride_email_v3_queue
pre_ride_email_v3_queue_events
pre_ride_email_v3_live_submit_approvals
pre_ride_email_v3_starting_point_options
```

V3 queue rows in this uploaded dump are all blocked. This is safe and expected after the canary moved into the past: historical/past V3 rows should not remain live-submit-ready.

## V3 package/state conclusion

The V3 closed-gate toolchain remains safe:

- Live EDXEIX gate disabled.
- EDXEIX adapter skeleton-only/non-live.
- Queue #716 was validated earlier, then is no longer live-submit-ready after expiry.
- No V3 live EDXEIX submit path should be opened from this state.

## Hotfix included

This patch tightens the Git-safe package builder and Handoff Center post-build scrubber:

- Excludes `/storage/runtime/` from generated packages.
- Excludes EDXEIX session/cookie/CSRF runtime material.
- Excludes cPanel backup filename patterns such as `.bak.*` and `.pre_*`.
- Adds a final Git-safe ZIP scrubber in `/ops/handoff-center.php` that removes unsafe entries before download.
