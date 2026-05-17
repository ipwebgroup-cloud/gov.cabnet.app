# gov.cabnet.app — EDXEIX Pre-Ride Candidate Closure v3.2.31

## Purpose

v3.2.31 is a safety patch after the supervised v3.2.30 one-shot POST returned HTTP 419 / session expired.

It keeps V0 production untouched and adds only the diagnostic/automation-track safety pieces needed before the next attempt:

1. Mark a captured candidate as manually submitted via V0/laptop.
2. Archive that candidate so `--latest-ready=1` does not select it again.
3. Block retry by candidate ID, source hash, or payload hash.
4. Fix latest-ready candidate selection so old/past candidate rows are not reused.
5. Add an EDXEIX create-form/session-token diagnostic GET before future transport work.
6. Hold further server-side POST attempts until a later fresh-form-token integration patch.

## Production V0 impact

None intended.

This patch does not change the laptop/browser V0 workflow and does not modify production V0 routes.

## SQL

Run the additive table migration:

```bash
mysql -u cabnet_gov -p cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_05_17_edxeix_pre_ride_candidate_closures.sql
```

## Mark candidate 4 manually submitted via V0

After the SQL is installed:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_mark_manual.php \
  --candidate-id=4 \
  --method=v0_laptop_manual \
  --submitted-by=Andreas \
  --note='Real ride submitted manually through V0/laptop after server POST returned HTTP 419 session expired.' \
  --json
```

Expected:

```text
ok: true
closure_status: manual_submitted_v0
candidate_id: 4
```

## Verify retry prevention

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php --candidate-id=4 --json
```

Expected blocker:

```text
candidate_closed_or_manually_submitted_via_v0
```

## EDXEIX form-token diagnostic

The transport trace page/CLI now includes `form_token_diagnostic`.

It performs a safe GET to the EDXEIX submit URL using the saved session cookie and reports:

- HTTP status
- sanitized title/excerpt fingerprint
- whether an `_token` hidden input was found
- token hash prefix only
- session CSRF hash prefix only
- whether the form token matches the saved session token

It never prints cookies, CSRF tokens, raw tokens, or raw response HTML.

## Safety

v3.2.31 performs no EDXEIX POST, no AADE/myDATA call, no queue job, no normalized booking write, and no live config write.

A later patch must integrate a freshly fetched form token before another server-side transport test.
