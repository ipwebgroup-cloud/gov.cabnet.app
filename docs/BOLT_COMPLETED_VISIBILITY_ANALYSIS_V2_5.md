# Bolt Completed-Order Visibility Analysis v2.5

Adds `/ops/completed-visibility.php`.

## Purpose

Analyze the sanitized Bolt visibility timeline from a real test and clearly report whether the watched ride appeared before completion or only after completion.

## Main finding supported by the 2026-04-27 test

The watched ride was not visible during accepted/assigned, pickup/waiting, or trip-started captures. It appeared after completion/auto-watch.

## Safety

The page reads sanitized JSONL evidence files only.

It does not:
- call Bolt
- call EDXEIX
- read/write the database
- stage jobs
- update mappings
- enable live submission
