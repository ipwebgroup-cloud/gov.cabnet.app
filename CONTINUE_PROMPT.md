# Continue Prompt — gov.cabnet.app Bolt → EDXEIX Bridge

You are continuing the gov.cabnet.app Bolt → EDXEIX bridge project.

## Project identity

- Domain: `https://gov.cabnet.app`
- Repo: `https://github.com/ipwebgroup-cloud/gov.cabnet.app`
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload
- No frameworks, Composer, Node, or heavy dependencies unless Andreas explicitly approves.

## Current state

The system is prepared for a real future Bolt preflight test but live EDXEIX submission is still disabled.

Validated pages/tools:

```text
/ops/index.php              Guided operations console
/ops/help.php               Novice operator guide
/ops/readiness.php          Readiness audit
/ops/future-test.php        Future Bolt test checklist
/ops/mappings.php           Mapping dashboard/editor
/ops/jobs.php               Jobs/attempts viewer
/ops/live-submit.php        Disabled live-submit gate
/ops/edxeix-session.php     EDXEIX session/readiness helper
```

Known mappings:

```text
Filippos Giannakopoulos → 17585
EMX6874 → 13799
EHA2545 → 5949
```

Georgios Zachariou remains intentionally unmapped.

## Live submit status

- `/ops/live-submit.php` is installed but blocked.
- `live_http_transport_enabled_in_this_patch` is false.
- Current blockers expected until final phase include no real future candidate, EDXEIX submit URL missing, EDXEIX session not ready, and final HTTP transport not implemented.
- `/ops/edxeix-session.php` helps inspect server-side session/submit URL readiness without exposing secrets.

## Next best step

If there is no real future Bolt ride yet, continue only with safe production-readiness work.

Possible next safe step:

```text
Refine documentation and production checklist, or prepare the final HTTP transport patch as disabled code only.
```

Do not submit to EDXEIX live until Andreas explicitly approves and a real eligible future Bolt candidate exists.
