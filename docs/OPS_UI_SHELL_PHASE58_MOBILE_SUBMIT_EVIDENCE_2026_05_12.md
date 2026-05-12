# Ops UI Shell Phase 58 — Mobile Submit Evidence Snapshot

Date: 2026-05-12

## Summary

Adds a read-only evidence snapshot page for the future mobile/server-side EDXEIX submit workflow.

Route:

```text
/ops/mobile-submit-evidence.php
```

The page parses a real Bolt pre-ride email, resolves EDXEIX IDs, checks lessor-specific starting point evidence, reads the latest sanitized EDXEIX submit capture, runs the preflight gate, builds a disabled connector preview, validates the payload, and outputs sanitized JSON evidence.

## Safety contract

The page does not:

```text
call Bolt
call EDXEIX
call AADE
write database rows
stage jobs
enable live EDXEIX submission
print raw email text in evidence JSON
print cookies/session/CSRF token values
print real credentials/config values
```

## Purpose

This page creates a copyable evidence snapshot for debugging and future-session continuity without exposing raw email content. It records the raw email SHA-256 hash and parsed/mapped facts only.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-evidence.php
```

Expected:

```text
No syntax errors detected
```

Open:

```text
https://gov.cabnet.app/ops/mobile-submit-evidence.php
```

Expected:

- login required
- shared ops shell loads
- latest/pasted email can be evaluated
- sanitized evidence JSON displays
- raw email is not included in evidence JSON
- live submit remains blocked
- production pre-ride tool remains unchanged
