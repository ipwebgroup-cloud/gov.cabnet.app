# V3.2.9 — Controlled Maildir Fixture Writer Design Draft

This patch adds a read-only design snapshot for a future controlled Maildir fixture writer.

## Safety posture

- No Maildir write.
- No executable writer added.
- No DB writes.
- No queue mutation.
- No Bolt, EDXEIX, or AADE calls.
- Live EDXEIX submission remains disabled.

## CLI

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --maildir-writer-design-json
```

Aliases:

- `--controlled-demo-mail-writer-design-json`
- `--fixture-writer-design-json`

## Purpose

The output defines the boundaries for a future explicit one-shot Maildir fixture writer: one mail file only, no cron, no batch generation, preview-first behavior, atomic tmp-to-new move, no queue/DB mutation, and no EDXEIX/AADE calls.
