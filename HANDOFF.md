# HANDOFF — gov.cabnet.app Bolt → EDXEIX bridge

Current state after v3.4:
- Live EDXEIX submission remains disabled.
- v3.1 confirmed edxeix_session.json was freshly updated by the Firefox extension.
- v2.9 rerun confirmed authenticated EDXEIX lease form access.
- v3.2 confirmed local payload field names match the EDXEIX lease form contract.
- v3.3 confirmed final mechanics are prepared but no eligible future-safe Bolt candidate exists.
- v3.4 adds `/ops/edxeix-disabled-live-submit-harness.php`, a disabled harness/runbook documenting the future live-submit sequence without any POST code path.
- No live-submit handler exists in this patch.

Primary safe entry:
`https://gov.cabnet.app/ops/home.php`

Disabled harness:
`https://gov.cabnet.app/ops/edxeix-disabled-live-submit-harness.php`

Do not enable live submission unless Andreas explicitly requests it after a real eligible future Bolt trip passes preflight and EDXEIX session/form access remains confirmed.
