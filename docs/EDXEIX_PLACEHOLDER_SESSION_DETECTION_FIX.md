# EDXEIX Placeholder Session Detection Fix

This patch fixes the EDXEIX session readiness helper and the live-submit gate so copied example/template values are not treated as a real EDXEIX session.

Expected result while the example runtime session is present:

- session file exists: yes
- JSON valid: yes
- cookie/CSRF raw values present: yes
- placeholder/example values: detected
- session cookie/CSRF ready: no

No secrets are printed and no EDXEIX request is performed.
