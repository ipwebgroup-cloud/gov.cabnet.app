# EDXEIX Pre-Ride One-Shot Transport Trace v3.2.30

This patch adds the first supervised one-candidate HTTP POST trace for the pre-ride EDXEIX path.

## Safety contract

Default mode is dry-run. The tool does not submit unless all of the following are true:

1. `candidate_id` is explicitly selected.
2. The one-shot readiness/rehearsal packet is still ready.
3. The pickup is still at least the configured guard minutes in the future.
4. Duplicate success checks are clean.
5. EDXEIX session file is ready.
6. Submit URL is configured.
7. Existing live submit gates remain disabled, preventing a second path.
8. The operator provides the current payload hash.
9. The operator types the exact confirmation phrase.

No AADE call, queue job, normalized booking write, live config write, or cron is added.

## Confirmation phrase

```text
I UNDERSTAND POST THIS ONE PRE-RIDE CANDIDATE TO EDXEIX
```

## Dry-run command

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php --candidate-id=2 --json
```

The dry-run output shows the required payload hash.

## One supervised POST trace command

Replace `PAYLOAD_HASH_FROM_DRY_RUN` with the dry-run output value.

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php \
  --candidate-id=2 \
  --transport=1 \
  --expected-payload-hash=PAYLOAD_HASH_FROM_DRY_RUN \
  --confirm='I UNDERSTAND POST THIS ONE PRE-RIDE CANDIDATE TO EDXEIX' \
  --json
```

## Web page

```text
https://gov.cabnet.app/ops/pre-ride-one-shot-transport-trace.php?candidate_id=2
```

Actual POST from the web page requires a POST form submission and the exact confirmation phrase.

## Optional SQL

```bash
mysql -u cabnet_gov -p cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_05_17_edxeix_pre_ride_transport_attempts.sql
```

The table stores only sanitized trace metadata. It does not store cookie headers, CSRF tokens, or raw response body HTML.

## After a trace

A redirect or 2xx response is not proof by itself. Verify the contract in the EDXEIX portal/list. If confirmed, record proof and do not retry the same candidate. If not confirmed, treat the result as diagnostic only.
