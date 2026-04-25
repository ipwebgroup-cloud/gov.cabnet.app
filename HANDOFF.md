# HANDOFF — gov.cabnet.app Bolt → EDXEIX bridge

Current baseline: production-prep is safe and live submission remains blocked.

Latest patch fixes EDXEIX placeholder session detection. Copied example/template values in `/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json` must not count as a real EDXEIX browser session.

Expected current state until real EDXEIX values are added:

- `/ops/edxeix-session.php`: session file exists and JSON valid, but placeholder values detected and session readiness is false.
- `/ops/live-submit.php`: no real future candidate, no selected booking, live HTTP transport blocked.
- live submission remains disabled and unauthorized.

Remaining prerequisites for final live phase:

1. Real future Bolt candidate using Filippos + mapped vehicle.
2. Real server-side EDXEIX session cookie/CSRF values.
3. Exact EDXEIX submit URL configured in server-only `live_submit.php`.
4. Final HTTP transport patch after explicit Andreas approval.
