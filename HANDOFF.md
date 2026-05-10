# gov.cabnet.app — Bolt → EDXEIX Bridge Handoff

## Current Version

v6.6.9 — ASAP office workflow: pre-ride email utility now supports read-only DB ID lookup and latest server Maildir email loading.

## Project Identity

- Domain: https://gov.cabnet.app
- GitHub repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow.
- Server layout:
  - `/home/cabnet/public_html/gov.cabnet.app`
  - `/home/cabnet/gov.cabnet.app_app`
  - `/home/cabnet/gov.cabnet.app_config`
  - `/home/cabnet/gov.cabnet.app_sql`

## Workflow

Andreas downloads zip patches, extracts them into the local GitHub Desktop repo, uploads changed files manually to production, tests production, then commits through GitHub Desktop.

## Production Safety Status

- EDXEIX live backend automation remains disabled.
- The Firefox helper may POST the visible EDXEIX form only after a logged-in operator clicks **POST / Save reviewed form** and confirms.
- `submission_jobs` must remain zero unless Andreas explicitly approves main pipeline live testing.
- `submission_attempts` must remain zero unless Andreas explicitly approves main pipeline live testing.
- AADE issuing remains strictly via the Bolt API pickup timestamp worker path.
- Pre-ride Bolt email must never issue AADE receipts.
- No server-side EDXEIX tokens, cookies, CSRF tokens, or sessions are copied/exposed.

## v6.6.9 Operational Shortcut

Main page:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
https://gov.cabnet.app/ops/pre-ride-email-tool.php
```

New buttons:

```text
Load latest server email + DB IDs
Parse email + DB IDs
Save + open EDXEIX
```

Read-only DB lookup class:

```text
/home/cabnet/gov.cabnet.app_app/src/BoltMail/EdxeixMappingLookup.php
```

Latest server email loader:

```text
/home/cabnet/gov.cabnet.app_app/src/BoltMail/MaildirPreRideEmailLoader.php
```

CLI diagnostic:

```text
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_lookup.php
```

CLI tests:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_lookup.php < /path/to/email.txt
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_lookup.php --latest-maildir
```

## DB Mapping Tables Used

- `mapping_drivers.edxeix_driver_id`
- `mapping_vehicles.edxeix_vehicle_id`
- `mapping_starting_points.edxeix_starting_point_id`
- Optional new columns:
  - `mapping_drivers.edxeix_lessor_id`
  - `mapping_vehicles.edxeix_lessor_id`

Optional additive migration:

```text
/home/cabnet/gov.cabnet.app_sql/2026_05_10_edxeix_lessor_mapping_columns.sql
```

## Latest Maildir Loader

Default Maildir locations checked include:

```text
/home/cabnet/mail/gov.cabnet.app/bolt-bridge/new
/home/cabnet/mail/gov.cabnet.app/bolt-bridge/cur
```

Optional environment override:

```text
GOV_CABNET_PRERIDE_MAILDIR
```

The loader is read-only. It does not move/delete/mark mail and does not store mail.

## Correct Source Split

### EDXEIX

```text
Pre-ride Bolt email
→ manual/parser utility
→ DB mapping lookup for exact EDXEIX IDs
→ local Firefox helper fills visible EDXEIX form
→ operator verifies
→ operator-confirmed POST only
```

### AADE

```text
Bolt API pickup timestamp
→ bolt_pickup_receipt_worker.php
→ AADE invoice issue
```

AADE invoice source remains strictly the Bolt API pickup timestamp worker path.

## Current Known Issue / Next Check

If a pre-ride email contains a driver or vehicle not found in `mapping_drivers` / `mapping_vehicles`, the page will show **CHECK IDS** and will not silently guess.

For the latest sample:

```text
Driver: Efthymios Giakis
Vehicle: ITK7702
```

Use the v6.6.9 page or CLI to check whether these IDs exist in DB. If missing, add/update the mapping records safely.

## Next Safe Tasks

1. Upload v6.6.9 patch.
2. Reload Firefox helper from `tools/firefox-edxeix-autofill-helper/manifest.json`.
3. Open `https://gov.cabnet.app/ops/pre-ride-email-tool.php`.
4. Click **Load latest server email + DB IDs**.
5. Confirm IDs are resolved.
6. Click **Save + open EDXEIX**.
7. On EDXEIX, click **Fill using exact IDs**.
8. Verify form fields.
9. POST only after operator review.


## v6.6.9 hotfix

- Price ranges such as `40.00 - 44.00 eur` now resolve to the upper bound (`44.00`) for EDXEIX manual/autofill payloads, avoiding understated contract values.
- No DB writes, no AADE calls, no EDXEIX server-side calls, no queue jobs, and no submission attempts.
