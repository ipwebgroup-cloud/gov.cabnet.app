# gov.cabnet.app — EDXEIX Strict Candidate Identity Lock + Validation Capture v3.2.37

## Purpose

v3.2.37 protects the supervised pre-ride EDXEIX one-shot trace after validation showed that `--capture-ready` can select the latest real Maildir pre-ride email instead of an intended demo/test email.

The patch keeps the server-side fresh create-form token integration from v3.2.36, but adds a strict identity lock before any POST.

## Safety posture

- No V0 production route is changed.
- No unattended automation or cron is added.
- No AADE/myDATA call is made.
- No queue job is created.
- No normalized booking is written.
- No live config is written.
- Candidate 4 remains closed/manual-V0 and retry blocked.
- Candidate 5 remains server-attempted/unconfirmed and retry blocked by the existing attempt table.
- Raw cookies, CSRF tokens, fresh form tokens, and raw EDXEIX HTML are not printed or stored.

## What changed

1. Adds strict identity lock for live POST:
   - expected customer name
   - expected driver name
   - expected vehicle plate
   - expected pickup datetime
2. Blocks POST if any expected identity value is missing or mismatched.
3. Adds recent candidate listing so the operator can select a specific candidate ID before transport.
4. Aligns transport payload to the authenticated EDXEIX create form fields:
   - `lessee[type]`
   - `lessee[name]`
   - `lessee[vat_number]`
   - `lessee[legal_representative]`
   - `starting_point_id`
5. Captures sanitized validation evidence when EDXEIX redirects back to `/dashboard/lease-agreement/create`.
6. Classifies create-form return separately from session expiry.

## New CLI examples

List recent captured candidates:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php --list-recent-candidates --json
```

Dry-run a specific candidate:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php --candidate-id=NEW_ID --json
```

Supervised one-shot POST with strict identity lock:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php \
  --candidate-id=NEW_ID \
  --transport=1 \
  --expect-customer='EXPECTED CUSTOMER' \
  --expect-driver='EXPECTED DRIVER' \
  --expect-vehicle='EXPECTED PLATE' \
  --expect-pickup='YYYY-MM-DD HH:MM:SS' \
  --expected-payload-hash=HASH_FROM_DRY_RUN \
  --confirm='I UNDERSTAND POST THIS ONE PRE-RIDE CANDIDATE TO EDXEIX' \
  --json
```

## Expected safe blocks

If identity is missing:

```text
expected_customer_name_required_for_transport
expected_driver_name_required_for_transport
expected_vehicle_plate_required_for_transport
expected_pickup_datetime_required_for_transport
```

If identity differs:

```text
identity_lock_mismatch_customer_name
identity_lock_mismatch_driver_name
identity_lock_mismatch_vehicle_plate
identity_lock_mismatch_pickup_datetime
```

If EDXEIX returns to the create form:

```text
PRE_RIDE_TRANSPORT_TRACE_PERFORMED_SUBMIT_REDIRECT_CREATE_FORM_RETURNED
```

The result remains unconfirmed until manually verified in the EDXEIX list.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_transport_trace_lib.php
php -l /home/cabnet/gov.cabnet.app_app/cli/edxeix_create_form_token_diagnostic.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-create-form-token-diagnostic.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-one-shot-transport-trace.php

php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php --list-recent-candidates --json
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php --candidate-id=5 --json
```

Expected for candidate 5: blocked by previous server attempt / retry prevention.

## Production V0 impact

None.
