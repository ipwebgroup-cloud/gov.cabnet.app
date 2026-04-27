# HANDOFF — gov.cabnet.app Bolt → EDXEIX bridge

Current state after v2.7:
- Live EDXEIX submission remains disabled.
- v2.6 showed submit mechanics are ready but there is no eligible candidate.
- v2.7 adds `/ops/edxeix-session-probe.php` for local EDXEIX session metadata and optional read-only GET probing.
- Default load does not call EDXEIX.
- `?probe=1` performs GET only and never POSTs.

Primary safe entry:
`https://gov.cabnet.app/ops/home.php`

EDXEIX submit readiness:
`https://gov.cabnet.app/ops/edxeix-submit-readiness.php`

EDXEIX session/form GET probe:
`https://gov.cabnet.app/ops/edxeix-session-probe.php`

Do not enable live submission unless Andreas explicitly requests it after a real eligible future Bolt trip passes preflight.
