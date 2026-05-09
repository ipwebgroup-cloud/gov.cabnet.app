# EDXEIX Mail Preflight Bridge v6.7.2

## Purpose

`edxeix_mail_preflight_bridge.php` is a safe CLI tool for the EDXEIX source path.

It uses **pre-ride Bolt email intake rows only**.

It does not use Bolt API rides as the EDXEIX data source.

## Source Split

- EDXEIX source: pre-ride Bolt email only.
- AADE invoice source: Bolt API pickup timestamp worker only.
- Pre-ride email must never issue AADE invoices.
- Bolt API finished/past rides must never be submitted to EDXEIX.

## v6.7.2 Safety Hardening

v6.7.2 rejects malformed or placeholder CLI arguments.

In particular:

- `--intake-id=ID` is rejected.
- `--create--json` is rejected as a malformed option.
- `--create` requires one explicit numeric `--intake-id`.
- Bulk create mode is disabled.
- The script writes at most one normalized preflight booking per command.

## Safety Guarantees

The script:

- does not call EDXEIX;
- does not issue AADE receipts;
- does not create `submission_jobs`;
- does not create `submission_attempts`;
- does not print session cookies, CSRF tokens, API keys, or private config values.

## Preview Commands

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php --limit=20 --json
```

Preview one intake row:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php --intake-id=123 --json
```

## Create Command

Only after previewing and reviewing one future pre-ride email row:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php --intake-id=123 --create --json
```

Then run:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php --only-ready --future-hours=168 --limit=100 --json
```

## Expected Current Result

When no current future pre-ride email exists:

```text
candidate_rows: 0
preview_ready: 0
created_bookings: 0
queues_unchanged: true
```

## Commit Note

This is a safety hardening patch only. It does not activate live EDXEIX submission.


## v6.7.2 hardening

When `--intake-id` is supplied, the script now treats a missing intake row as an error (`requested_intake_id_not_found`). This prevents placeholder or mistyped numeric ids from returning `ok: true` with zero work performed.
