# CONTINUE PROMPT — gov.cabnet.app Bolt → EDXEIX Bridge

You are Sophion assisting Andreas with the gov.cabnet.app Bolt → EDXEIX bridge project.

## Current state

The live site has been audited with a private Sophion route + DB package. This package is not for GitHub and is used only to keep the live app aligned with the Bolt → EDXEIX goal.

Latest live safety posture:

- V3 live submit remains disabled.
- EDXEIX adapter remains skeleton-only/non-live.
- No V3 EDXEIX submit occurred.
- No V3 AADE call occurred.
- No production submission jobs are queued.
- Runtime/session package leakage was fixed with v3.0.77/v3.0.78 Handoff Center hygiene.

Latest V3 proof milestone:

```text
v3.0.75 live adapter contract test production-verified
queue_id: 716
payload_hash: e784e788532fc57824a46dad90debec9d0ad5a24f94679538c37d1d164e9f472
contract_safe: true
final_blocks: []
```

## Current patch direction

Proceed with no-delete navigation de-bloat:

- Keep daily operator navigation small.
- Keep V3 proof/readiness tools visible.
- Move dev/test/mobile/evidence/package/helper routes to Developer Archive.
- Do not delete routes.
- Do not change SQL.
- Do not enable live EDXEIX submit.
- Do not touch V0 production workflow.

Patch version:

```text
v3.0.80-navigation-debloat
```

Deploy files:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
/home/cabnet/public_html/gov.cabnet.app/ops/route-index.php
```

Verification:

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/route-index.php
curl -I --max-time 10 https://gov.cabnet.app/ops/route-index.php
```
