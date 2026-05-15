# Live Legacy Public Utility Stats Source Audit — 2026-05-15

Adds v3.0.96 read-only source-kind classification for usage evidence found against legacy guarded public-root Bolt/EDXEIX utility endpoints.

## Purpose

The v3.0.95 quiet-period audit showed several routes with unknown-date evidence. Andreas provided route sample output showing those unknown-date mentions are coming from cPanel stats/cache files. This patch adds a dedicated read-only audit that classifies cPanel stats/cache-only evidence separately from raw/live access-log evidence.

## Safety

- No route moves.
- No route deletions.
- No redirects.
- No SQL.
- No database connection.
- No filesystem writes.
- No Bolt call.
- No EDXEIX call.
- No AADE call.
- Legacy public-root utilities are not included or executed.
- Production Pre-Ride Tool remains untouched.

## Added files

- `/home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_stats_source_audit.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/legacy-public-utility-stats-source-audit.php`

## Interpretation

cPanel stats/cache-only evidence is historical usage evidence, not a current runtime dependency by itself. It still does not approve route deletion. It only supports later compatibility-stub review after explicit approval and one final dependency/access-log check.
