# V3 Real Adapter Design Spec

Version: v3.0.66-v3-real-adapter-design-spec  
Project: gov.cabnet.app Bolt → EDXEIX bridge  
Status: design only / commit-only checkpoint

## Purpose

This document defines the safe design for a future V3 real EDXEIX live-submit adapter while preserving the current production boundary:

- V0/manual laptop helper remains untouched.
- V3 live submit remains disabled.
- No EDXEIX calls are made by this package.
- No AADE behavior is changed.
- No database schema, cron, queue status, or production submission table behavior is changed.

The intent is to prepare the implementation plan before writing any code that could make a real EDXEIX submission.

## Current proven V3 state

The following has been verified on the live server:

1. V3 pre-ride email intake works.
2. Parser and mapping work.
3. Starting-point guard works for verified options.
4. Submit dry-run readiness works.
5. Live-submit readiness works.
6. Payload audit works.
7. Package export works.
8. Operator approval workflow works.
9. Final rehearsal accepts valid approval and then blocks on master gate.
10. Kill-switch check accepts valid approval and then blocks on master gate/adapter.
11. Pre-live switchboard works in browser using direct DB/config rendering.
12. Future adapter skeleton exists but is not live-capable.

## Real adapter target

Future file:

```text
gov.cabnet.app_app/src/BoltMailV3/EdxeixLiveSubmitAdapterV3.php
```

Interface already defined:

```php
Bridge\BoltMailV3\LiveSubmitAdapterV3
```

Required methods:

```php
public function name(): string;
public function isLiveCapable(): bool;
public function submit(array $edxeixPayload, array $context = []): array;
```

## Live-capable behavior rule

The adapter must remain non-live-capable until Andreas explicitly approves a live-submit update.

Current/safe state:

```php
isLiveCapable() === false
```

Future live-capable state may only be allowed after all gates below are implemented, tested, and explicitly approved.

## Required hard gates before any real submit attempt

A future live adapter must never attempt an EDXEIX call unless every condition is true:

1. Server config file exists and is readable:
   `/home/cabnet/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php`
2. `enabled === true`
3. `mode === 'live'`
4. `adapter === 'edxeix_live'`
5. `hard_enable_live_submit === true`
6. Required acknowledgement phrase is present.
7. Queue row exists.
8. Queue row status is exactly `live_submit_ready`.
9. Pickup is still future-safe.
10. Required payload fields are present.
11. Starting point is verified in `pre_ride_email_v3_starting_point_options`.
12. Operator approval exists, is valid, is not revoked, and is not expired.
13. Adapter is live-capable.
14. Dry-run/payload package has already been generated or can be generated locally.
15. No terminal/historical/expired/cancelled/past row is selected.

## Required payload fields

The final EDXEIX field package must include:

```text
lessor
driver
vehicle
starting_point_id
lessee_name
lessee_phone
boarding_point
disembark_point
started_at
ended_at
price
price_text
```

If any value is missing or blank, the adapter must return a blocked result and must not call EDXEIX.

## Result envelope standard

All adapter results must return an array with these keys where applicable:

```php
[
    'ok' => false,
    'submitted' => false,
    'blocked' => true,
    'adapter' => 'edxeix_live',
    'reason' => '...',
    'message' => '...',
    'payload_sha256' => '...',
    'context' => [
        'queue_id' => '...',
        'dedupe_key' => '...',
        'lessor_id' => '...',
        'vehicle_plate' => '...',
    ],
]
```

Only a real confirmed EDXEIX submission may return:

```php
'submitted' => true
```

## Error handling design

The future adapter must fail closed. Any exception, missing field, invalid response, network error, timeout, unexpected HTML/page shape, or mismatch must return a blocked/failed envelope and not mark the queue as submitted.

The adapter should never expose credentials, cookies, tokens, session paths, raw request secrets, or full remote responses in UI output.

## Audit expectations

Before and after a future submit attempt, V3 should have local evidence:

- selected queue row
- payload hash
- approval row id
- package export artifact names
- gate state
- adapter name/version
- timestamp
- result envelope

No raw credentials or private session material may be stored in repo or public webroot.

## Rollback principle

A single config revert must disable all future live submit behavior:

```php
'enabled' => false,
'mode' => 'disabled',
'adapter' => 'disabled',
'hard_enable_live_submit' => false,
```

The current project must keep this as the default state until Andreas explicitly requests a live-submit update.
