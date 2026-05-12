# gov.cabnet.app Patch — Phase 59 Mobile Submit Evidence Log

## Upload paths

```text
public_html/gov.cabnet.app/ops/mobile-submit-evidence-log.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-evidence-log.php

gov.cabnet.app_sql/2026_05_12_mobile_submit_evidence_log.sql
→ /home/cabnet/gov.cabnet.app_sql/2026_05_12_mobile_submit_evidence_log.sql
```

## SQL

```bash
mysql -u cabnet_gov -p cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_05_12_mobile_submit_evidence_log.sql
```

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-evidence-log.php
```

Open:

```text
https://gov.cabnet.app/ops/mobile-submit-evidence-log.php
```

## Safety

- Does not modify production `/ops/pre-ride-email-tool.php`.
- Does not call Bolt, EDXEIX, or AADE.
- Does not stage jobs or enable live submission.
- Stores sanitized evidence JSON only.
- Blocks obvious raw email / secret patterns.
