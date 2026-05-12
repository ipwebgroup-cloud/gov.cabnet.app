# Phase 39 — Mapping Resolver Test

Upload:

```text
public_html/gov.cabnet.app/ops/mapping-resolver-test.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/mapping-resolver-test.php
```

SQL: none.

Verify:

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mapping-resolver-test.php
```

Open:

```text
https://gov.cabnet.app/ops/mapping-resolver-test.php
https://gov.cabnet.app/ops/mapping-resolver-test.php?operator=WHITEBLUE%20PREMIUM%20E%20E&driver=Georgios%20Tsatsas&vehicle=XZO1837
```

Expected WHITEBLUE result:

```text
lessor 1756
driver 4382
vehicle 4327
starting point 612164
lessor-specific starting point YES
```

Production pre-ride tool remains unchanged.
