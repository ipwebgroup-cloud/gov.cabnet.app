# V3.2.11 — Maildir Fixture Writer Authorization Packet

## Purpose

Adds a read-only authorization packet for a future one-shot Maildir fixture writer. The packet consolidates:

- real-format fixture preview
- controlled Maildir writer design
- Maildir path preflight
- explicit Andreas request gate
- non-goals and safety posture

## Safety

This patch does not add an executable writer, does not create a Maildir file, does not trigger intake, does not mutate queue state, does not write the database, does not call Bolt, EDXEIX, or AADE, and does not enable live submit.

## CLI

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --maildir-writer-authorization-json
```

Aliases:

```bash
--fixture-writer-authorization-json
--one-shot-maildir-authorization-json
```

## Expected posture

The expected output is a read-only packet with `authorization_packet_only=true`, `executable_mail_writer_added=false`, `maildir_write_allowed_now=false`, `maildir_write_made=false`, and `future_patch_required_for_maildir_write=true`.
