# Ops UI Shell Phase 11 — Firefox Extension Pair Status — 2026-05-11

This phase adds a read-only `/ops/firefox-extensions-status.php` page for the current two-helper Firefox workflow.

## Safety contract

- Does not modify `/ops/pre-ride-email-tool.php`.
- Does not call Bolt.
- Does not call EDXEIX.
- Does not call AADE.
- Does not write workflow data.
- Does not install or modify browser extensions.
- Keeps the current instruction: both temporary Firefox helpers remain loaded until a merged helper is separately built and tested.

## Purpose

The page inventories the two current helper roles:

1. `Cabnet EDXEIX Session + Payload Fill`
2. `Gov Cabnet EDXEIX Autofill Helper`

It displays detected server source folders, manifest details, safe source file counts, modified dates, SHA-256 values, and a merge checklist.

## Notes

If the browser shows a helper loaded but the page reports its server folder as missing, confirm the exact folder under `/home/cabnet/tools/` before attempting any merge or packaging work.
