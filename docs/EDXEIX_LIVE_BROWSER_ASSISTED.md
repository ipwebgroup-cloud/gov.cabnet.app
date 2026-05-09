# EDXEIX Live Browser-Assisted Production Path v6.8.1

## Source policy

- EDXEIX submission source is pre-ride Bolt email only.
- Bolt API is not an EDXEIX submission source.
- AADE invoice issuing remains limited to the Bolt API pickup timestamp worker.

## Why v6.8.1 exists

The first production attempt proved that a server HTTP 302 response is not sufficient proof of EDXEIX record creation. Therefore v6.8.1 makes browser-fill/manual UI confirmation the live production path until server-side confirmation can be proven end-to-end.

## Operational path

1. Import Bolt mail.
2. Preview/create the local normalized EDXEIX preflight booking.
3. The preflight bridge clears old `no_edxeix` / `aade_receipt_only` flags only for that exact future mail-derived booking.
4. Arm browser fill for one exact booking.
5. Fetch locked payload with the Firefox extension.
6. Fill the EDXEIX browser form.
7. Review fields and submit manually inside EDXEIX.
8. Confirm the booking appears in the EDXEIX list.

## Safety

- No EDXEIX HTTP POST from browser-fill payload endpoint.
- No AADE action.
- No queue rows.
- No cookies or CSRF tokens exposed.
- Only one locked booking can be fetched.
