# Bolt API Visibility Diagnostic

This diagnostic is a guarded, read-only ops tool for proving when the current Bolt Fleet orders endpoint exposes a trip.

## Safety posture

- Does not submit to EDXEIX.
- Does not enable live EDXEIX submission.
- Does not stage queue jobs.
- Uses the existing Bolt sync path in dry-run mode only.
- Does not print raw Bolt payloads.
- Does not print secrets, cookies, CSRF values, tokens, passenger names, emails, or phone numbers.
- Optional timeline recording stores sanitized JSONL summaries only under private app storage.

## Files

- `public_html/gov.cabnet.app/ops/bolt-api-visibility.php`
- `public_html/gov.cabnet.app/ops/bolt-api-visibility-run.php`
- `gov.cabnet.app_app/lib/bolt_visibility_diagnostic.php`

## URL

```text
https://gov.cabnet.app/ops/bolt-api-visibility.php
```

## Recommended test sequence

1. Open the diagnostic page before the Bolt test ride.
2. Use **Watch Filippos every 20s** only while performing the test.
3. Capture/record one snapshot after each visible operational state:
   - ride accepted/assigned
   - passenger picked up / waiting
   - trip started
   - trip completed
4. Compare:
   - `Orders seen`
   - `Sanitized samples`
   - `Local recent rows`
   - watch match badges for order, driver, and vehicle

## Current v1.1 behaviour

The first diagnostic version proved the page works and can record a private timeline. Screenshots from 2026-04-25 showed:

```text
orders_seen: 1
sanitized_samples: 0
recorded: yes
watch matches: no
```

That means the dry-run sync result can report that Bolt returned/imported at least one order, while not exposing order-like arrays in the wrapper output for the diagnostic parser to summarize.

Version 1.1 therefore adds a second read-only view:

```text
Recent local normalized Bolt bookings
```

This reads the latest safe summary fields from `normalized_bookings` after the dry-run probe. It helps confirm what the sync imported without printing raw Bolt payloads.

## Private artifacts

When `record=1`, snapshots are appended to:

```text
/home/cabnet/gov.cabnet.app_app/storage/artifacts/bolt-api-visibility/YYYY-MM-DD.jsonl
```

These files are private server artifacts and should not be committed to Git.

## Known watch values for first test

```text
Driver: Filippos Giannakopoulos
Bolt UUID: 57256761-d21b-4940-a3ca-bdcec5ef6af1
Vehicle: EMX6874
EDXEIX driver ID: 17585
EDXEIX vehicle ID: 13799
```

## Important boundary

This diagnostic must remain an observation tool only. It is not a live submit tool.
