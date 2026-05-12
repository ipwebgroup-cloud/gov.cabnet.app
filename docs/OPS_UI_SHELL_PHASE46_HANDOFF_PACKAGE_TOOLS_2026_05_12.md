# Ops UI Shell Phase 46 — Handoff Package Tools

Adds an admin-only read-only handoff package tools hub.

## Added

- `public_html/gov.cabnet.app/ops/handoff-package-tools.php`

## Purpose

Centralizes links and status checks for:

- Handoff Center
- Package Inspector
- CLI Builder Guide
- Package Archive
- Package Validator

## Safety

This page is read-only. It does not build packages, export the database, validate ZIPs, extract packages, read package contents, display secrets, call Bolt, call EDXEIX, call AADE, write workflow data, stage jobs, or enable live submission.

Generated packages that include `DATABASE_EXPORT.sql` remain private operational material.
