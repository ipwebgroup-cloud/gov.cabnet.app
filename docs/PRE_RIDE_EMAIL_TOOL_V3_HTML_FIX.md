# Pre-Ride Email Tool V3 — HTML-only email body parser fix

V3 hotfix: `v3.0.1-isolated-parser-html-block-fix` and `v3.0.1-isolated-maildir-loader-html-block-fix`.

## Issue

Some Bolt pre-ride emails arrive as HTML-only fragments like:

```html
<p dir="ltr"><strong>Customer:</strong> Name</p>
<p dir="ltr"><strong>Driver:</strong> Driver Name</p>
```

The first isolated V3 parser handled `<br>`, `<html>`, and `<div>` fragments, but not `<p>`-only fragments. As a result, the labels stayed embedded in raw HTML and the parser reported missing fields.

## Fix

The isolated V3 parser and Maildir loader now:

- Decode HTML entities before tag handling.
- Detect any HTML tag, not only `<br>`, `<html>`, or `<div>`.
- Convert block tags such as `<p>`, `<div>`, `<li>`, `<tr>`, and headings into newlines.
- Strip remaining tags.
- Preserve one field per line so `Label: Value` parsing works.

## Safety

No production file is changed. No DB writes, no EDXEIX calls, no AADE calls, and no queue jobs are introduced.
