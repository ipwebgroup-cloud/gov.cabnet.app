# HANDOFF — gov.cabnet.app Bolt → EDXEIX bridge

Current state after v2.6:
- Live EDXEIX submission remains disabled.
- Real Bolt test indicated current visibility appears after completion, not before.
- Existing historical/completed/cancelled rows are blocked by preflight.
- v2.6 adds `/ops/edxeix-submit-readiness.php` to verify local submit preparation without submitting.

Primary safe entry: `https://gov.cabnet.app/ops/home.php`
Submit readiness probe: `https://gov.cabnet.app/ops/edxeix-submit-readiness.php`

Do not enable live submission unless Andreas explicitly requests it after a real eligible future Bolt trip passes preflight.
