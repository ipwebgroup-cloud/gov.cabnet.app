# V3 Automation Pre-Live Status

Version checkpoint: `v3.0.73-v3-proof-ledger`

## Current verified posture

The V3 pre-ride email automation path has reached a stable closed-gate pre-live proof stage.

Confirmed from live server testing before this patch:

- V3 Maildir intake works.
- V3 queue rows are created/deduplicated.
- V3 expiry guard safely blocks expired or past rows.
- V3 starting-point guard verifies lessor/start-option compatibility.
- V3 dry-run readiness and live-readiness gates work.
- Historical live-ready proof is preserved after expiry.
- Operator approval workflow works for closed-gate rehearsal only.
- Local EDXEIX field package export works.
- Adapter interface, disabled adapter, dry-run adapter, and non-live EDXEIX skeleton adapter exist.
- Adapter contract probe confirms the real adapter skeleton is not live capable.
- Adapter row simulation confirms no EDXEIX call is made.
- Payload consistency harness confirms DB payload, artifact payload, and adapter payload hashes match.
- Pre-live switchboard shows current blocking state.
- Pre-live proof bundle exporter writes local evidence summaries.

Latest proof bundle checkpoint before this patch:

```text
V3 pre-live proof bundle export v3.0.72-v3-proof-bundle-runner-and-ops-hotfix
OK: yes
Bundle safe: yes
```

Important v3.0.72 safety flags:

```text
storage_ok: yes
payload_consistency_ok: yes
db_vs_artifact_match: yes
adapter_hash_match: yes
adapter_live_capable: no
adapter_submitted: no
simulation_safe: yes
edxeix_call_made: no
aade_call_made: no
db_write_made: no
v0_touched: no
```

## What v3.0.73 adds

This patch adds a read-only proof ledger layer:

- CLI: `gov.cabnet.app_app/cli/pre_ride_email_v3_proof_ledger.php`
- Ops page: `public_html/gov.cabnet.app/ops/pre-ride-email-v3-proof-ledger.php`

The ledger indexes existing local artifacts only:

- `/home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_pre_live_proof_bundles`
- `/home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_live_submit_packages`

It does not call Bolt, EDXEIX, or AADE.
It does not write to the database.
It does not change queue status.
It does not write production submission tables.
It does not touch V0.

## Live submit remains blocked

The following conditions must remain blocked unless Andreas explicitly approves a future live-submit implementation phase:

- `enabled` must remain false in the live-submit config.
- `mode` must remain disabled/non-live.
- `adapter` must remain disabled or non-live.
- `hard_enable_live_submit` must remain false.
- The EDXEIX adapter skeleton must remain `isLiveCapable() === false` until a dedicated live-submit approval phase.

No historical, expired, cancelled, terminal, invalid, or past Bolt/pre-ride row may ever be submitted.

## Recommended next phase

Next safe engineering phase after committing v3.0.73:

```text
v3.0.74 — V3 proof ledger integration polish
```

Suggested scope:

1. Add a link to the proof ledger from the V3 Control Center/Ops Index.
2. Add a latest-proof card to the pre-live switchboard.
3. Add a proof-bundle retention/count warning, still read-only.
4. Keep live submission disabled.

Only after the ledger and switchboard are stable should the project move to:

```text
v3.1.x — real EDXEIX adapter design, still disabled by default
```

That later phase must not enable live submission without explicit approval and a real eligible future row.
