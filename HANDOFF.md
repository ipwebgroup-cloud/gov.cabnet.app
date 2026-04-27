# HANDOFF — gov.cabnet.app Bolt → EDXEIX bridge

Current state after v3.0:
- Live EDXEIX submission remains disabled.
- v2.9 target matrix showed all protected EDXEIX routes resolve to LOGIN_OR_SESSION_PAGE.
- The saved EDXEIX session is not authenticated enough for dashboard/form access.
- v3.0 adds `/ops/edxeix-session-refresh-checklist.php`, a checklist-only page explaining how to refresh the EDXEIX session using the browser extension.
- No backend calls or writes are performed by this page.
- No eligible future-safe Bolt candidate exists yet.

Primary safe entry:
`https://gov.cabnet.app/ops/home.php`

Session refresh checklist:
`https://gov.cabnet.app/ops/edxeix-session-refresh-checklist.php`

After refreshing the EDXEIX session, rerun:
`https://gov.cabnet.app/ops/edxeix-target-matrix.php?probe=1&follow=1&format=json`

Do not enable live submission unless Andreas explicitly requests it after a real eligible future Bolt trip passes preflight.
