# V3 Single-Row Live Submit One-Shot — v3.2.15

This patch adds a dedicated CLI for the explicitly authorized queue row `1590` only.

## Safety boundaries

- CLI only.
- Queue ID is locked to `1590` by this patch.
- Requires exact dry-run preview hash:
  `109473d72b6799287e3ef5fadf155238532516f47ef6817362beb48ff56de022`
- Requires exact confirmation phrase:
  `I_UNDERSTAND_SUBMIT_QUEUE_1590_TO_EDXEIX_ONCE`
- Requires the V3 live gate config to be manually armed for queue `1590`.
- Requires the legacy live transport config/session to be ready.
- Auto-disarms the V3 and legacy live-submit flags after any live transport attempt.
- Does not call Bolt.
- Does not call AADE.
- Does not mutate the V3 queue row.
- Does not print cookies, CSRF tokens, headers, or response body.

## Analyze-only command

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_single_row_live_submit_one_shot.php \
  --queue-id=1590 \
  --expected-preview-sha256=109473d72b6799287e3ef5fadf155238532516f47ef6817362beb48ff56de022 \
  --json
```

## Live one-shot command

Run only under operator supervision, after confirming the row is still future-safe and both server configs are armed:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_single_row_live_submit_one_shot.php \
  --queue-id=1590 \
  --expected-preview-sha256=109473d72b6799287e3ef5fadf155238532516f47ef6817362beb48ff56de022 \
  --live-submit-one \
  --confirm-single-row-live-submit=I_UNDERSTAND_SUBMIT_QUEUE_1590_TO_EDXEIX_ONCE \
  --json
```

## Server-only config note

The patch includes an example config under `gov.cabnet.app_config_examples/`. It must not be committed as a real server config and must not contain secrets.

The live transport still depends on the existing server-only `/home/cabnet/gov.cabnet.app_config/live_submit.php` and runtime session file.
