# gov.cabnet.app — Handoff

## Project

Bolt Fleet API → normalized local bookings → EDXEIX preflight/readiness workflow.

## Safety posture

The project remains at a pre-live blocked baseline.

- Do not enable live EDXEIX submission.
- Keep `live_submit_enabled` false.
- Keep `http_submit_enabled` false.
- Do not submit historical, cancelled, terminal, expired, invalid, or past Bolt trips.
- Do not expose real secrets, cookies, CSRF values, API keys, DB passwords, or raw session files.

## Current confirmed state

- Ops console is guarded under `/ops/`.
- EDXEIX session capture works and session readiness has previously shown ready.
- Manual Cookie/CSRF entry was removed from `/ops/edxeix-session.php`.
- Live HTTP transport remains intentionally blocked.
- Bolt API Visibility Diagnostic v1.1 works and records private sanitized timeline snapshots.
- Bolt Dev Accelerator v1.2 was uploaded, syntax-checked, and committed.
- Bolt Evidence Bundle v1.3 was uploaded, syntax-checked, and committed.
- Bolt Evidence Report Export v1.4 was uploaded, syntax-checked, and committed.
- Bolt Test Session Control v1.5 adds `/ops/test-session.php` as a low-risk single workflow launcher.

## Latest observed state from server screenshots/output

- Readiness verdict: `READY_FOR_REAL_BOLT_FUTURE_TEST`.
- Driver mappings: `1/2`.
- Vehicle mappings: `2/15`.
- Real future candidates: `0`.
- LAB rows/jobs: `0`.
- Local submission jobs: `0`.
- Live attempts indicated: `0`.
- Evidence Bundle was waiting for evidence because no sanitized snapshots existed yet for the date.

## Known mappings

- Filippos Giannakopoulos
  - Bolt UUID: `57256761-d21b-4940-a3ca-bdcec5ef6af1`
  - EDXEIX driver ID: `17585`
- EMX6874
  - EDXEIX vehicle ID: `13799`
- EHA2545
  - EDXEIX vehicle ID: `5949`

Leave Georgios Zachariou unmapped until his exact EDXEIX driver ID is independently confirmed.

## Current workflow pages

```text
/ops/test-session.php          v1.5 workflow launcher
/ops/dev-accelerator.php       v1.2 capture cockpit
/ops/evidence-bundle.php       v1.3 evidence summary
/ops/evidence-report.php       v1.4 Markdown/JSON report exporter
/ops/bolt-api-visibility.php   sanitized visibility diagnostic
/ops/future-test.php           guided future test checklist
/ops/readiness.php             readiness audit UI
/ops/mappings.php              guarded mapping dashboard
/ops/jobs.php                  jobs visibility
```

## Next safest step

Use `/ops/test-session.php` as the main entry point during a real future/scheduled Bolt ride with Filippos and EMX6874. Record snapshots after:

1. accepted/assigned
2. pickup/waiting
3. trip started
4. completed

Then open `/ops/evidence-report.php?format=md`, copy the Markdown report, and paste it into the next chat.
