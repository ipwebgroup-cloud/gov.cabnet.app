# HANDOFF — gov.cabnet.app Bolt → EDXEIX bridge

Current state after v3.2:
- Live EDXEIX submission remains disabled.
- v3.1 confirmed edxeix_session.json was freshly updated by the Firefox extension.
- v2.9 rerun confirmed authenticated EDXEIX lease form access:
  - decision: LEASE_FORM_TARGET_CONFIRMED_GET_ONLY
  - best form URL: /dashboard/lease-agreement/create
  - form fields include broker, lessor, lessee[type], lessee[name], driver, vehicle, starting_point_id, boarding_point, disembark_point, drafted_at, started_at, ended_at, price.
- v3.2 adds `/ops/edxeix-form-contract.php` to compare local payload preview field names with the observed EDXEIX form contract.
- No POST or live submit exists in this patch.
- No eligible future-safe Bolt candidate exists yet.

Primary safe entry:
`https://gov.cabnet.app/ops/home.php`

Form contract verifier:
`https://gov.cabnet.app/ops/edxeix-form-contract.php`

Do not enable live submission unless Andreas explicitly requests it after a real eligible future Bolt trip passes preflight and EDXEIX form access remains confirmed.
