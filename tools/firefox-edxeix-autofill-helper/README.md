# Gov Cabnet EDXEIX Firefox Helper v6.6.15

Local temporary Firefox helper for filling the logged-in EDXEIX rental-contract form from the saved gov.cabnet.app pre-ride payload.

## Important

- No credentials, cookies, or CSRF tokens are stored or exported.
- No server-side EDXEIX call is made by gov.cabnet.app.
- Old/past rides must not be posted.
- The helper fills visible fields and blocks POST unless the trip is future and a map point exists.

## v6.6.15

This version stabilizes the fill after EDXEIX JavaScript resets the form: it selects driver/vehicle/starting point first, waits, then re-applies text/date/price fields for several seconds.
