# EDXEIX Firefox Extension — Fixed URL + No Phrase Workflow

This patch refines the private Firefox extension used by authorized CABnet operators to refresh the EDXEIX session prerequisites.

## Change summary

The extension now assumes the EDXEIX lease agreement submit URL is always:

```text
https://edxeix.yme.gov.gr/dashboard/lease-agreement
```

Operators no longer manually provide the URL through the extension.

The extension confirmation phrase was also removed to reduce friction. The endpoint is still guarded by `/ops` access controls and still validates/saves only server-side prerequisites.

## New operator workflow

1. Log in to EDXEIX.
2. Click `+ Ανάρτηση σύμβασης`.
3. Confirm the browser is on:

```text
https://edxeix.yme.gov.gr/dashboard/lease-agreement/create
```

4. Click the CABnet EDXEIX Capture Firefox extension.
5. Click **Capture from EDXEIX tab**.
6. Click **Save to gov.cabnet.app**.
7. Verify:

```text
https://gov.cabnet.app/ops/edxeix-session.php
https://gov.cabnet.app/ops/live-submit.php
```

## Expected result after capture

```text
EDXEIX session ready: yes
EDXEIX submit URL configured: yes
Live flag: disabled
HTTP flag: disabled
Live HTTP execution: no
```

## Safety

The capture endpoint still does not call EDXEIX, does not call Bolt, does not write to the database, does not print secrets, and does not enable live submission.

The server endpoint forces:

```php
live_submit_enabled = false
http_submit_enabled = false
```

The final live HTTP transport remains intentionally blocked until Andreas explicitly approves the final live-submit patch.
