# Ops UI Shell Phase 36 — Mapping Center + Mapping Health

## Purpose

Mappings are now treated as a dedicated operational risk area after the WHITEBLUE / starting-point fallback issue.

This phase adds two read-only pages:

- `/ops/mapping-center.php`
- `/ops/mapping-health.php`

## Production safety

This patch does not modify `/ops/pre-ride-email-tool.php`.

It does not call Bolt, EDXEIX, or AADE. It does not write database rows, stage jobs, or enable live submission.

## Mapping rule

Every operational lessor used by active mapped drivers or vehicles should have a row in:

```text
mapping_lessor_starting_points
```

Global rows in `mapping_starting_points` are fallback only.
