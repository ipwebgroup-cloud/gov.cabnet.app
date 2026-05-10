# Bolt Pre-Ride Email Utility v6.6.9

## Purpose

This utility helps office staff create an EDXEIX rental-agreement form from the Bolt pre-ride email with fewer manual steps.

The page is:

```text
https://gov.cabnet.app/ops/pre-ride-email-tool.php
```

## v6.6.9 workflow

1. Click **Load latest server email + DB IDs** if the Bolt pre-ride email exists in the server Maildir.
2. Otherwise paste the email manually and click **Parse email + DB IDs**.
3. Verify the extracted transfer data.
4. Confirm that **Company / lessor ID**, **Driver ID**, and **Vehicle ID** are filled from the database.
5. Click **Save + open EDXEIX**.
6. On EDXEIX, click **Fill using exact IDs**.
7. Verify every field.
8. Click **POST / Save reviewed form** only after review.

## Safety boundaries

The tool performs read-only assistance only:

- It does not write to the database.
- It does not create `submission_jobs`.
- It does not create `submission_attempts`.
- It does not call EDXEIX from the server.
- It does not call AADE.
- It does not expose EDXEIX cookies, CSRF tokens, passwords, or sessions.
- It does not auto-submit without operator confirmation.

The only DB usage is read-only lookup from mapping tables.

## DB lookup sources

The lookup checks:

- `mapping_drivers.edxeix_driver_id`
- `mapping_vehicles.edxeix_vehicle_id`
- optional `mapping_drivers.edxeix_lessor_id`
- optional `mapping_vehicles.edxeix_lessor_id`
- `mapping_starting_points.edxeix_starting_point_id`

If `edxeix_lessor_id` columns are missing, the helper falls back to known operator aliases such as LUXLIMO → 3814.

## Latest server email loader

The loader checks common cPanel Maildir locations, especially:

```text
/home/cabnet/mail/gov.cabnet.app/bolt-bridge/new
/home/cabnet/mail/gov.cabnet.app/bolt-bridge/cur
```

It can also use the environment variable:

```text
GOV_CABNET_PRERIDE_MAILDIR
```

Multiple directories may be separated using the server path separator.

The loader only reads matching candidate files. It does not mark mail as read, move mail, delete mail, or store mail.

## CLI verification

Parse pasted email from STDIN:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_lookup.php < /path/to/email.txt
```

Load latest matching server Maildir email:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_lookup.php --latest-maildir
```


## v6.6.9 hotfix

- Price ranges such as `40.00 - 44.00 eur` now resolve to the upper bound (`44.00`) for EDXEIX manual/autofill payloads, avoiding understated contract values.
- No DB writes, no AADE calls, no EDXEIX server-side calls, no queue jobs, and no submission attempts.
