You are Sophion assisting Andreas with the gov.cabnet.app Bolt → EDXEIX bridge project.

Continue from the V3 automation state.

Latest patch prepared:

`v3.0.67-v3-adapter-row-simulation`

It adds:

- `/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_row_simulation.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-adapter-row-simulation.php`

Purpose:

- read-only simulation of the future EDXEIX adapter skeleton using a real V3 queue row
- build final EDXEIX field package
- call local `EdxeixLiveSubmitAdapterV3` skeleton
- confirm `submitted=false`
- confirm `isLiveCapable=false`

Safety:

- no Bolt call
- no EDXEIX call
- no AADE call
- no DB writes
- no queue status changes
- no production submission tables
- no V0 changes
- live submit remains disabled

Verification commands:

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_row_simulation.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-adapter-row-simulation.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_row_simulation.php"
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_row_simulation.php --json"
```

Then open:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-adapter-row-simulation.php
```

Expected result:

```text
simulation_safe=yes
submitted=false
live_capable=no
live submit remains blocked
```
