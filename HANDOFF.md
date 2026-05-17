# gov.cabnet.app — Handoff v3.2.28

Current state: pre-ride future candidate parsing and one-shot readiness are validated. Candidate ID 2 was captured as ready, then later became blocked in the web UI because the pickup time passed the 30-minute future guard window. This is correct and safe.

v3.2.28 adds a read-only readiness watch layer:

- `gov.cabnet.app_app/cli/pre_ride_readiness_watch.php`
- `public_html/gov.cabnet.app/ops/pre-ride-readiness-watch.php`
- `gov.cabnet.app_app/lib/edxeix_pre_ride_readiness_watch_lib.php`

Safety remains:

- No EDXEIX transport.
- No AADE/myDATA call.
- No queue job.
- No normalized booking write.
- Optional write only captures sanitized pre-ride metadata.
- Live-submit remains disabled.

Next safe step: use the watch page/CLI during the next real future pre-ride email. If it returns `WATCH_CAPTURED_READY_PACKET` with the trip still at least 30 minutes in the future, Andreas may explicitly approve a separate supervised one-shot transport patch.
