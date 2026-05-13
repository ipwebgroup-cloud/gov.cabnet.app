# V3 Live Readiness Page Fix

Fixes the read-only V3 Live-Submit Readiness dashboard error:

```text
Unknown column 'lessor_id' in 'SELECT'
```

The verified starting-point options table uses canonical columns:

```text
edxeix_lessor_id
edxeix_starting_point_id
```

This patch updates the page to detect/alias either naming style safely.

Safety:
- Production pre-ride-email-tool.php untouched.
- No DB writes.
- No EDXEIX calls.
- No AADE calls.
- No production submission_jobs/submission_attempts access.
