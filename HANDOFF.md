# HANDOFF — gov.cabnet.app V3 Real-Mail Expiry Audit Alignment

Current state: v3.1.4 patch prepared to improve read-only expiry-audit reporting.

## Safety facts

- Live EDXEIX submit remains disabled.
- Production Pre-Ride Tool remains untouched.
- V0 workflow remains untouched.
- This patch performs no DB writes and no queue mutations.
- This patch performs no Bolt, EDXEIX, or AADE calls.

## What v3.1.4 adds

The V3 Real-Mail Expiry Reason Audit now exposes stable summary fields to explain queue-health vs expiry-audit count differences:

- possible real rows
- canary rows
- possible-real expired-by-guard rows
- possible-real non-expired-guard rows
- possible-real mapping-correction rows
- classification counts
- mismatch explanation flag/note

## Verification command

Run the command in PATCH_README.md after upload.
