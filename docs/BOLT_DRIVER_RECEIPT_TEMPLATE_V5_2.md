# gov.cabnet.app — v5.2 Driver Receipt Email Template Polish

## Purpose

Polish the second driver receipt email so it is presentation-ready for live operations.

## Scope

This patch changes only the HTML receipt email body created by `BoltMailDriverNotificationService` and adds the LUX LIMO logo asset used in the receipt header.

## Included behavior

- Branded LUX LIMO header with logo.
- Cleaner receipt card layout.
- Route summary block.
- Driver / vehicle / total summary cards.
- Full ride details from the Bolt pre-ride email.
- Driver-copy 30-minute estimated end-time rule remains active.
- First-value-only estimated price rule remains active.
- VAT/TAX included breakdown at 13% remains active.
- Company stamp remains visible in the receipt.
- Safety note remains visible.

## Safety

This patch does not enable live EDXEIX submit, call Bolt, call EDXEIX, create submission jobs, create submission attempts, change normalized bookings, change dry-run evidence, or alter live-submit guards.
