# EDXEIX Pre-Ride Candidate v3.2.24 — Safe Source Diagnostics

## Purpose

v3.2.24 continues the ASAP automation path without enabling live EDXEIX submission.

Production validation of v3.2.23 showed the latest Maildir message was selected, but both the primary parser and fallback parser found zero usable labels. v3.2.24 adds opt-in source diagnostics so the decoded Maildir body structure can be inspected safely without printing or storing raw email content.

## Safety contract

- Dry-run by default.
- No EDXEIX HTTP transport.
- No AADE/myDATA call.
- No queue job creation.
- No `normalized_bookings` write.
- No raw email body storage.
- Source debug output is opt-in and redacts/truncates values.

## New CLI options

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php --json --latest-mail=1 --debug-source=1
```

Optional line count:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php --json --latest-mail=1 --debug-source=1 --debug-lines=40
```

Integrated EDXEIX diagnostic:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php --json --list-candidates=1 --limit=75 --pre-ride-latest=1 --pre-ride-debug-source=1
```

## Expected diagnostic fields

When debug is enabled the JSON includes:

- `source_debug.bytes`
- `source_debug.line_count`
- `source_debug.label_phrase_hit_fields`
- `source_debug.label_colon_hit_fields`
- `source_debug.redacted_structure_lines`

These are designed to reveal whether the loaded Maildir body is still encoded, missing expected labels, using unexpected wording, or not actually a usable Bolt pre-ride email.

## Next step after validation

Use the redacted structure diagnostics to adjust the candidate parser or Maildir selector. Do not enable one-shot transport until a candidate is parsed, future-safe, mapped, and explicitly approved.
