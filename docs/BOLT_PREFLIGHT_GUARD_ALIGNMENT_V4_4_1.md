# gov.cabnet.app — v4.4.1 Raw Preflight Guard Alignment

## Purpose

This hotfix aligns the legacy/raw JSON endpoint:

- `/bolt_edxeix_preflight.php`

with the current production mail-intake configuration:

- `edxeix.future_start_guard_minutes = 2`

## Cause

The mail-intake dashboards and auto dry-run worker read the canonical server config file:

- `/home/cabnet/gov.cabnet.app_config/config.php`

The older raw preflight endpoint loads the legacy helper stack, where an older split config file can still contain a fallback guard of `30` minutes. This caused the raw JSON endpoint to display `guard_minutes = 30` even while the active mail workflow correctly displayed `FUTURE GUARD 2 MIN`.

## Safety boundary

This patch is read-only for the raw preflight endpoint.

It does not:

- create `submission_jobs`
- create `submission_attempts`
- submit to EDXEIX
- call Bolt
- call EDXEIX
- enable live submit

## Changed behavior

`/bolt_edxeix_preflight.php` now reads the guard value directly from the canonical server config file:

- `/home/cabnet/gov.cabnet.app_config/config.php`

It also overwrites the read-only preview mapping status so both of these fields match:

- top-level `guard_minutes`
- row-level `future_guard_minutes`
- `edxeix_payload_preview._mapping_status.future_guard_minutes`

## Expected result

The raw JSON endpoint should show:

```json
"guard_minutes": 2
```

Each row should also show:

```json
"future_guard_minutes": 2
```

and the payload preview mapping status should show:

```json
"future_guard_minutes": 2
```
