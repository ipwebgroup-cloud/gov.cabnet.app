# EDXEIX Auto Live Worker v6.8.2

## Purpose

`edxeix_auto_live_worker.php` is the first one-command production worker for EDXEIX submission from Bolt pre-ride email.

It is designed to replace the live-pressure loop of:

1. import mail
2. preview/create booking
3. manual EDXEIX form typing
4. manual audit marking

## Source policy

- EDXEIX source: pre-ride Bolt email only.
- Bolt API is not an EDXEIX source.
- AADE invoice issuing remains separate and only through the Bolt API pickup timestamp worker.

## Safety

The worker:

- imports Bolt pre-ride email;
- finds exactly one currently-future ready mail candidate;
- creates/links one normalized booking;
- clears old no-EDXEIX flags only for that exact currently-future mail booking through the existing bridge;
- submits to the EDXEIX create form using the saved Firefox EDXEIX session;
- verifies the EDXEIX redirect/list page contains expected booking markers;
- marks the booking no-repeat only after UI/list verification;
- does not create `submission_jobs` or `submission_attempts`;
- does not print cookies, CSRF tokens, API keys, or response bodies;
- does not call AADE.

HTTP 302 alone is not counted as success.

## Commands

Dry-run:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_auto_live_worker.php --dry-run --json
```

Live submit:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_auto_live_worker.php --confirm-live='I UNDERSTAND AUTO LIVE EDXEIX' --json
```

## Expected success

```json
{
  "ok": true,
  "submitted": true,
  "confirmed_in_edxeix_ui": true
}
```

## Expected safe block

If no currently-future pre-ride email exists:

```json
{
  "ok": false,
  "error": "no_ready_pre_ride_mail_candidate"
}
```

If multiple future ready candidates exist, the worker blocks and refuses to choose.
