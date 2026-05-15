# gov.cabnet.app handoff — v3.0.89 Legacy Public Utility Ops Wrapper

Current state:

- V3 closed-gate pre-live work remains safe.
- Live EDXEIX submission remains disabled.
- Production pre-ride tool `/ops/pre-ride-email-tool.php` remains untouched.
- Public utility reference cleanup Phase 1 reduced cleanup references from 63 to 38.
- Phase 2 preview verified `actionable=32`, `safe_phase2=0`, `final_blocks=[]`.
- Because no safe Phase 2 cleanup candidates exist, the next safe step is to add stable `/ops` wrapper targets before editing more references.

Patch v3.0.89 adds:

- `/home/cabnet/gov.cabnet.app_app/src/Support/LegacyPublicUtilityRegistry.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/legacy-public-utility.php`

Safety:

- Metadata only.
- No Bolt call.
- No EDXEIX call.
- No AADE call.
- No DB connection.
- No filesystem writes.
- No route moves.
- No route deletions.
- No redirect/include/execution of legacy public-root utilities.

Next safest step after verification:

1. Confirm v3.0.89 wrapper loads behind ops auth.
2. Only then prepare a small patch to update low-risk private/test generated references to point at `/ops/legacy-public-utility.php?utility=...` instead of direct public-root routes.
3. Do not change production pre-ride tool.
4. Do not move or delete public-root utilities yet.
