# Live Legacy Public Utility Usage Audit — 2026-05-15

Adds a read-only usage evidence audit for the six guarded legacy public-root utilities.

## Safety posture

- No Bolt call.
- No EDXEIX call.
- No AADE call.
- No DB connection.
- No filesystem writes.
- No route moves.
- No route deletions.
- No redirects.
- Legacy public-root utilities are not included or executed.
- Production pre-ride tool remains untouched.

## Added routes

- CLI: `/home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_usage_audit.php`
- Ops: `/home/cabnet/public_html/gov.cabnet.app/ops/legacy-public-utility-usage-audit.php`

## Purpose

The audit scans readable local logs and cPanel stat caches for historical mentions of the legacy public-root utility endpoints. It is evidence-gathering only and does not approve removal.

## Next safe decision

Use the audit result to decide whether quiet-period tracking or compatibility wrappers are needed before any future relocation/stub work. Do not delete or move any legacy route without explicit approval.
