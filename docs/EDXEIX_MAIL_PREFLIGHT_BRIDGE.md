# EDXEIX Mail Preflight Bridge v6.7.0

## Purpose

`edxeix_mail_preflight_bridge.php` creates the missing safe operational step between Bolt pre-ride email intake and EDXEIX readiness review.

It is used for:

1. Finding future, unlinked `bolt_mail_intake` rows.
2. Previewing whether each row can safely become a local normalized booking.
3. Creating that local normalized booking only when `--create` is explicitly supplied.

## Source Policy

- EDXEIX submission source: pre-ride Bolt email only.
- Bolt API is not an EDXEIX submission source.
- AADE invoice source: Bolt API pickup timestamp worker only.
- Pre-ride email must never issue AADE invoices.

## Safety

The script does not:

- call EDXEIX;
- issue AADE receipts;
- create `submission_jobs`;
- create `submission_attempts`;
- print cookies, CSRF tokens, API keys, or private config values.

Default mode is preview-only.

## Commands

Preview current future unlinked mail candidates:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php --limit=20 --json
```

Preview one intake row:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php --intake-id=123 --json
```

Create one local normalized EDXEIX preflight booking after preview review:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php --intake-id=123 --create --json
```

Create all currently eligible future unlinked rows, limited to 20:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php --limit=20 --create --json
```

After creation, run:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php --only-ready --future-hours=168 --limit=100 --json
```

Then, for one ready booking only, run analyze-only:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/live_submit_one_booking.php --booking-id=ID --analyze-only
```

## Live Submission

This script does not enable live submission. Live EDXEIX submission still requires Andreas to explicitly approve one exact eligible future booking and the existing guarded one-shot live gate.
