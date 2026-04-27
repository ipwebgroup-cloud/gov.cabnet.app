# HANDOFF — gov.cabnet.app Bolt → EDXEIX bridge

Current state after v2.5:
- Live EDXEIX submission remains disabled.
- Real Bolt test on 2026-04-27 captured evidence.
- Evidence indicates watched ride appeared only after completion/auto-watch, not during accepted/pickup/started stages.
- Preflight correctly blocked all rows as not future-safe and terminal/historical.
- v2.5 adds `/ops/completed-visibility.php` to document this finding.

Primary safe entry:
`https://gov.cabnet.app/ops/home.php`

Completed-order visibility analysis:
`https://gov.cabnet.app/ops/completed-visibility.php`

Do not enable live submission unless Andreas explicitly requests it after a real eligible future Bolt trip passes preflight.
