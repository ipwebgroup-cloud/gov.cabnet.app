# V3 Fast Pipeline Exit-Code Fix

Fixes the V3 fast pipeline runner so child process exit codes are preserved correctly when using `proc_get_status()` before `proc_close()`.

## Problem

The pipeline was showing every step as `FAIL` even when child scripts completed normally and printed successful/no-op output. This happened because PHP can return `-1` from `proc_close()` after `proc_get_status()` has already observed the process exit code.

## Fix

The runner now stores the observed child exit code from `proc_get_status()` and uses it if `proc_close()` returns `-1`.

## Safety

- No EDXEIX calls.
- No AADE calls.
- No production submission table writes.
- No production pre-ride email tool changes.
- Existing V3-only commit behavior is unchanged.
