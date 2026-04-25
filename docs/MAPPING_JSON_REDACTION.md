# Mapping Dashboard JSON Redaction

## Purpose

`/ops/mappings.php?format=json` is a guarded operations endpoint, but it should still avoid returning raw Bolt payloads or unnecessary personal data.

This patch keeps the dashboard read-only and changes JSON output so it returns only mapping-safe fields.

## Changed behavior

The JSON endpoint now includes:

- `json_sanitized: true`
- `raw_payload_json_included: false`
- sanitized driver rows
- sanitized vehicle rows

The JSON endpoint intentionally excludes:

- `raw_payload_json`
- embedded Bolt raw payload email fields
- unknown/raw database columns

## Verification

Open:

```text
https://gov.cabnet.app/ops/mappings.php?format=json
```

Expected:

```json
{
  "json_sanitized": true,
  "raw_payload_json_included": false
}
```

Also search the response for:

```text
raw_payload_json
```

Expected: no row-level `raw_payload_json` fields should be present.

## Safety

This patch does not:

- call Bolt
- call EDXEIX
- write to the database
- create queue jobs
- enable live submission
