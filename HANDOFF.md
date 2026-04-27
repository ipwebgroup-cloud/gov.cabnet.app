# HANDOFF — gov.cabnet.app Bolt → EDXEIX bridge

Current state after v2.8:
- Live EDXEIX submission remains disabled.
- v2.7 proved the configured EDXEIX target is reachable but returned a 302 with no form confirmation.
- v2.8 adds `/ops/edxeix-redirect-probe.php` for GET-only redirect-follow inspection.
- Default page load does not call EDXEIX.
- `?probe=1` performs GET only.
- `?probe=1&follow=1` follows redirects, still GET only.
- No POST or live submit exists in this patch.

Primary safe entry:
`https://gov.cabnet.app/ops/home.php`

Redirect GET probe:
`https://gov.cabnet.app/ops/edxeix-redirect-probe.php`

Do not enable live submission unless Andreas explicitly requests it after a real eligible future Bolt trip passes preflight.
