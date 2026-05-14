# V0 / V3 Operations Boundary

Version: v3.0.40

## Operating model

- V0 is installed on the laptop and remains the manual/production helper.
- V3 is installed on the PC/server-side path and remains the automation development path.
- Andreas uses operator judgment during live rides.
- V3 should provide visibility and dry-run readiness, not make live-production fallback decisions.

## Hard boundary

This patch does not touch V0 production or V0 dependencies.

V3 work must not modify:

- V0 laptop helper files
- V0 browser/tool dependencies
- current manual production upload flow
- live-submit enablement
- AADE production behavior

## V3 scope

V3 may continue improving:

- monitoring visibility
- queue state readability
- storage/cron health diagnostics
- starting-point guards
- expiry guards
- dry-run readiness
- payload audit behind a closed live-submit gate

## Live submit posture

Live EDXEIX submission remains disabled unless Andreas explicitly asks to open that gate in a future update.
