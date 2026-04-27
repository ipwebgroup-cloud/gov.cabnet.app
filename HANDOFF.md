# HANDOFF — gov.cabnet.app Bolt → EDXEIX bridge

Current state after v3.1:
- Live EDXEIX submission remains disabled.
- v2.9 target matrix showed all protected EDXEIX routes resolve to LOGIN_OR_SESSION_PAGE.
- v3.0 added a session refresh checklist.
- v3.1 adds `/ops/extension-session-write-verification.php` to verify whether the Firefox extension actually refreshed `edxeix_session.json`.
- This page reads local session-file metadata only and does not show cookies/tokens/raw JSON.

Primary safe entry:
`https://gov.cabnet.app/ops/home.php`

Extension write verification:
`https://gov.cabnet.app/ops/extension-session-write-verification.php`

After refreshing the EDXEIX session, verify freshness, then rerun:
`https://gov.cabnet.app/ops/edxeix-target-matrix.php?probe=1&follow=1&format=json`

Do not enable live submission unless Andreas explicitly requests it after a real eligible future Bolt trip passes preflight and EDXEIX form access is confirmed.
