# Live Public Utility Relocation Plan Hotfix — 2026-05-15

The v3.0.83 public utility relocation planner was read-only but failed on production when scanning an unreadable private directory under app storage.

v3.0.84 replaces the recursive iterator with a permission-safe scanner that uses `scandir()`, skips unreadable directories, and excludes runtime, artifacts, logs, temp, cache, patch backup, package, vendor, and node_modules paths.

No routes were moved or deleted. No SQL was changed. No external calls are made. The production pre-ride tool remains untouched.
