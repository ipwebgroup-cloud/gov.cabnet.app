Continue the gov.cabnet.app Bolt → EDXEIX bridge from v3.2.30.

The project has reached the supervised live-test boundary for pre-ride EDXEIX candidates.

v3.2.30 introduced:
- `gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_transport_trace_lib.php`
- `gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php`
- `public_html/gov.cabnet.app/ops/pre-ride-one-shot-transport-trace.php`
- optional SQL table `edxeix_pre_ride_transport_attempts`

Default behavior is dry-run. Transport requires candidate ID, exact payload hash, `--transport=1`, and exact confirmation phrase.

Keep these rules:
- Never submit past/terminal/too-close candidates.
- Never enable unattended EDXEIX submit from a generic continue.
- After one POST trace, require manual EDXEIX verification before any retry or automation step.
