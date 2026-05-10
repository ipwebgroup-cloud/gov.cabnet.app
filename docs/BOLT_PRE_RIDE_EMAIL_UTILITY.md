# Bolt Pre-Ride Email Manual Utility

Version: v6.6.2

## Purpose

This utility gives operations a fast manual fallback while the full Bolt → normalized booking → EDXEIX workflow remains guarded.

The operator can paste the Bolt pre-ride email body into:

```text
https://gov.cabnet.app/ops/pre-ride-email-tool.php
```

The page extracts the main transfer fields and fills an editable operator form for manual verification, dispatch copy/paste, spreadsheet copy/paste, and manual EDXEIX entry.

## Safety posture

The utility is intentionally simple and safe:

- No database access.
- No database writes.
- No network calls.
- No Bolt API calls.
- No EDXEIX calls.
- No AADE calls.
- No queue jobs.
- No submission attempts.
- No email body storage.
- POST body only; extracted values are shown back to the operator for manual review.

## Files

```text
gov.cabnet.app_app/src/BoltMail/BoltPreRideEmailParser.php
public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
gov.cabnet.app_app/cli/parse_pre_ride_email.php
```

## Web usage

1. Open:

```text
https://gov.cabnet.app/ops/pre-ride-email-tool.php
```

2. Paste the full Bolt pre-ride email body.
3. Press **Parse email**.
4. Check the parser confidence and missing fields.
5. Review and edit the populated operator form.
6. Copy individual fields, the dispatch summary, or the CSV row.
7. Use the verified values for manual business operations.

## CLI usage

From the server:

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/BoltMail/BoltPreRideEmailParser.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
php -l /home/cabnet/gov.cabnet.app_app/cli/parse_pre_ride_email.php
```

Parse from a text file:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/parse_pre_ride_email.php --file=/tmp/bolt-email.txt --json
```

Parse from STDIN:

```bash
cat /tmp/bolt-email.txt | /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/parse_pre_ride_email.php --json
```

## Expected extracted fields

The parser looks for labels such as:

```text
Operator:
Customer:
Customer mobile:
Driver:
Vehicle:
Pickup:
Drop-off:
Start time:
Estimated pick-up time:
Estimated end time:
Estimated price:
```

It accepts minor variations such as `Dropoff`, `Drop off`, `Customer phone`, and `Estimated pickup time`.

## Important limitation

This utility does not prove that a trip is eligible for live EDXEIX submission. It is a manual data extraction assistant only.

Live EDXEIX submission remains blocked unless Andreas explicitly approves a later live-submit change and all production guards pass.
