# v5.2.2 Driver Receipt Email Transport Fix

## Purpose

Fixes receipt email delivery failures caused by long HTML lines being rejected by Exim/SMTP transports.

The receipt HTML body is now sent with:

- `Content-Transfer-Encoding: base64`
- base64 body wrapped at 76 characters per line

This prevents `message has lines too long for transport` errors while keeping the rendered receipt unchanged.

## Scope

Changed file:

- `gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php`

## Safety

This patch only changes receipt email transport encoding. It does not:

- enable live EDXEIX submit
- call Bolt
- call EDXEIX
- create `submission_jobs`
- create `submission_attempts`
- change normalized bookings
- change dry-run evidence
- change live-submit gates
