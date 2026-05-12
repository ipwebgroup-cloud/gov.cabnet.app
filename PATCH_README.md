# gov.cabnet.app Phase 51 — EDXEIX Session Readiness

## Files included

- `public_html/gov.cabnet.app/ops/edxeix-session-readiness.php`
- `docs/OPS_UI_SHELL_PHASE51_EDXEIX_SESSION_READINESS_2026_05_12.md`

## Upload path

```text
public_html/gov.cabnet.app/ops/edxeix-session-readiness.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-session-readiness.php
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-session-readiness.php
```

Expected:

```text
No syntax errors detected
```

Open:

```text
https://gov.cabnet.app/ops/edxeix-session-readiness.php
```

Expected:

- login required
- page opens in the shared ops shell
- readiness checklist displays
- latest sanitized submit capture metadata displays if available
- no live submit control exists
- production pre-ride tool remains unchanged
