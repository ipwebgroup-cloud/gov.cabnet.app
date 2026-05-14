# gov.cabnet.app — V3 Handoff

Current V3 state after v3.0.42:

- V0 laptop/manual production helper remains untouched.
- V3 pulse cron storage/lock ownership issue was repaired and verified as cabnet.
- V3 pulse cron is healthy: cycles_run=5 ok=5 failed=0 exit_code=0.
- V3 storage check reports pulse lock file owner/group cabnet:cabnet and perms 0660.
- Live EDXEIX submit remains disabled.
- New V3 compact monitor exists at `/ops/pre-ride-email-v3-monitor.php`.
- New V3 queue focus page exists at `/ops/pre-ride-email-v3-queue-focus.php`.

Important operator note:

- Do not manually run the V3 pulse cron worker as root; it may create root-owned lock files.
- Test it as cabnet:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_pulse_cron_worker.php"
```

Next safe work:

- Continue polishing V3 visibility pages only.
- Do not touch V0 production helper or dependencies.
- Do not enable live-submit.
