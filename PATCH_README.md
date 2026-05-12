# gov.cabnet.app patch — Phase 37 Mapping Navigation + Verification Register

## Upload paths

Upload:

```text
public_html/gov.cabnet.app/ops/_mapping_nav.php
public_html/gov.cabnet.app/ops/mapping-center.php
public_html/gov.cabnet.app/ops/mapping-verification.php
```

to:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/_mapping_nav.php
/home/cabnet/public_html/gov.cabnet.app/ops/mapping-center.php
/home/cabnet/public_html/gov.cabnet.app/ops/mapping-verification.php
```

Upload:

```text
gov.cabnet.app_sql/2026_05_12_mapping_verification_register.sql
```

to:

```text
/home/cabnet/gov.cabnet.app_sql/2026_05_12_mapping_verification_register.sql
```

## SQL

Run:

```bash
mysql -u cabnet_gov -p cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_05_12_mapping_verification_register.sql
```

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_mapping_nav.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mapping-center.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mapping-verification.php
```

Open:

```text
https://gov.cabnet.app/ops/mapping-center.php
https://gov.cabnet.app/ops/mapping-verification.php
https://gov.cabnet.app/ops/mapping-verification.php?lessor=1756
```

## Production safety

This patch does not touch `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php`.

It does not call Bolt, EDXEIX, or AADE, and it does not enable live submission.
