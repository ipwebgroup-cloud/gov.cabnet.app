# gov.cabnet.app — Bolt → EDXEIX Bridge Handoff

## Current validated baseline

- Domain: https://gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- Ops guard: active
- Operations console: novice guided dashboard active
- Readiness: clean and waiting for a real future Bolt ride
- Future test: ready to create real future test ride
- Live EDXEIX submission: disabled and blocked
- Live submit gate: installed as a disabled production scaffold

## Current mappings

Known mapped test path:

- Filippos Giannakopoulos → EDXEIX driver ID `17585`
- EMX6874 → EDXEIX vehicle ID `13799`
- EHA2545 → EDXEIX vehicle ID `5949`

Leave Georgios Zachariou unmapped until the exact EDXEIX driver ID is independently confirmed.

## Latest patch state

The live-submit gate was refined so old finished/cancelled/terminal Bolt rows are treated only as analyzed rows, not as real future candidates. By default, `/ops/live-submit.php` should no longer auto-select historical blocked rows when no technical real future candidate exists.

Expected current live gate state until the real future Bolt ride exists:

- analyzed recent rows may show historical rows
- real future candidates: `0`
- live-eligible rows: `0`
- no selected booking by default
- live HTTP transport blocked
- no EDXEIX request performed

## Remaining production blockers

Before real EDXEIX submission can be attempted:

1. Create a real future Bolt ride using Filippos and a mapped vehicle.
2. Confirm it appears through Bolt sync.
3. Confirm Future Test and Preflight pass.
4. Confirm EDXEIX session/cookie/CSRF readiness server-side.
5. Configure exact EDXEIX submit URL server-side.
6. Apply a separate final HTTP transport patch.
7. Enable live only for a one-shot approved submission.
8. Submit once, audit, then disable live flags again.

## Safety boundary

Do not enable live EDXEIX submission unless Andreas explicitly asks for the final live-submit transport patch and a real eligible future Bolt trip exists.
