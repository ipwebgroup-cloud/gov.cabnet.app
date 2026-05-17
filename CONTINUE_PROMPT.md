Continue the gov.cabnet.app Bolt → EDXEIX bridge from v3.2.33.

V0 production/laptop workflow must remain untouched.

Current focus: server-side EDXEIX session/CSRF/form-token diagnostics. Candidate 4 is archived as manually submitted via V0 and must not be retried. v3.2.33 adds a read-only diagnostic for `/dashboard/lease-agreement/create` to determine whether the server session can fetch a valid create form token.

Next command to request from Andreas:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/edxeix_create_form_token_diagnostic.php --json
```

No EDXEIX POST unless explicitly approved again for a new real eligible future candidate.
