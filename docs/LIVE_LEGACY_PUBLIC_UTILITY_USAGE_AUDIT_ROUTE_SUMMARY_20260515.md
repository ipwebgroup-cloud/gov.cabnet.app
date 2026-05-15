# gov.cabnet.app — Legacy Public Utility Usage Audit Route Summary Hotfix

Date: 2026-05-15
Version: v3.0.93-legacy-public-utility-usage-audit-route-summary

## Purpose

The v3.0.92 usage audit worked, but its JSON route rows used internal field names. Operator CLI extraction commands that expected generic fields such as `route`, `mentions`, and `last_seen` printed `?` even though the audit data existed.

## Change

This hotfix keeps the audit read-only and adds stable route-summary aliases:

- `route`
- `current_route`
- `legacy_route`
- `file`
- `legacy_file`
- `mentions`
- `mention_count`
- `usage_mentions`
- `usage_mentions_total`
- `last_seen`
- `latest_seen`
- `source_kinds`
- `sample_hits`

It also adds top-level `route_mention_summary` and `utilities` arrays for easier CLI inspection.

## Safety

No routes are moved or deleted. No redirects are added. No SQL changes are made. No Bolt, EDXEIX, AADE, database, or filesystem write actions are performed. The audit only scans readable local log/stat files.

Live EDXEIX submission remains disabled and the production pre-ride tool is untouched.
