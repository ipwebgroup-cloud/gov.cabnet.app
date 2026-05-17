# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

Current state: v3.2.31 candidate closure / retry prevention / EDXEIX form-token diagnostic.

Latest proven facts:
- v3.2.30 performed one supervised POST for candidate 4.
- EDXEIX returned HTTP 419 / Ο χρόνος σύνδεσης έληξε.
- The server-side POST is not confirmed saved.
- Andreas submitted the real ride manually through V0 from the laptop.
- Candidate 4 must not be retried server-side.

v3.2.31 purpose:
- Add closure table and mark-manual tooling.
- Archive manually submitted candidates.
- Fix latest-ready selection.
- Add form-token diagnostic GET.
- Hold future server-side POST attempts until fresh token integration.

V0 production must remain untouched.
