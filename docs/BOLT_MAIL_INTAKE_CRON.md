# Bolt Mail Intake Cron

This patch adds a private CLI scanner for `bolt-bridge@gov.cabnet.app`.

## Safety

The CLI scanner only imports parsed Bolt `Ride details` emails into `bolt_mail_intake`.

It does **not**:

- create EDXEIX jobs
- stage EDXEIX submissions
- submit anything live
- move or delete mail files

## CLI command

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/import_bolt_mail.php --limit=250 --days=30
```

## JSON test

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/import_bolt_mail.php --limit=250 --days=30 --json
```

## Recommended cPanel cron

```cron
*/2 * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/import_bolt_mail.php --limit=250 --days=30 >> /home/cabnet/gov.cabnet.app_app/storage/logs/bolt_mail_intake.log 2>&1
```

For the first production days, keep this cron at every 2 minutes so forwarded Bolt emails are imported quickly while live EDXEIX submission remains disabled.

## Verification

```bash
tail -50 /home/cabnet/gov.cabnet.app_app/storage/logs/bolt_mail_intake.log
```

```sql
SELECT id, customer_name, driver_name, vehicle_plate, parsed_pickup_at, parse_status, safety_status
FROM bolt_mail_intake
ORDER BY id DESC
LIMIT 20;
```

A historical or expired email should show `blocked_past`.

A valid future pre-ride email should show `future_candidate`.
