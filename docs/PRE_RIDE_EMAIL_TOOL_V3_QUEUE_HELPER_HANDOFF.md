# V3 Queue Helper Handoff

Adds a fill-only operator handoff from the isolated V3 queue dashboard to the isolated Firefox V3 helper.

## Route

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-queue.php
```

## What changed

- Selected queue rows now show a **V3 helper handoff** panel.
- A row can be saved to the isolated V3 Firefox helper only when:
  - parser gate is OK,
  - mapping gate is OK,
  - future gate is OK,
  - lessor, driver, vehicle, and starting-point IDs are present,
  - pickup is still in the future.
- Optional button opens the EDXEIX company form after saving the helper payload.

## Safety

- No DB writes from the dashboard.
- No production route changes.
- No production `submission_jobs` writes.
- No production `submission_attempts` writes.
- No EDXEIX server-side call.
- No AADE call.
- V3 Firefox helper remains fill-only and has no POST/save button.
