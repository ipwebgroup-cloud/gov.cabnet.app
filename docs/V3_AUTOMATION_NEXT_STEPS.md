# V3 Automation Next Steps

Current next safe path after `v3.0.67-v3-adapter-row-simulation`:

1. Verify the adapter row simulation on the live server.
2. Confirm the future adapter skeleton remains non-live-capable and returns `submitted=false`.
3. Commit the V3 simulation checkpoint.
4. Prepare a documentation checkpoint for the closed-gate pre-live surface.
5. Only after explicit approval, design the real adapter internals behind the disabled gate.

Live submit remains disabled.

Do not change V0, AADE, EDXEIX live submit behavior, cron schedules, or production submission tables.
