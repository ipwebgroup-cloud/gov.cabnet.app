# Ops UI Shell Phase 39 — Mapping Resolver Test — 2026-05-12

Adds `/ops/mapping-resolver-test.php`, a read-only sanity tester for Bolt driver/vehicle/operator mapping resolution.

## Purpose

Mappings are a confirmed production failure point. The WHITEBLUE case proved that company, driver, and vehicle can resolve correctly while the starting point falls back to a wrong global value.

This page helps verify a driver + vehicle pair before a real future ride reaches the EDXEIX helper workflow.

## Safety

- No Bolt calls.
- No EDXEIX calls.
- No AADE calls.
- No DB writes.
- No queue staging.
- No live submission.
- Production pre-ride tool remains unchanged.

## Route

`/ops/mapping-resolver-test.php`

## Recommended WHITEBLUE test

`/ops/mapping-resolver-test.php?operator=WHITEBLUE%20PREMIUM%20E%20E&driver=Georgios%20Tsatsas&vehicle=XZO1837`

Expected:

- lessor `1756`
- driver `4382`
- vehicle `4327`
- starting point `612164`
- lessor-specific starting point: yes

## Upload path

`public_html/gov.cabnet.app/ops/mapping-resolver-test.php` → `/home/cabnet/public_html/gov.cabnet.app/ops/mapping-resolver-test.php`
