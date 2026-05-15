# V3 Controlled One-Shot Maildir Fixture Writer — v3.2.14

Adds a controlled one-shot Maildir fixture writer to the V3 real future candidate capture readiness toolchain.

## Safety posture

- Default mode is preview-only.
- The writer creates no Maildir file unless both are present:
  - `--write-one-maildir-fixture`
  - `--confirm-one-maildir-fixture-write=I_UNDERSTAND_ONE_MAILDIR_FILE_ONLY`
- Creates exactly one message per run.
- Writes by atomic tmp-then-move into `/home/cabnet/mail/gov.cabnet.app/bolt-bridge/new`.
- Does not write to the database.
- Does not mutate the queue.
- Does not call Bolt, EDXEIX, or AADE.
- Does not enable live submit.
- Production Pre-Ride Tool remains untouched.

## Preview command

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --maildir-fixture-writer-json
```

## Explicit write command template

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --maildir-fixture-writer-json --write-one-maildir-fixture --confirm-one-maildir-fixture-write=I_UNDERSTAND_ONE_MAILDIR_FILE_ONLY
```

Only run the write command when a controlled fixture-mail test is intentionally required.
