# Current Production-Prep Baseline — gov.cabnet.app Bolt → EDXEIX

Last updated: 2026-04-25

## Status

The bridge is in a safe pre-production state.

Ready:

- EDXEIX submit URL is configured server-side.
- EDXEIX Cookie/CSRF session is saved server-side and reports ready.
- Safe Operations Console exists.
- Future Test Checklist exists.
- Mapping dashboard/editor exists.
- EDXEIX Session readiness/save form exists.
- Disabled Live Submit Gate exists.
- LAB/dry-run cleanup has been validated.
- Historical/terminal rows are blocked.
- Live submit gate no longer auto-selects historical rows.

Still blocked:

- No real future Bolt candidate exists yet.
- Live HTTP transport is intentionally not enabled.
- Server live and HTTP flags remain disabled.
- Final live-submit transport patch is still required after real test validation.

## Expected current gate state

```text
EDXEIX URL configured: yes
EDXEIX session ready: yes
Real future candidates: 0
Live-eligible rows: 0
Live HTTP execution: no
```

Expected remaining blockers:

```text
live_submit_config_disabled
http_submit_config_disabled
no_real_future_candidate
no_selected_real_future_candidate
http_transport_not_enabled_in_this_patch
```

## Next operational dependency

A real future Bolt ride must be created with Filippos.

Recommended:

```text
Filippos Giannakopoulos → EDXEIX driver 17585
EMX6874 → EDXEIX vehicle 13799
or EHA2545 → EDXEIX vehicle 5949
Start time: 40–60 minutes in the future
```

## Final-live phase

Do not implement or enable live HTTP transport until Andreas explicitly approves the final live-submit patch after a real future Bolt candidate appears and passes dry-run/preflight validation.
