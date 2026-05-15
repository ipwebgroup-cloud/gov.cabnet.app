# gov.cabnet.app — Public Utility Dependency Evidence

Date: 2026-05-15  
Scope: live-site audit / no-break de-bloat planning  
Version: v3.0.85-public-utility-relocation-plan-dependency-evidence

## Purpose

This patch improves the existing Public Utility Relocation Plan so it classifies dependency evidence before any move of guarded public-root utility endpoints.

It remains read-only and does not move, delete, disable, or rewrite any routes.

## Why this was needed

The live dependency check showed no obvious cron hits, but it did show live/project references in docs and ops pages such as:

- `/ops/bolt-live.php`
- `/ops/jobs.php`
- `/ops/submit.php`
- `/ops/test-booking.php`
- `/ops/help.php`
- `/home/cabnet/docs/*`

Therefore the public-root utilities should not be relocated yet. The next step is to retire/replace references or prepare compatibility wrappers first.

## Safety

- No Bolt call.
- No EDXEIX call.
- No AADE call.
- No database connection.
- No filesystem writes.
- No route move.
- No route deletion.
- Production pre-ride tool is untouched.

## Expected result

The planner now reports:

- all 6 public-root utilities still require dependency review before move;
- blocking dependency reference counts;
- reference kinds such as ops pages, docs, and private app code;
- sample references explaining why no route should be moved yet.

## Current recommendation

Do not relocate the public-root utilities yet.

Next safe phase should be a no-break compatibility plan that updates ops/docs links first, creates supervised `/ops` wrappers or private CLI equivalents where needed, and only then replaces public-root utilities with compatibility stubs.
