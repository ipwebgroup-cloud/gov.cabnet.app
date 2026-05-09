# EDXEIX Readiness Report v6.6.0

This read-only CLI report checks whether normalized Bolt bookings are ready for EDXEIX pre-live review.

## Safety

The script does not:

- call EDXEIX
- create `submission_jobs`
- create `submission_attempts`
- issue AADE receipts
- print cookies, CSRF tokens, API keys, or private config values

## Script

```text
/home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php
```

## Recommended commands

Syntax check:

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php
```

JSON report:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php --future-hours=72 --past-minutes=60 --limit=50 --json
```

Only bookings that pass preflight readiness:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php --only-ready --future-hours=168 --limit=100 --json
```

Queue safety confirmation:

```bash
mysql cabnet_gov -e "
SELECT COUNT(*) AS submission_jobs FROM submission_jobs;
SELECT COUNT(*) AS submission_attempts FROM submission_attempts;
"
```

## Interpreting output

`preflight_ready=true` means the booking appears structurally ready for EDXEIX review, ignoring the intentional live-submit blockers such as disabled config, missing one-shot lock, and disconnected EDXEIX session.

A booking is not ready when it is:

- receipt-only / mail-only
- not a real Bolt API booking
- lab/test
- terminal, cancelled, finished, expired, historical, or too close/past
- missing driver, vehicle, or starting-point mapping
- duplicate of an already successful EDXEIX submission attempt/audit

## Production rule

EDXEIX live submission remains disabled unless Andreas explicitly requests live-submit activation for one exact eligible future Bolt trip.
