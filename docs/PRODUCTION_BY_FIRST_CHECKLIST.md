# Production by the 1st — Safe Remaining Checklist

This checklist focuses on what can be completed safely before the first real Bolt future ride and final EDXEIX live submission.

## Already completed

- Ops access guard installed and verified.
- Readiness dashboard installed.
- Future test checklist installed.
- Mapping dashboard/editor installed.
- Mapping JSON sanitized.
- Known EDXEIX driver reference panel installed.
- LAB dry-run booking harness installed and validated.
- LAB cleanup tool installed and validated.
- Safe `/ops/index.php` landing page installed.
- Novice help/guided dashboard installed.
- Disabled live-submit gate installed.
- `edxeix_live_submission_audit` table installed.

## Current required live-test inputs

Known safe driver:

```text
Filippos Giannakopoulos → EDXEIX 17585
```

Known safe vehicles:

```text
EMX6874 → EDXEIX 13799
EHA2545 → EDXEIX 5949
```

Leave unmapped:

```text
Georgios Zachariou / +306944787864 / XRO7604
```

## Remaining before first EDXEIX live submission

- Confirm a real future Bolt ride can be created with Filippos and a mapped vehicle.
- Confirm when the ride appears through the Bolt API after sync.
- Confirm `/ops/future-test.php` detects the candidate.
- Confirm `/bolt_edxeix_preflight.php?limit=30` builds the correct payload.
- Confirm EDXEIX session is ready server-side.
- Confirm the exact EDXEIX submit URL/form action.
- Apply final live HTTP transport patch only after explicit approval.
- Submit one controlled future booking only.
- Disable live-submit config immediately after the first controlled test.

## Do not do yet

- Do not enable live EDXEIX HTTP transport.
- Do not map Georgios without confirmed EDXEIX ID.
- Do not auto-stage or auto-submit from cron.
- Do not submit historical or completed Bolt rides.
- Do not expose cookies, tokens, API keys, or database credentials.
