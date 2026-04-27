# HANDOFF — gov.cabnet.app Bolt → EDXEIX bridge

Current state after v3.3:
- Live EDXEIX submission remains disabled.
- v3.1 confirmed edxeix_session.json was freshly updated by the Firefox extension.
- v2.9 rerun confirmed authenticated EDXEIX lease form access.
- v3.2 confirmed local payload field names match the EDXEIX lease form contract.
- v3.3 adds `/ops/edxeix-final-submit-gate.php`, a final read-only go/no-go gate before any future live-submit handler.
- The expected current result is that mechanics are prepared but no eligible future-safe Bolt candidate exists.
- No POST or live submit exists in this patch.

Primary safe entry:
`https://gov.cabnet.app/ops/home.php`

Final submit gate:
`https://gov.cabnet.app/ops/edxeix-final-submit-gate.php`

Do not enable live submission unless Andreas explicitly requests it after a real eligible future Bolt trip passes preflight and EDXEIX session/form access remains confirmed.
