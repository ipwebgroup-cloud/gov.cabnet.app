# gov.cabnet.app Ops UI Style Notes

Version: v3.0.38
Scope: Operations UI shell and V3 Pre-Ride dashboard polish.

## Canonical shell

The Ops Home visual language is the canonical UI baseline:

- White top navigation.
- Deep-blue left sidebar.
- Light gray content canvas.
- White cards with thin borders.
- Dark blue headings.
- Simple rectangular action buttons.
- Tab row below the page title.
- Clear safety banners.

The V3 dashboard should match this shell. Avoid creating a separate mini-app style for V3.

## Navigation groups

Top navigation direction:

```text
ΑΡΧΙΚΗ
MY START
LAUNCH
PRE-RIDE
WORKFLOW
HELPER
DOCS
ADMIN
PROFILE
```

Sidebar direction:

```text
Primary Workflow
Pre-Ride Safety
Live Submit Locked
Bolt Bridge
Evidence & Diagnostics
```

## Status colors

```text
Green  = safe / read-only / OK / disabled live submit
Amber  = caution / review / locked gate visibility
Red    = blocked / error / dangerous if enabled
Blue   = informational / monitor mode
Slate  = neutral admin/tooling
```

## Safety copy standard

Every V3 live-submit-adjacent page should visibly state:

```text
Live EDXEIX submission remains disabled.
This page is read-only unless explicitly stated otherwise.
```

## UI implementation rule

Prefer small additive PHP pages and shared partials. Do not introduce frameworks, Composer, Node build tools, or heavy dependencies.
