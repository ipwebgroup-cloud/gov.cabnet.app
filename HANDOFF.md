# HANDOFF — gov.cabnet.app Bolt → EDXEIX bridge

Current state after v2.9:
- Live EDXEIX submission remains disabled.
- v2.8 showed EDXEIX is reachable with GET+follow, but current route appears to land on login/public shell rather than the authenticated lease form.
- v2.9 adds `/ops/edxeix-target-matrix.php` to probe all known target candidates with GET-only requests and classify them.
- No POST or live submit exists in this patch.
- No eligible live Bolt candidate exists yet.

Primary safe entry:
`https://gov.cabnet.app/ops/home.php`

Target matrix:
`https://gov.cabnet.app/ops/edxeix-target-matrix.php`

Do not enable live submission unless Andreas explicitly requests it after a real eligible future Bolt trip passes preflight.
