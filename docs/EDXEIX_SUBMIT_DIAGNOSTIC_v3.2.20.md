# EDXEIX Submit Diagnostic v3.2.20

Generated for Andreas on 2026-05-17.

## Purpose

This patch moves the gov.cabnet.app Bolt → EDXEIX bridge closer to full automation without enabling unattended live submission.

The immediate blocker after queue 2398 is that EDXEIX returned HTTP 302 but no remote/reference ID was captured and no saved contract was confirmed. HTTP 302 alone is not enough proof of success. It may mean success redirect, login/session timeout, CSRF rejection, validation failure, duplicate rejection, or another portal flow.

v3.2.20 adds a safe diagnostic layer to classify that redirect path.

## Safety posture

Default behavior is dry-run/read-only.

The web page:

- does not POST to EDXEIX;
- does not call Bolt;
- does not stage jobs;
- does not mutate queues;
- does not write database rows;
- does not print cookies, CSRF tokens, raw payload values, or raw EDXEIX HTML.

The CLI transport mode is blocked unless all of the following are true:

1. `live_submit_enabled = true` in server-only `/home/cabnet/gov.cabnet.app_config/live_submit.php`;
2. `http_submit_enabled = true`;
3. `edxeix_session_connected = true`;
4. one-shot lock points to the selected booking/order reference;
5. selected booking passes the existing live-submit gate;
6. selected booking is a real eligible future Bolt trip;
7. selected booking is not terminal, cancelled, expired, historical, lab/test, receipt-only, or Admin Excluded;
8. exact confirmation phrase is supplied from the terminal.

## Added tools

### Web diagnostic page

```text
https://gov.cabnet.app/ops/edxeix-submit-diagnostic.php
```

This page shows:

- selected booking/order reference;
- live safety gate summary;
- session readiness summary;
- payload field list and hash only;
- technical blockers;
- live blockers;
- copy/paste CLI commands for dry-run and supervised transport trace.

### CLI diagnostic tool

Dry-run analysis only:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php --booking-id=BOOKING_ID --json
```

Supervised one-shot transport trace, still blocked unless server-only gates are explicitly enabled:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php --booking-id=BOOKING_ID --transport=1 --confirm='I UNDERSTAND SUBMIT LIVE TO EDXEIX' --json
```

Disable redirect following for comparison:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php --booking-id=BOOKING_ID --transport=1 --confirm='I UNDERSTAND SUBMIT LIVE TO EDXEIX' --no-follow --json
```

## Classifications

The diagnostic classifies the result as one of the following:

```text
DRY_RUN_DIAGNOSTIC_ONLY
TRANSPORT_BLOCKED_BY_SAFETY_GATE
SUBMIT_REDIRECT_SUCCESS_CANDIDATE
SUBMIT_REDIRECT_LOGIN_REQUIRED
SUBMIT_REDIRECT_CSRF_OR_SESSION_REJECTED
SUBMIT_REDIRECT_VALIDATION_ERROR
SUBMIT_REDIRECT_UNKNOWN_FINAL_200
SUBMIT_REDIRECT_UNFOLLOWED_OR_OPAQUE
SUBMIT_HTTP_2XX_UNCLASSIFIED
SUBMIT_HTTP_ERROR
TRANSPORT_EXCEPTION
```

Important: `SUBMIT_REDIRECT_SUCCESS_CANDIDATE` is not final proof. It means the final page contains success/list signals and must be followed by read-only verifier/search proof before we call it saved.

## Why this is the ASAP path

The fastest safe route to automation is:

1. prove exactly what EDXEIX returns after POST;
2. capture redirect chain and final page classification;
3. confirm saved contract with verifier/list proof;
4. only then promote the process to controlled one-shot live submit;
5. only after repeated confirmed success, consider an unattended worker.

This patch completes step 1 infrastructure and prepares step 2.

## No SQL changes

This patch does not add or alter database tables.

A later patch may add a dedicated diagnostic evidence table after the trace format is proven useful.
