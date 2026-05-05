# Bolt Mail Intake Production Notes

This patch adds the first production-safe email intake layer for Bolt pre-ride emails forwarded into:

```text
bolt-bridge@gov.cabnet.app
```

## Confirmed from live server

- Gmail filtered forwarding was configured from `mykonoscab@gmail.com` to `bolt-bridge@gov.cabnet.app`.
- Roundcube shows the forwarded `Fwd: Ride details` email inside `bolt-bridge@gov.cabnet.app`.
- WHM terminal confirmed readable Maildir files under:

```text
/home/cabnet/mail/gov.cabnet.app/bolt-bridge/new
/home/cabnet/mail/gov.cabnet.app/bolt-bridge/cur
```

- Read-only grep confirmed at least one mailbox file contains `Ride details`.

## What this patch does

- Scans the Bolt bridge mailbox Maildir.
- Detects likely Bolt `Ride details` emails.
- Parses only normalized fields needed for review.
- Stores parsed data in `bolt_mail_intake`.
- Marks past rides as blocked.
- Marks rides inside the future guard window as blocked too soon.
- Shows a guarded operations screen at:

```text
https://gov.cabnet.app/ops/mail-intake.php?key=YOUR_INTERNAL_API_KEY
```

## What this patch does not do

- It does not submit anything to EDXEIX.
- It does not create EDXEIX submission jobs.
- It does not alter existing Bolt API sync behavior.
- It does not store or display full raw email bodies.
- It does not require Composer, Node, or any framework.

## Security posture

The operations screen requires the server-only `app.internal_api_key` value from:

```text
/home/cabnet/gov.cabnet.app_config/config.php
```

Do not paste the real key into chat or commit it to GitHub.

## Email field mapping

```text
Operator               -> operator_raw
Customer               -> customer_name
Customer mobile        -> customer_mobile
Driver                 -> driver_name
Vehicle                -> vehicle_plate
Pickup                 -> pickup_address
Drop-off               -> dropoff_address
Start time             -> parsed_start_at
Estimated pick-up time -> parsed_pickup_at
Estimated end time     -> parsed_end_at
Estimated price        -> estimated_price_raw, optional
```

## Production safety rules

Allowed:

```text
Email received -> parsed -> visible in intake table -> readiness/preflight planning
```

Blocked:

```text
Email received -> live EDXEIX submission
```

Live EDXEIX submission remains disabled unless Andreas explicitly requests a live-submit update after a real eligible future trip passes preflight.
