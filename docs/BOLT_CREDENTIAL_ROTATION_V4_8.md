# gov.cabnet.app — v4.8 Credential Rotation + Final Dry-run Handoff

## Purpose

v4.8 adds a no-secret credential-rotation acknowledgement gate before any future live EDXEIX submission design.

The project remains in safe dry-run operation:

- `app.dry_run = true`
- `edxeix.live_submit_enabled = false`
- no live EDXEIX POST
- no submission jobs required for driver copy or dry-run evidence

## Added/changed files

- `public_html/gov.cabnet.app/ops/credential-rotation.php`
- `public_html/gov.cabnet.app/ops/launch-readiness.php`
- `gov.cabnet.app_app/cli/mark_credential_rotation.php`

## Credential rotation marker

After manually rotating all required credentials, run:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/mark_credential_rotation.php --ops-key --bolt --edxeix --mailbox --by=Andreas
```

This creates:

```text
/home/cabnet/gov.cabnet.app_app/storage/security/credential_rotation_ack.json
```

The marker stores only acknowledgement metadata. It must not contain passwords, API keys, tokens, cookies, or EDXEIX session contents.

## Required manual rotation items

1. Rotate `app.internal_api_key` / ops dashboard key.
2. Rotate Bolt API credentials or tokens if exposed.
3. Rotate EDXEIX credentials/session before any live-submit phase.
4. Rotate mailbox/forwarding credentials if exposed.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/credential-rotation.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/launch-readiness.php
php -l /home/cabnet/gov.cabnet.app_app/cli/mark_credential_rotation.php
```

Open:

```text
https://gov.cabnet.app/ops/credential-rotation.php?key=INTERNAL_API_KEY
https://gov.cabnet.app/ops/launch-readiness.php?key=INTERNAL_API_KEY
```

Expected after acknowledgement:

- Credential rotation gate shows acknowledged.
- Launch readiness remains dry-run only.
- `submission_jobs = 0`
- `submission_attempts = 0`
- `live_submit_enabled = false`

## Safety

v4.8 does not enable live submit and does not call Bolt or EDXEIX from the new pages. The acknowledgement CLI writes only a local no-secret JSON marker.
