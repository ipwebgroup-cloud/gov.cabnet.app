# Mobile Submit QA Dashboard — Phase 62

## Purpose

Adds a read-only QA dashboard for the future authenticated mobile/server-side EDXEIX submit workflow.

Route:

```text
/ops/mobile-submit-qa-dashboard.php
```

The page summarizes:

- mapping readiness
- lessor-specific starting point override readiness
- sanitized EDXEIX submit capture readiness
- dry-run support readiness
- sanitized evidence log readiness
- live-submit blocked state

## Safety contract

The dashboard does not:

- submit to EDXEIX
- call Bolt
- call AADE
- write database rows
- modify the production pre-ride tool
- display raw email text, cookies, sessions, CSRF token values, credentials, or real config values

Live server-side EDXEIX submit remains blocked until Andreas explicitly approves a separate live-submit change.

## Deployment path

```text
public_html/gov.cabnet.app/ops/mobile-submit-qa-dashboard.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-qa-dashboard.php
```

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-qa-dashboard.php
```

Open:

```text
https://gov.cabnet.app/ops/mobile-submit-qa-dashboard.php
```

Expected result:

- page loads behind the ops login system
- safety badges show read-only/no-live-submit posture
- gate matrix displays mapping, override, capture, dry-run, evidence, and live-submit blocked state
- WHITEBLUE / lessor 1756 override is shown as ready only if `mapping_lessor_starting_points` confirms starting point `612164`
- evidence gate shows ready only if `mobile_submit_evidence_log` exists and evidence routes are installed
