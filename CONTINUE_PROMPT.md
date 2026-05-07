Greetings Sophion. Continue the gov.cabnet.app Bolt → EDXEIX bridge project.

Current state:

- v4.9 dry-run production posture was frozen.
- v5.0 guarded live-submit is armed but still blocked by `edxeix_session_connected=false` and required one-shot locks.
- v5.1 added a second driver email: an HTML receipt copy with all ride details, 13% VAT/TAX included in the total, and the LUX LIMO company stamp.
- Driver email recipient resolution is by Bolt driver identity/name from `mapping_drivers.driver_email`, not by vehicle plate.
- Live EDXEIX submit must remain blocked unless Andreas explicitly approves a connected-session live-submit step.

Next likely work:

- Validate the next real Bolt email sends both the normal driver copy and receipt copy.
- Then continue the previously identified v5.1/v5.2 technical mapping task: EDXEIX partner + driver mapping matrix by lessor/company, because EDXEIX driver IDs are scoped to each lessor.
