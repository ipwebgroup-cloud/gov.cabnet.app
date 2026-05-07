# gov.cabnet.app — v4.7 Production Hardening / Launch Control Panel

Date: 2026-05-07

## Purpose

v4.7 moves the Bolt mail bridge from feature validation into production hardening without enabling live EDXEIX submission.

The new launch control panel is read-only and gives Andreas one place to confirm:

- dry-run mode is still enabled
- live EDXEIX submit is still disabled
- submission jobs and attempts remain zero
- mail intake, auto dry-run, and driver directory sync crons are fresh
- driver notifications are enabled
- driver email routing uses Bolt driver identity data, not vehicle plate matching
- driver directory has name/identifier/email coverage
- latest mail intake, notification, and dry-run evidence rows are visible
- credential rotation is still a required manual gate before any live-submit phase

## New URL

```text
https://gov.cabnet.app/ops/launch-readiness.php?key=INTERNAL_API_KEY
```

JSON view:

```text
https://gov.cabnet.app/ops/launch-readiness.php?key=INTERNAL_API_KEY&format=json
```

## Safety contract

The page does not:

- import mail
- send driver emails
- create normalized bookings
- create dry-run evidence
- create submission_jobs
- create submission_attempts
- call Bolt
- call EDXEIX
- submit anything live

## v4.6 conditional result

A paid real future Bolt ride could not be used for the future-candidate evidence gate because Bolt required card payment.

Validated real-world pieces:

- real Bolt pre-ride email arrival
- mail import cron
- past/too-late blocking
- Bolt driver directory sync
- driver identity email routing
- driver-facing email copy
- updated driver-facing email formatting
- zero submission_jobs
- zero submission_attempts
- no EDXEIX POST

## Launch rule

Do not move to live-submit work until:

- v4.7 launch control panel is green under normal cron operation
- exposed credentials/ops keys are rotated
- v5 live-submit worker is explicitly requested
- live-submit design includes strict future guard, idempotency, terminal-status blocking, synthetic/test blocking, emergency kill switch, and EDXEIX session validation
