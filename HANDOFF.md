# HANDOFF — gov.cabnet.app v3.0.87 reference cleanup phase 1

Current safe state:

- Production pre-ride tool remains untouched.
- V3 live EDXEIX submission remains disabled.
- Public utility relocation planner is active and read-only.
- v3.0.87 reduces operator/documentation links to legacy guarded public-root utilities.
- No public-root utility endpoints were moved or deleted.
- No SQL changes were made.

Next safest step:

After deployment, rerun the relocation planner and compare cleanup reference counts. Continue with further no-delete reference cleanup only if needed.
