# EDXEIX Submit Diagnostic v3.2.21 — ASAP Candidate Discovery + Guard Floor

Generated for Andreas on 2026-05-17.

## Purpose

This patch keeps the gov.cabnet.app Bolt → EDXEIX bridge moving toward full automation while preserving the production safety posture.

v3.2.21 improves the v3.2.20 submit diagnostic after live server validation showed the dry-run tool selected an old finished/test-like booking by default and inherited a `0 min future` guard from current configuration.

## What changed

- Adds candidate discovery to the EDXEIX diagnostic CLI and web page.
- Stops the diagnostic from defaulting to an arbitrary recent/stale booking when no explicit booking is selected.
- Auto-selects only a real future Bolt candidate that passes diagnostic readiness filters.
- Applies a diagnostic +30 minute minimum future guard even if current config reports a lower value.
- Blocks diagnostic transport when the configured future guard is below +30 minutes.
- Requires explicit booking/order selection before any diagnostic transport trace can run.
- Adds visible diagnostic safety blockers separate from existing live-gate blockers.

## Safety posture

- Default mode remains dry-run/read-only.
- Web page never performs EDXEIX transport.
- CLI transport remains blocked unless:
  - `--transport=1` is passed;
  - the exact server-only confirmation phrase is passed;
  - the selected booking/order is explicit or server-only one-shot allowed;
  - existing live gates allow the booking;
  - diagnostic safety blockers are empty;
  - configured future guard is at least +30 minutes;
  - selected booking is a real mapped future Bolt trip;
  - booking is not historical, terminal, cancelled, expired, test/lab, receipt-only, or past.

## Main commands

Candidate discovery:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php --json --list-candidates=1 --limit=75
```

Dry-run selected booking:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php --booking-id=BOOKING_ID --json
```

Supervised transport trace remains blocked unless all gates pass:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php --booking-id=BOOKING_ID --transport=1 --confirm='I UNDERSTAND SUBMIT LIVE TO EDXEIX' --json
```

## Expected result after deployment

If no safe future Bolt candidate exists, the diagnostic should return:

```text
NO_SAFE_CANDIDATE_AVAILABLE
```

If current config has `future_start_guard_minutes = 0`, the diagnostic should show:

```text
future_guard_floor_applied: true
configured_future_guard_below_30_minimum
```

This is intentional. It keeps the ASAP path moving without allowing unsafe live POST attempts.
