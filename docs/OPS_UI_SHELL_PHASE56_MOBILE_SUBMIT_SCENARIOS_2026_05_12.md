# Ops UI Shell Phase 56 — Mobile Submit Scenarios

Date: 2026-05-12

## Purpose

Adds a read-only synthetic scenario tester for the future mobile/server-side EDXEIX submit workflow.

The page generates TEST-ONLY Bolt pre-ride email bodies and runs them through the current parser, EDXEIX mapping resolver, lessor-specific starting point lookup, submit preflight gate, disabled connector preview, and payload validator.

## Added file

- `public_html/gov.cabnet.app/ops/mobile-submit-scenarios.php`

## Safety contract

This patch does not:

- call Bolt
- call EDXEIX
- call AADE
- write database rows
- stage jobs
- enable live EDXEIX submission
- modify the production pre-ride tool

Synthetic scenario emails are test-only and must never be posted to EDXEIX.

## Main scenario checks

The first built-in scenario tests the verified WHITEBLUE mapping:

- WHITEBLUE / lessor `1756`
- Georgios Tsatsas / driver `4382`
- XZO1837 / vehicle `4327`
- Ομβροδέκτης / Mykonos starting point `612164`

Additional scenarios cover LUXLIMO, QUALITATIVE, and MTA mapping paths so missing lessor-specific starting point overrides can be seen before a real ride.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-scenarios.php
```

Expected:

```text
No syntax errors detected
```

Open:

```text
https://gov.cabnet.app/ops/mobile-submit-scenarios.php
```

Expected:

- login required
- page opens inside shared ops shell
- scenario can be generated and run
- parser result displays
- mapping resolver result displays
- lessor-specific starting point override status displays
- preflight gate result displays when support class exists
- dry-run connector/request preview displays when support class exists
- payload validator result displays when support class exists
- no live submit control exists
