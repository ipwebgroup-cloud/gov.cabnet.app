# EDXEIX Pre-Ride Candidate v3.2.25

## Purpose

Patch v3.2.25 hardens the diagnostics-only pre-ride candidate parser after v3.2.24 proved that the loaded Maildir body contains usable labels inside HTML rows such as `<p><strong>Operator: ...`.

The previous fallback parser only cleaned certain HTML structures and missed `<p>/<strong>` rows. This patch cleans those rows before fallback extraction, while continuing to redact diagnostic output and avoid storing raw email bodies.

## Safety posture

- Dry-run by default.
- No EDXEIX HTTP transport.
- No AADE/myDATA call.
- No submission job creation.
- No normalized booking insert/update.
- Existing production V0 pre-ride parser file is not changed.
- Raw email body is not stored.

## Expected diagnostic change

The command:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php --json --latest-mail=1 --debug-source=1
```

should now report fallback diagnostics similar to:

```json
{
  "parser_fallback": {
    "used": true,
    "diagnostics": {
      "fallback_html_cleanup_applied": true,
      "fallback_label_hits": 10
    }
  }
}
```

If the email is old, incomplete, unmapped, or not at least +30 minutes in the future, it must remain blocked.

## Next gate

Only after a future pre-ride candidate is parsed and passes mapping/future/exclusion checks should Andreas consider a supervised one-shot transport diagnostic.
