# HANDOFF — gov.cabnet.app Bolt → EDXEIX V3 Automation

Version: `v3.0.57-v3-live-adapter-runbook`

## Project identity

- Domain: `https://gov.cabnet.app`
- Repo: `https://github.com/ipwebgroup-cloud/gov.cabnet.app`
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- Do not introduce Composer, Node, frameworks, or heavy dependencies unless Andreas explicitly approves.

## Server layout

```text
/home/cabnet/public_html/gov.cabnet.app
/home/cabnet/gov.cabnet.app_app
/home/cabnet/gov.cabnet.app_config
/home/cabnet/gov.cabnet.app_sql
/home/cabnet/tools/firefox-edxeix-autofill-helper
```

## Critical boundary

```text
V0 laptop/manual helper: production fallback, untouched by V3 patches.
V3 PC/server automation: development path.
Live EDXEIX submit: disabled.
```

## Verified V3 milestone

V3 readiness pipeline was proven with a forwarded Gmail/Bolt-style pre-ride email.

Proof row:

```text
queue_id: 56
customer: Arnaud BAGORO
driver: Filippos Giannakopoulos
vehicle: EHA2545
lessor_id: 3814
driver_id: 17585
vehicle_id: 5949
starting_point_id: 6467495
historically reached: live_submit_ready
payload audit: PAYLOAD-READY
package export: artifacts written
current status: blocked after expiry
```

Proven path:

```text
forwarded Gmail email
→ server mailbox
→ V3 intake
→ parser
→ mapping
→ future-safe guard
→ starting-point guard
→ submit_dry_run_ready
→ live_submit_ready
→ payload audit
→ final rehearsal blocked by master gate
→ package export
```

## Current gate state

Expected safe state:

```text
enabled=no
mode=disabled
adapter=disabled
hard_enable_live_submit=no
ok_for_live_submit=no
```

## Recent installed/verified phase items

```text
v3.0.52 live package export: verified
v3.0.53 operator approval visibility: verified
v3.0.54 closed-gate adapter diagnostics: verified
v3.0.55 future adapter skeleton: verified
v3.0.56 adapter contract probe: verified
v3.0.57 live adapter runbook: documentation checkpoint
```

## Current next recommended work

Proceed toward automation using closed-gate safeguards:

```text
v3.0.58-v3-live-adapter-result-envelope
v3.0.59-v3-live-adapter-evidence-artifacts
v3.0.60-v3-operator-approval-write-scaffold-closed
v3.0.61-v3-final-prelive-dry-run-test
```

Do not enable live submit yet.

## Useful commands

Storage check:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php"
```

Pulse health:

```bash
tail -n 120 /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_fast_pipeline_pulse.log | egrep "cron start|ERROR|Pulse summary|finish exit_code" || true
```

Closed-gate diagnostics:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_closed_gate_adapter_diagnostics.php"
```

Adapter contract probe:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_contract_probe.php"
```

## Safety rules

- Do not request or expose credentials.
- Do not submit expired, historical, terminal, cancelled, invalid, or exempt rows.
- EMT8640 remains permanently exempt.
- Do not run the pulse cron worker as root.
- Do not change V0 laptop/manual production helper files.
- Do not enable live submit without explicit Andreas approval.
