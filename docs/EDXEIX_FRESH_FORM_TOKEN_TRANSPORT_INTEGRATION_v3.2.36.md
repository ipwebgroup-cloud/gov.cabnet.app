# EDXEIX Fresh Create-Form Token Transport Integration — v3.2.36

Purpose: integrate the authenticated EDXEIX create-form token into the supervised pre-ride one-shot transport trace.

## Safety

- No V0 production route changes.
- No cron or unattended worker.
- No AADE/myDATA call.
- No queue job.
- No normalized booking write.
- No live_submit.php write.
- No raw cookies, raw CSRF, raw `_token`, or raw HTML are printed or stored.
- Candidate 4 remains closed/manual V0 submitted and is retry-blocked.

## What changed

Before a supervised transport, v3.2.36 performs a read-only GET to `/dashboard/lease-agreement/create`, validates the real lease-agreement form, extracts the hidden `_token` internally, replaces the stale saved CSRF value for this one POST only, then removes the token from all output.

Transport still requires:

```bash
--candidate-id=N
--transport=1
--expected-payload-hash=HASH
--confirm='I UNDERSTAND POST THIS ONE PRE-RIDE CANDIDATE TO EDXEIX'
```

## Required workflow

1. Save current EDXEIX browser session from the extension.
2. Verify token readiness:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/edxeix_create_form_token_diagnostic.php --json
```

3. Capture a new future pre-ride candidate.
4. Dry-run the transport trace for that explicit candidate.
5. Only if armable, run one supervised POST.
6. Verify manually in EDXEIX.

Do not reuse candidate 4.
