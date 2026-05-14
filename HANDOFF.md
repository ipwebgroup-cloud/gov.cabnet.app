# HANDOFF — gov.cabnet.app Bolt → EDXEIX V3

You are Sophion assisting Andreas with the `gov.cabnet.app` Bolt → EDXEIX bridge project.

## Project identity

- Domain: `https://gov.cabnet.app`
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- Do not introduce frameworks, Composer, Node build tools, or heavy dependencies unless Andreas explicitly approves.
- V0 laptop/manual helper is production fallback and must not be touched while developing V3.

## Current verified V3 state

V3 readiness pipeline is proven using a forwarded Gmail/Bolt pre-ride email:

```text
Gmail/manual forward
→ server mailbox
→ V3 intake
→ parser
→ mapping
→ future-safe guard
→ verified starting-point guard
→ submit_dry_run_ready
→ live_submit_ready
→ payload audit ready
→ final rehearsal blocked by master gate
→ local live package export
```

## Verified proof row

- Queue ID: `56`
- Customer: `Arnaud BAGORO`
- Driver: `Filippos Giannakopoulos`
- Vehicle: `EHA2545`
- Lessor: `3814`
- Driver ID: `17585`
- Vehicle ID: `5949`
- Starting point: `6467495`
- Starting point label: `ΕΔΡΑ ΜΑΣ...`
- Historical proof: row reached `live_submit_ready`
- Later expiry guard correctly blocked the row after pickup time passed.

## Current live-submit posture

Live EDXEIX submission remains disabled:

```text
enabled = false
mode = disabled
adapter = disabled
hard_enable_live_submit = false
required acknowledgement phrase absent or not active
operator approval required
```

## Recent verified packages

- `v3.0.47-live-readiness-start-options-alias-fix`
- `v3.0.48-v3-forwarded-email-proof-checkpoint`
- `v3.0.50-v3-proof-dashboard`
- `v3.0.51-v3-proof-dashboard-history-fix`
- `v3.0.52-v3-live-package-export`
- `v3.0.53-v3-operator-approval-visibility`

## v3.0.53 addition

Adds:

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-operator-approvals.php
```

This is a read-only page for inspecting V3 operator approval visibility, approval table state, latest approval records, queue rows, and master gate blocks.

## Next recommended phase

Continue closed-gate live adapter preparation:

1. Verify operator approval visibility page.
2. Add approval audit/export if needed.
3. Build closed-gate live adapter skeleton that cannot submit while gate is closed.
4. Test with another future forwarded email.
5. Keep V0 untouched and live submit disabled until Andreas explicitly approves a live-submit update.
