# gov.cabnet.app — Mapping Coverage Dashboard

This patch adds a guarded, read-only operations page:

```text
/ops/mappings.php
```

## Purpose

The page shows Bolt → EDXEIX mapping coverage for:

```text
mapping_drivers
mapping_vehicles
```

It helps identify which Bolt drivers and vehicles still need real EDXEIX IDs before a future Bolt trip can become live-ready.

## Safety contract

The dashboard is read-only.

It does not:

- call Bolt
- call EDXEIX
- post EDXEIX forms
- create submission jobs
- update mapping rows
- modify any database rows
- expose secrets

It is protected by the existing ops access guard through `.user.ini`.

## Features

- Driver mapping totals and coverage percentage
- Vehicle mapping totals and coverage percentage
- Filter by all, mapped, or unmapped rows
- Search by driver name, UUID, phone, plate, model, or source fields
- JSON view with `?format=json`
- Mobile-readable Bootstrap-like layout without external dependencies

## Verification

Open:

```text
https://gov.cabnet.app/ops/mappings.php
```

Expected current coverage, based on latest readiness:

```text
Drivers mapped: 1/2
Vehicles mapped: 2/15
```

Open unmapped view:

```text
https://gov.cabnet.app/ops/mappings.php?view=unmapped
```

Open JSON:

```text
https://gov.cabnet.app/ops/mappings.php?format=json
```

## Next safe enhancement

After this read-only dashboard is verified, a later patch can add a guarded mapping edit screen with CSRF protection and strict validation. That should remain separate from this read-only patch.
