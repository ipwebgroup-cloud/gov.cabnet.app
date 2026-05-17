# gov.cabnet.app Handoff — 2026-05-17 v3.2.24 ASAP Automation Track

Current posture:

- Production V0 remains unaffected.
- EDXEIX live submission remains blocked.
- Session file is ready, but no eligible real future Bolt candidate exists.
- Future guard is set to 30 minutes in both server config files.
- v3.2.22 added pre-ride future candidate diagnostics and optional sanitized metadata capture.
- v3.2.23 added diagnostics-only fallback parsing, but latest Maildir validation still showed zero primary and fallback fields.
- v3.2.24 adds opt-in safe source diagnostics to inspect the decoded Maildir body structure without printing/storing raw email content.

Next safest step:

Run:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_diagnostic.php --json --latest-mail=1 --debug-source=1
```

Use `source_debug.label_phrase_hit_fields`, `source_debug.label_colon_hit_fields`, and `source_debug.redacted_structure_lines` to decide whether the Maildir loader selected the wrong email, the body is encoded/unexpected, or the parser needs a new label pattern.

Do not enable one-shot transport until a candidate is parsed, future-safe, mapped, and explicitly approved.
