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
- Bolt API Visibility Diagnostic v1.0 was uploaded and committed on 2026-04-25.
- Screenshots confirmed the diagnostic page works and records private sanitized timeline snapshots.
- The screenshots showed `orders_seen: 1`, `sanitized_samples: 0`, and watch matches `NO` for Filippos/EMX6874 while no active future test was available.

## This patch

Bolt API Visibility Diagnostic v1.1 adds:

- `diagnostic_version: 1.1.0`
- read-only latest `normalized_bookings` summaries
- `Local recent rows` metric
- `Dry-run sync explanation` section
- `Recent local normalized Bolt bookings` table
- timeline local row/status visibility

This explains the case where dry-run sync reports `orders_seen > 0` but does not expose order-like arrays for sanitized samples.

## Known mappings

- Filippos Giannakopoulos
  - Bolt UUID: `57256761-d21b-4940-a3ca-bdcec5ef6af1`
  - EDXEIX driver ID: `17585`
- EMX6874
  - EDXEIX vehicle ID: `13799`
- EHA2545
  - EDXEIX vehicle ID: `5949`

Leave Georgios Zachariou unmapped until his exact EDXEIX driver ID is independently confirmed.

## Next safest step

Use `/ops/bolt-api-visibility.php` during a real future/scheduled Bolt ride with Filippos and EMX6874. Record snapshots after:

1. accepted/assigned
2. pickup/waiting
3. trip started
4. completed

Then compare `Orders seen`, `Sanitized samples`, `Local recent rows`, and watch match badges.
