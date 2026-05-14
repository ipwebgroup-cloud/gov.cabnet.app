# V3 Next Phase Plan

Current version checkpoint: `v3.0.59-v3-approval-rehearsal-proof-checkpoint`

## Immediate next patch

```text
v3.0.60-v3-live-adapter-kill-switch-check
```

## Purpose

Before any real EDXEIX adapter behavior is implemented, add a formal read-only kill-switch/pre-live check that verifies every required live-submit condition.

The page/CLI should answer one question:

```text
Could V3 live submit run right now?
```

Expected answer until explicit future approval:

```text
No.
```

## Required checks

The kill-switch check should require all of the following before returning OK:

```text
config file exists and loads
enabled = true
mode = live
adapter = edxeix_live
hard_enable_live_submit = true
required acknowledgement phrase present
selected queue row = live_submit_ready
pickup time is future-safe
row is not blocked / submitted / expired
operator approval exists and is valid
operator approval has not expired
operator approval has not been revoked
starting point is verified for the lessor
payload audit passes
package export exists or can be generated
adapter class exists
adapter class is live-capable
adapter contract check passes
V0 untouched
```

## Required negative behavior

If any condition is missing, the check must show explicit block reasons and make no changes.

```text
No EDXEIX call
No AADE call
No queue status change
No production submission table write
No config write
No V0 changes
No SQL schema changes
```

## Future phase after kill-switch

Only after the kill-switch is installed and proven closed:

```text
v3.0.61-v3-real-adapter-design-doc
v3.0.62-v3-real-adapter-non-network-dry-run-scaffold
v3.0.63-v3-real-adapter-server-only-disabled-config-support
```

Actual live-submit enabling remains out of scope until Andreas explicitly approves a live-submit update.
