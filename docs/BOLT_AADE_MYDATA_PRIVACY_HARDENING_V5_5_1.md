# v5.5.1 AADE/myDATA Privacy Hardening

This patch suppresses AADE/myDATA response excerpts from CLI and dashboard output.

## Why

A successful production connectivity ping can return real transmitted-document data from AADE/myDATA. That text must not be printed in terminal output, browser JSON, logs, or copied into support chats.

## Changed files

- `gov.cabnet.app_app/src/Receipts/AadeMyDataClient.php`
- `gov.cabnet.app_app/cli/aade_mydata_readiness.php`
- `public_html/gov.cabnet.app/ops/aade-mydata-readiness.php`

## Behavior

The AADE client now returns only:

- ok / failed
- HTTP status
- cURL status/error
- response byte length
- response SHA-256 hash
- explicit `response_excerpt_suppressed=true`

It does not return response body excerpts.

## Safety

This patch does not send invoices, send emails, call EDXEIX, create jobs, create attempts, import mail, or change live-submit gates.
