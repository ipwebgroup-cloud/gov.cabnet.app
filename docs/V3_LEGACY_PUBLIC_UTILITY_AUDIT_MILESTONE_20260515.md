# gov.cabnet.app — V3 Legacy Public Utility Audit Milestone

Date: 2026-05-15  
Milestone range: v3.0.80 through v3.0.99  
Project: Bolt → EDXEIX bridge for `https://gov.cabnet.app`

## Purpose

This milestone records the live-site audit and no-break de-bloat work completed after the V3 closed-gate pre-live milestone. The work focused on reducing operator navigation clutter, identifying public-root utility exposure, classifying legacy Bolt/EDXEIX helper routes, and adding read-only audit boards before any future route retirement discussion.

## Safety posture

The safety posture did not change.

- Live EDXEIX submission remains disabled.
- The V3 live gate remains closed.
- The EDXEIX adapter remains skeleton/non-live.
- The production pre-ride tool route remains untouched: `/ops/pre-ride-email-tool.php`.
- Legacy public-root utility routes remain in place for compatibility.
- No routes were moved.
- No routes were deleted.
- No redirects were added.
- No SQL migrations were applied.
- No Bolt, EDXEIX, AADE, database-write, or production submission calls were introduced by the audit tools.

## Confirmed production pre-ride route status

The production tool remains:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
https://gov.cabnet.app/ops/pre-ride-email-tool.php
```

A grep check confirmed it does not directly use the shared `/ops/_shell.php`, so the navigation de-bloat work did not modify the production tool file or its direct shared-shell layout.

## Navigation and route inventory work

### v3.0.80 — Ops navigation de-bloat

Updated:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
/home/cabnet/public_html/gov.cabnet.app/ops/route-index.php
```

Result:

- Daily navigation was shortened.
- V3 proof/readiness links remained visible.
- Dev/test/mobile/evidence/package/helper tools were grouped under a collapsed Developer Archive.
- Route Index became a live route inventory/developer archive surface.
- No routes were deleted.

Verified markers included:

```text
v3.0.80
Developer archive
Live Route Inventory
```

### v3.0.81–v3.0.82 — Public Route Exposure Audit

Added read-only audit tooling:

```text
/home/cabnet/gov.cabnet.app_app/cli/public_route_exposure_audit.php
/home/cabnet/public_html/gov.cabnet.app/ops/public-route-exposure-audit.php
```

Result:

- Public-root PHP endpoints were inventoried.
- `.user.ini` auto-prepend auth posture was checked.
- `_auth_prepend.php` and `.htaccess` helper protections were checked.
- Initial `.htaccess` `.user.ini` detection warning was fixed in v3.0.82.

Confirmed result after hotfix:

```text
ok=true
auth_ok=true
htaccess_denies_user_ini=true
final_blocks=[]
```

Remaining warning was a planning item only: six guarded public-root utility endpoints should stay protected and be considered for later no-break relocation planning.

## Legacy public-root utility audit scope

The six legacy guarded public-root utilities reviewed were:

```text
/bolt-api-smoke-test.php
/bolt-fleet-orders-watch.php
/bolt_stage_edxeix_jobs.php
/bolt_submission_worker.php
/bolt_sync_orders.php
/bolt_sync_reference.php
```

These routes were not moved, deleted, redirected, or stubbed.

## Public utility relocation and reference cleanup planning

### v3.0.83–v3.0.86 — Relocation/reference cleanup planners

Added and refined:

```text
/home/cabnet/gov.cabnet.app_app/cli/public_utility_relocation_plan.php
/home/cabnet/public_html/gov.cabnet.app/ops/public-utility-relocation-plan.php
```

Key outcomes:

- v3.0.83 introduced the no-break relocation plan.
- v3.0.84 fixed a permission-safe scan issue around unreadable private storage paths.
- v3.0.85 added dependency evidence.
- v3.0.86 added reference cleanup planning.

Important live result after Phase 1 cleanup:

```text
cleanup_refs before: 63
cleanup_refs after: 38
reduction: 25 references retired
```

No route behavior changed.

### v3.0.87 — Phase 1 reference cleanup

Updated selected legacy ops pages and operator docs to stop pointing directly to guarded public-root utility endpoints. They now point to supervised audit/planning pages instead.

Verified:

```text
PHP syntax clean
auth redirects intact
planner ok=true
final_blocks=[]
cleanup_refs reduced from 63 to 38
```

### v3.0.88–v3.0.91 — Phase 2 preview and noise filtering

Added:

```text
/home/cabnet/gov.cabnet.app_app/cli/public_utility_reference_cleanup_phase2_preview.php
/home/cabnet/public_html/gov.cabnet.app/ops/public-utility-reference-cleanup-phase2-preview.php
```

Then refined scanner noise filtering so intentional wrapper/navigation/docs references do not inflate cleanup debt.

Final verified v3.0.91 result:

```text
ok=true
version=v3.0.91-public-utility-reference-cleanup-preview-ignore-wrapper-noise
actionable=32
safe_phase2=0
ignored=68
final_blocks=[]
```

Meaning: no further automatic reference cleanup was safe at that time. Remaining references require manual review.

## Legacy public utility wrapper

### v3.0.89–v3.0.90 — Read-only wrapper and navigation

Added:

```text
/home/cabnet/gov.cabnet.app_app/src/Support/LegacyPublicUtilityRegistry.php
/home/cabnet/public_html/gov.cabnet.app/ops/legacy-public-utility.php
```

Result:

- Provides a supervised `/ops` landing wrapper for the six legacy utilities.
- Does not execute legacy utilities.
- Does not redirect legacy routes.
- Does not replace or delete any legacy public-root route.

Verified:

```text
LegacyPublicUtilityRegistry.php syntax: PASS
legacy-public-utility.php syntax: PASS
Auth redirect: PASS
v3.0.89 markers: PRESENT
direct_execution_from_wrapper: false
```

v3.0.90 added Developer Archive navigation links for the wrapper and Phase 2 preview.

## Usage, quiet-period, and source-kind audits

### v3.0.92–v3.0.93 — Legacy usage audit

Added:

```text
/home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_usage_audit.php
/home/cabnet/public_html/gov.cabnet.app/ops/legacy-public-utility-usage-audit.php
```

v3.0.93 added stable route-level summary fields.

Verified route-level evidence:

```text
/bolt-api-smoke-test.php        14 mentions, last seen 24-Apr-2026 12:39:01 UTC
/bolt-fleet-orders-watch.php     0 mentions
/bolt_stage_edxeix_jobs.php     17 mentions, cPanel stats/cache only
/bolt_submission_worker.php     13 mentions, cPanel stats/cache only
/bolt_sync_orders.php           15 mentions, cPanel stats/cache only, last seen Apr/25/26 7:40 PM
/bolt_sync_reference.php         9 mentions, cPanel stats/cache only
```

### v3.0.94–v3.0.95 — Quiet-period audit

Added:

```text
/home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_quiet_period_audit.php
/home/cabnet/public_html/gov.cabnet.app/ops/legacy-public-utility-quiet-period-audit.php
```

v3.0.95 added stable route-level classification fields.

Final classification:

```text
/bolt-api-smoke-test.php       historical_usage_outside_quiet_window   stub candidate: yes
/bolt-fleet-orders-watch.php   no_usage_seen_in_scanned_sources        stub candidate: yes
/bolt_stage_edxeix_jobs.php    usage_evidence_with_unknown_date        keep unchanged
/bolt_submission_worker.php    usage_evidence_with_unknown_date        keep unchanged
/bolt_sync_orders.php          usage_evidence_with_unknown_date        keep unchanged
/bolt_sync_reference.php       usage_evidence_with_unknown_date        keep unchanged
```

Important: “stub candidate” means future compatibility-stub review only. It does not approve deletion, redirect, movement, or retirement.

### v3.0.96–v3.0.97 — Stats Source Audit and navigation

Added:

```text
/home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_stats_source_audit.php
/home/cabnet/public_html/gov.cabnet.app/ops/legacy-public-utility-stats-source-audit.php
```

Verified:

```text
ok=true
version=v3.0.96-legacy-public-utility-stats-source-audit
cpanel_only=4
live_log=0
move_now=0
delete_now=0
final_blocks=[]
```

Meaning:

- Four unknown-date routes had cPanel analytics/cache-only evidence.
- No raw/live access-log evidence was found by this audit.
- No move/delete/redirect action was recommended.

v3.0.97 added Developer Archive navigation for the stats-source audit.

## Aggregate checkpoint

### v3.0.98–v3.0.99 — Legacy Public Utility Readiness Board and navigation

Added:

```text
/home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_readiness_board.php
/home/cabnet/public_html/gov.cabnet.app/ops/legacy-public-utility-readiness-board.php
```

v3.0.99 added Developer Archive navigation.

Verified:

```text
ok=true
version=v3.0.98-legacy-public-utility-readiness-board
move_now=0
delete_now=0
redirect_now=0
final_blocks=[]
```

This is the milestone checkpoint: the legacy public utility cleanup phase has visibility and audit tools, but no live behavior changes.

## Known cosmetic issue

The shared shell note currently contains a cosmetic spacing typo:

```text
legacystats source audit navigation
```

Preferred future text:

```text
legacy stats source audit navigation
```

This is not functional and does not block the milestone.

## Recommended next step after this milestone

Do not stub, redirect, move, or delete legacy utility routes yet.

Next safest options:

1. Commit this documentation milestone.
2. Keep collecting quiet-period evidence.
3. Later, if explicitly approved, prepare a tiny cosmetic shell-note patch.
4. Future compatibility-stub discussion may only involve:
   - `/bolt-api-smoke-test.php`
   - `/bolt-fleet-orders-watch.php`

The remaining four legacy routes should stay unchanged until additional evidence review confirms they are not operationally needed.
