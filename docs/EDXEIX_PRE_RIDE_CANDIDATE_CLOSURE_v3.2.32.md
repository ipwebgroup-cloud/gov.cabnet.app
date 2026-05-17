# EDXEIX Pre-Ride Candidate Closure v3.2.32

## Purpose

Fixes the v3.2.31 manual V0 closure write when `submitted_at` is omitted by the CLI.

The v3.2.31 validation showed the closure table installed and syntax checks passed, but the CLI default sent an empty string for `submitted_at`, causing MariaDB to reject the insert with `Incorrect datetime value: ''`. v3.2.32 normalizes `submitted_at` safely:

- Empty value -> current server time.
- Parseable value -> normalized `Y-m-d H:i:s`.
- Invalid value -> current server time with warning.

## Safety

No EDXEIX POST is performed.
No AADE/myDATA call is performed.
No queue job is created.
No normalized booking is written.
No live config is changed.
V0 production remains untouched.

## Validation

Run:

```bash
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_candidate_closure_lib.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_mark_manual.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-candidate-closure.php
```

Then mark candidate 4:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_mark_manual.php \
  --candidate-id=4 \
  --method=v0_laptop_manual \
  --submitted-by=Andreas \
  --note='Real ride submitted manually through V0/laptop after server POST returned HTTP 419 session expired.' \
  --json
```

Expected: `ok: true`, `closure_status: manual_submitted_v0`, candidate archived for retry prevention.
