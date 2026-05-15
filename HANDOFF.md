# HANDOFF — gov.cabnet.app Bolt → EDXEIX bridge

Current continuation point: v3.0.88 adds a read-only Public Utility Reference Cleanup Phase 2 Preview.

Safety posture remains unchanged:
- Live EDXEIX submission disabled.
- No route moves or deletions.
- No SQL changes.
- Production pre-ride tool untouched.
- V3 remains closed-gate.

Use:
- `/ops/public-utility-reference-cleanup-phase2-preview.php`
- CLI: `/home/cabnet/gov.cabnet.app_app/cli/public_utility_reference_cleanup_phase2_preview.php --json`

Next safest step: use the Phase 2 preview output to patch only docs and ops/admin links first. Do not move legacy public-root utilities until compatibility wrappers and dependency quiet-period checks are complete.
