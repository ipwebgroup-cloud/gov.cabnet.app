# gov.cabnet.app patch — Phase 24 Smoke Test Center

Upload:

```text
public_html/gov.cabnet.app/ops/smoke-test-center.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/smoke-test-center.php
```

Verify:

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/smoke-test-center.php
```

Open:

```text
https://gov.cabnet.app/ops/smoke-test-center.php
```

Expected:
- login required
- page opens inside shared ops shell
- file snapshot displays
- DB/table snapshot displays if DB is available
- copy/paste smoke-test command blocks are visible
- production pre-ride tool remains unchanged
