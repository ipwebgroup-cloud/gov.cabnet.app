# Patch: Sanitize Mapping Dashboard JSON Output

## What changed

Updates `/ops/mappings.php` so `?format=json` returns sanitized mapping-safe fields only.

Raw Bolt payloads are excluded from JSON output.

## Files included

```text
public_html/gov.cabnet.app/ops/mappings.php
docs/MAPPING_JSON_REDACTION.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload path

```text
public_html/gov.cabnet.app/ops/mappings.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/mappings.php
```

## SQL

No SQL required.

## Verification

```text
https://gov.cabnet.app/ops/mappings.php
https://gov.cabnet.app/ops/mappings.php?format=json
https://gov.cabnet.app/ops/mappings.php?view=unmapped&format=json
```

Expected JSON fields:

```text
json_sanitized: true
raw_payload_json_included: false
```

Expected absence:

```text
raw_payload_json
```

## Git commit title

```text
Sanitize mapping dashboard JSON output
```

## Git commit description

```text
Sanitizes the guarded mapping dashboard JSON endpoint so it no longer returns raw Bolt payloads.

The /ops/mappings.php?format=json response now returns mapping-safe driver and vehicle fields only, adds json_sanitized=true, and explicitly marks raw_payload_json_included=false.

The HTML dashboard remains read-only and operationally useful, while JSON output now avoids exposing raw_payload_json and embedded raw payload data such as email fields.

No Bolt request, EDXEIX request, database write, queue creation, or live submission behavior is introduced.
```
