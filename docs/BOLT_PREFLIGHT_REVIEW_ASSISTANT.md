# Bolt Preflight Review Assistant v1.6

Adds `/ops/preflight-review.php`, a read-only operator page that explains current preflight status in plain language.

## Safety

The page does not:

- call Bolt
- call EDXEIX
- stage jobs
- update mappings
- write database rows
- write files
- enable live EDXEIX submission

It reads the existing readiness audit and recent normalized booking analysis.

## Main URLs

```text
https://gov.cabnet.app/ops/preflight-review.php
https://gov.cabnet.app/ops/preflight-review.php?format=json
```

## Purpose

The assistant answers:

- Is system setup clean?
- Is a real future Bolt candidate present?
- Are driver and vehicle mappings ready?
- Does the future guard pass?
- Are terminal/past/cancelled rows blocked?
- What are the current blocker reasons?
- What is the next safest operator action?

## Live submission

Live EDXEIX submission remains disabled.
