# gov.cabnet.app — V3 Handoff

You are Sophion assisting Andreas with the gov.cabnet.app Bolt → EDXEIX bridge project.

## Project identity

- Domain: https://gov.cabnet.app
- GitHub repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- Do not introduce frameworks, Composer, Node build tools, or heavy dependencies unless Andreas explicitly approves.

Expected server layout:

```text
/home/cabnet/public_html/gov.cabnet.app
/home/cabnet/gov.cabnet.app_app
/home/cabnet/gov.cabnet.app_config
/home/cabnet/gov.cabnet.app_sql
/home/cabnet/tools/firefox-edxeix-autofill-helper
```

## Critical safety posture

- V0 laptop/manual production helper is untouched and must remain untouched unless Andreas explicitly asks.
- V3 is the PC/server-side automation development path.
- Live EDXEIX submit remains disabled.
- Do not enable live-submit without explicit approval from Andreas.
- No AADE changes unless explicitly requested.
- No real credentials may be requested, exposed, or committed.
- Prefer read-only, dry-run, rehearsal, payload audit, and visibility screens.

## Current V3 proof state

As of 2026-05-14, V3 has successfully proven the forwarded-email readiness path.

Proof path:

```text
Gmail/manual forward
→ server mailbox
→ V3 intake
→ parser
→ driver / vehicle / lessor mapping
→ future-safe guard
→ verified starting-point guard
→ submit_dry_run_ready
→ live_submit_ready
```

Proof row:

```text
queue_id: 56
queue_status: live_submit_ready
customer: Arnaud BAGORO
driver: Filippos Giannakopoulos
vehicle: EHA2545
lessor_id: 3814
driver_id: 17585
vehicle_id: 5949
starting_point_id: 6467495
last_error: NULL
```

Payload audit confirmed row 56 as payload-ready:

```text
Rows checked: 1
Payload-ready: 1
Blocked: 0
Warnings: 0
No EDXEIX call. No AADE call. No queue status change.
```

Final rehearsal correctly blocked the row because the master gate is closed:

```text
Master gate OK: no
config_loaded: yes
adapter: disabled
hard_enabled: no
Pre-live passed: 0
Blocked: 1
```

Gate blocks:

```text
enabled is false
mode is not live
required acknowledgement phrase is not present
adapter is disabled
hard_enable_live_submit is false
approval: no valid operator approval found
```

This is the correct safe result.

## Important V3 patches verified

- v3.0.39 — V3 storage check and V0/V3 boundary docs
- v3.0.40 — Pulse lock ownership hardening
- v3.0.41 — Compact V3 monitor
- v3.0.42 — V3 queue focus page
- v3.0.43 — V3 pulse focus page
- v3.0.44 — V3 readiness focus page
- v3.0.45 — V3 dashboard integration
- v3.0.46 — Ops index V3 entry links
- v3.0.47 — Live-readiness starting-point option alias fix

## Runtime fixes confirmed

The V3 pulse cron previously failed due to a root-owned lock file. It is now healthy.

Correct lock file state:

```text
/home/cabnet/gov.cabnet.app_app/storage/locks/pre_ride_email_v3_fast_pipeline_pulse.lock
owner:group: cabnet:cabnet
perms: 0660
```

Do not test the pulse cron worker as root. Test it as cabnet:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_pulse_cron_worker.php"
```

## Lessor / starting point facts

Lessor 3814 verified V3 option:

```text
3814 / 6467495 = ΕΔΡΑ ΜΑΣ, Δήμος Μυκόνου, Περιφερειακή Ενότητα Μυκόνου, Περιφέρεια Νοτίου Αιγαίου, Αποκεντρωμένη Διοίκηση Αιγαίου, 846 00, Ελλάδα
```

Lessor 2307 verified V3 options:

```text
1455969 = ΧΩΡΑ ΜΥΚΟΝΟΥ
9700559 = ΕΠΑΝΩ ΔΙΑΚΟΦΤΗΣ
```

Earlier invalid mapping:

```text
For lessor 2307, 6467495 was invalid/not present in EDXEIX form.
```

## EMT8640 permanent exemption

Vehicle EMT8640 and Bolt vehicle identifier `f9170acc-3bc4-43c5-9eed-65d9cadee490` are permanently exempt.

Rule:

```text
No voucher
No driver email
No invoice / AADE receipt
No EDXEIX worker submission
No V3 queue intake
```

## Main V3 Ops URLs

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php
https://gov.cabnet.app/ops/pre-ride-email-v3-monitor.php
https://gov.cabnet.app/ops/pre-ride-email-v3-queue-focus.php
https://gov.cabnet.app/ops/pre-ride-email-v3-pulse-focus.php
https://gov.cabnet.app/ops/pre-ride-email-v3-readiness-focus.php
https://gov.cabnet.app/ops/pre-ride-email-v3-storage-check.php
https://gov.cabnet.app/ops/pre-ride-email-v3-live-submit-gate.php
```

## Next safest step

Commit the verified V3 readiness proof checkpoint.

After commit, the next safe phase is to continue with live adapter design behind the closed gate only. Do not open the gate or enable live submit unless Andreas explicitly requests it.
