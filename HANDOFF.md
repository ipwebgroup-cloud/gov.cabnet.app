# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

Updated: 2026-05-15  
Milestone: v3.0.82 public route exposure audit detection hotfix

## Current live posture

- Production pre-ride tool remains untouched: `/ops/pre-ride-email-tool.php`.
- V3 live EDXEIX submission remains disabled.
- Live adapter remains skeleton-only / non-live.
- Ops routes remain protected by global `.user.ini` auto-prepend auth.
- Navigation has been de-bloated without deleting routes.
- Public route exposure audit exists and is read-only.

## Latest patch

v3.0.82 fixes a false warning in the public route exposure audit. The live `.htaccess` already denies direct access to `.user.ini`, but v3.0.81 did not reliably detect escaped `FilesMatch` patterns. The audit detector now recognizes literal and escaped forms.

## Safety

No SQL changes. No route deletion. No Bolt call. No EDXEIX call. No AADE call. No DB connection. No filesystem writes from the audit.
