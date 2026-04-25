# Live Submit Global Session Status Refinement

This patch refines `/ops/live-submit.php` so global EDXEIX session readiness is displayed independently from candidate selection.

## Why

After the EDXEIX session and submit URL were saved successfully, `/ops/edxeix-session.php` correctly showed:

- Session cookie/CSRF ready: yes
- Submit URL configured: yes

But `/ops/live-submit.php` still showed the `EDXEIX session ready` requirement as `waiting` because it was reading readiness only from the selected booking analysis. When no real future Bolt candidate existed, no booking was selected, so the session requirement looked incomplete.

## Change

`/ops/live-submit.php` now:

- reads global server-side EDXEIX session readiness directly from `live_submit.php` and `edxeix_session.json`
- reports global session readiness in JSON as `global_session_state`
- marks `EDXEIX session ready` as pass when the session is truly ready, even if no Bolt candidate exists yet
- keeps candidate-specific items waiting until a real future Bolt ride appears
- keeps live HTTP transport blocked

## Safety

This patch does not call Bolt, does not call EDXEIX, does not write to the database, does not create jobs, does not expose cookies/CSRF tokens, and does not enable live submission.
