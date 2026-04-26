# gov.cabnet.app — Handoff

## Project

Bolt Fleet API → normalized local bookings → EDXEIX preflight/readiness workflow.

## Safety posture

The project remains pre-live blocked.

- Do not enable live EDXEIX submission.
- Keep `live_submit_enabled` false.
- Keep `http_submit_enabled` false.
- Historical, cancelled, terminal, expired, invalid, LAB/test, or past Bolt trips must never be submitted.
- Do not expose real secrets, cookies, CSRF values, API keys, DB passwords, or raw session files.

## Current confirmed state

- Ops console is guarded under `/ops/`.
- EDXEIX session capture works.
- Live HTTP transport remains intentionally blocked.
- Bolt API Visibility Diagnostic v1.1 is available.
- Bolt Dev Accelerator v1.2 is available.
- Bolt Evidence Bundle v1.3 is available.
- Bolt Evidence Report Export v1.4 is available.
- Bolt Test Session Control v1.5 is available.
- Bolt Preflight Review Assistant v1.6 is now added.
- Latest observed readiness: `READY_FOR_REAL_BOLT_FUTURE_TEST`.
- Latest observed candidates: `0`.
- Current blocker: no real future Bolt candidate yet.

## v1.6 patch

Adds `/ops/preflight-review.php`.

Purpose:

- explain preflight readiness in operator language
- show whether a real future candidate exists
- show mapping/future guard/terminal/blocker state
- link to raw preflight JSON only
- keep live submission disabled

## Next safest step

When a real future Bolt ride is available:

1. Open `/ops/test-session.php`.
2. Capture accepted/assigned.
3. Capture pickup/waiting.
4. Capture trip started.
5. Capture completed.
6. Open `/ops/evidence-report.php?format=md`.
7. Paste the Markdown evidence report into chat.
8. Review `/ops/preflight-review.php`.
9. Do not submit live.
