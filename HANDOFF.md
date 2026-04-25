# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

Current baseline after this patch:

- Operations console is guarded and novice-friendly.
- Readiness and Future Test pages are in place.
- Mapping dashboard/editor is in place.
- EDXEIX session helper can save submit URL, Cookie header, and CSRF token server-side.
- EDXEIX prerequisites are expected to be ready after valid session values are saved.
- `/ops/live-submit.php` now shows global EDXEIX session readiness independently from candidate selection.
- Live EDXEIX HTTP transport remains intentionally blocked.
- No real future Bolt candidate exists yet.

Known good first-test mapping:

- Filippos Giannakopoulos → EDXEIX driver ID 17585
- EMX6874 → EDXEIX vehicle ID 13799
- EHA2545 → EDXEIX vehicle ID 5949

Do not use Georgios Zachariou yet; leave him unmapped until his exact EDXEIX driver ID is confirmed.

Remaining blockers before first live EDXEIX submission:

1. Create a real future Bolt ride with Filippos and a mapped vehicle.
2. Confirm it appears in Bolt sync / normalized bookings.
3. Confirm preflight payload is technically valid.
4. Apply final HTTP transport patch only after explicit approval.
5. Enable live flags only for one approved controlled submission.

Safety: no live EDXEIX submission is currently possible from the installed preparatory patches.
