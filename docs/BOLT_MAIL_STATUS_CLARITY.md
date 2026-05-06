# Bolt Mail Status Dashboard v4.1

Read-only dashboard polish for the Bolt pre-ride mail intake layer.

## Purpose

v4.1 improves operator clarity after synthetic and local preflight-booking tests.

It separates:

- active unlinked `future_candidate` rows that still need action
- linked future rows that already created local preflight bookings
- closed synthetic rows
- stale open rows awaiting cron expiry
- local `source='bolt_mail'` normalized bookings
- submission job / attempt counts

## Safety contract

This page is read-only.

It does not:

- scan the mailbox
- import email
- create normalized bookings
- create submission jobs
- call Bolt
- call EDXEIX
- submit live

## Upload path

`/home/cabnet/public_html/gov.cabnet.app/ops/mail-status.php`

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mail-status.php
```

Open:

`https://gov.cabnet.app/ops/mail-status.php?key=INTERNAL_KEY`

Expected clean state after synthetic cleanup:

- Active unlinked candidates: `0`
- Open submission jobs: `0`
- Stale open intake rows: `0`
- Live submit badge: `OFF`

