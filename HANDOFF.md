# gov.cabnet.app — Handoff v3.2.27

Current state: pre-ride EDXEIX future candidate path is working.

Validated immediately before this patch:

- v3.2.26 parsed the latest Bolt pre-ride Maildir email using diagnostics fallback HTML label parsing.
- The candidate was classified `PRE_RIDE_READY_CANDIDATE`.
- Metadata was captured as `candidate_id=2`.
- Candidate details: pickup `2026-05-17 13:51:11`, end `2026-05-17 14:22:11`, driver `Filippos Giannakopoulos`, vehicle `EMX6874`.
- Mapping resolved: lessor `3814`, driver `17585`, vehicle `13799`, starting point `6467495`.
- No EDXEIX transport occurred.
- Production V0 and AADE remain unaffected.

v3.2.27 adds a read-only one-shot readiness packet:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_readiness.php --candidate-id=2 --json
```

Ops URL:

```text
https://gov.cabnet.app/ops/pre-ride-one-shot-readiness.php?candidate_id=2
```

Critical rule: do not enable live EDXEIX submission unless Andreas explicitly approves the next supervised one-shot transport patch.
