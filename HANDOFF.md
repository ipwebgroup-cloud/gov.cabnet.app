# gov.cabnet.app HANDOFF — v3.2.29 Pre-Ride Transport Rehearsal

## Current state

The pre-ride automation path now has:

1. Maildir pre-ride detection.
2. Diagnostics-only fallback parser for HTML label rows.
3. Sanitized candidate capture into `edxeix_pre_ride_candidates`.
4. One-shot readiness packet.
5. Readiness watch page/CLI.
6. v3.2.29 read-only transport rehearsal packet.

## Safety posture

Live EDXEIX transport remains disabled.

No automatic or unattended submission is enabled.

Historical, cancelled, terminal, expired, invalid, or past Bolt rows remain blocked.

Receipt-only Bolt mail rows remain blocked from EDXEIX.

## Latest safe commands

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_readiness_watch.php --json --capture-ready
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_transport_rehearsal.php --latest-ready=1 --json
```

## Next sensitive step

The next patch would be a supervised one-shot EDXEIX transport trace for one real eligible future pre-ride candidate only.

Do not build or enable that unless Andreas explicitly approves with:

```text
Sophion, prepare the supervised pre-ride one-shot EDXEIX transport trace patch. I understand this is for one real eligible future ride only.
```
