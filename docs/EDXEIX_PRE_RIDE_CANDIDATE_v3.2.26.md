# EDXEIX Pre-Ride Candidate v3.2.26

## Purpose

Fix the diagnostics-only fallback parser after v3.2.25 validation showed HTML label rows were detected but fallback fields remained empty.

## Root cause

`preg_match_all()` returns the number of matches. A normal Bolt pre-ride email has many labels, but v3.2.25 accepted only exactly one match.

## Fix

The fallback parser now accepts any positive match count and rejects only `false` or zero.

## Safety

- No EDXEIX transport.
- No AADE call.
- No queue job.
- No normalized booking write.
- No Production V0 parser change.
- No raw email body storage.

## Expected validation

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php --json --latest-mail=1 --debug-source=1
```

Expected parser block:

```text
parser_fallback.used: true
fallback_html_cleanup_applied: true
fallback_label_hits: greater than 1
parsed_fields populated
```

Candidate may remain blocked if the ride is not future, mapping is incomplete, or an exclusion applies.
