# gov.cabnet.app — Bolt → EDXEIX Bridge Handoff

## Current validated baseline

The project is in a safe pre-production state.

- Domain: `https://gov.cabnet.app`
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- Live EDXEIX submission: disabled
- Live HTTP transport: blocked by design
- Ops access guard: active
- LAB/test dry-run tooling: validated and cleaned
- Mapping dashboard/editor: active
- Live submit gate: installed, disabled, and safely blocked
- EDXEIX session readiness helper: added at `/ops/edxeix-session.php`

## Key current pages

```text
/ops/index.php              Guided operations console
/ops/help.php               Novice help/glossary
/ops/readiness.php          Readiness audit
/ops/future-test.php        Real future Bolt test checklist
/ops/mappings.php           Mapping coverage/editor
/ops/jobs.php               Local job/attempt viewer
/ops/live-submit.php        Disabled live submit gate
/ops/edxeix-session.php     EDXEIX session / submit URL readiness helper
```

## Known mappings

```text
Filippos Giannakopoulos → EDXEIX driver 17585
EMX6874 → EDXEIX vehicle 13799
EHA2545 → EDXEIX vehicle 5949
```

Leave Georgios Zachariou unmapped for now.

## Remaining blockers before actual live EDXEIX submission

```text
1. A real future Bolt candidate must exist.
2. EDXEIX cookie/CSRF session must be ready server-side.
3. Exact EDXEIX submit/action URL must be configured server-side.
4. Final HTTP transport patch must be explicitly approved and installed.
5. Live-submit config must be enabled only for a controlled one-shot test.
```

## Safety rule

Do not enable or implement live EDXEIX HTTP submission unless Andreas explicitly approves the final live-submit transport patch and a real future eligible Bolt booking exists.
