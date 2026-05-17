# HANDOFF — gov.cabnet.app v3.2.30

Current ASAP track:

- v3.2.26 fixed pre-ride Maildir HTML fallback parsing.
- v3.2.27 added one-shot readiness packet.
- v3.2.28 added readiness watch.
- v3.2.29 added read-only transport rehearsal.
- v3.2.30 adds supervised one-candidate EDXEIX HTTP POST trace.

v3.2.30 is not unattended automation. It requires explicit candidate ID, exact payload hash, transport flag, and confirmation phrase.

Confirmation phrase:

```text
I UNDERSTAND POST THIS ONE PRE-RIDE CANDIDATE TO EDXEIX
```

The previously rehearsed candidate 2 was valid only while >30 minutes before pickup. Past/too-close candidates must remain blocked.

Never retry a candidate after confirmed success. Verify EDXEIX manually after any POST trace.
