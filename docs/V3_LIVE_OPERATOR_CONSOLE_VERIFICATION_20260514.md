# V3 Live Operator Console Verification — 2026-05-14

## Page

```text
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-operator-console.php
```

Authenticated URL used:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-live-operator-console.php?queue_id=716
```

## Syntax check

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-operator-console.php
```

Expected:

```text
No syntax errors detected
```

## Auth redirect check

```bash
curl -I https://gov.cabnet.app/ops/pre-ride-email-v3-live-operator-console.php
```

Expected unauthenticated response:

```text
HTTP/1.1 302 Found
Location: /ops/login.php?next=%2Fops%2Fpre-ride-email-v3-live-operator-console.php
```

## Observed authenticated console state

Badges:

```text
EXPECTED CLOSED PRE-LIVE GATE
NO LIVE RISK DETECTED
NO EDXEIX CALL
NO DB WRITES
```

Queue metrics:

```text
live_submit_ready: 1
future_active: 1
active: 1
```

Gate card:

```text
gate mode: disabled
adapter: disabled
enabled: no
hard: no
ack: yes
```

Artifacts:

```text
proof bundles: 5
live package field exports: 4
```

Current queue row displayed:

```text
queue_id: 716
status: live_submit_ready
customer: V3 Canary Marina 1778760875
vehicle: ITK7702
driver: Efthymios Giakis
lessor: 2307
driver_id: 17852
vehicle_id: 11187
start: 1455969
proof status: payload complete, start verified, approval valid, closed-gate proof ready
```

## Safety interpretation

The console is a read-only operational view. It confirms readiness of a row for closed-gate proof, not permission to submit live.

The page must continue to show live blocked until Andreas explicitly requests live submission work and the separate master gate is intentionally opened under strict safeguards.
