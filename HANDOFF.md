# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

Updated: 2026-05-15  
Current milestone: v3.0.80–v3.0.99 legacy public utility audit/readiness checkpoint

## Project identity

- Domain: https://gov.cabnet.app
- GitHub repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow.
- Server layout:
  - `/home/cabnet/public_html/gov.cabnet.app`
  - `/home/cabnet/gov.cabnet.app_app`
  - `/home/cabnet/gov.cabnet.app_config`
  - `/home/cabnet/gov.cabnet.app_sql`
  - `/home/cabnet/tools/firefox-edxeix-autofill-helper`

## Source-of-truth order

1. Latest uploaded files, pasted code, screenshots, SQL output, or live audit output in the current chat.
2. `HANDOFF.md` and `CONTINUE_PROMPT.md`.
3. `README.md`, `SCOPE.md`, `DEPLOYMENT.md`, `SECURITY.md`, `docs/`, and `PROJECT_FILE_MANIFEST.md`.
4. GitHub repo.
5. Prior memory/context only as background, never as proof of current code state.

## Safety rules still active

- Default to read-only, dry-run, preview, audit, queue visibility, and preflight behavior.
- Do not enable live EDXEIX submission unless Andreas explicitly asks for a live-submit update.
- Live submission must remain blocked unless there is a real eligible future Bolt trip, preflight passes, and the trip is sufficiently in the future.
- Historical, cancelled, terminal, expired, invalid, or past Bolt orders must never be submitted to EDXEIX.
- Never request or expose real API keys, DB passwords, tokens, cookies, session files, or private credentials.
- Config examples may be committed; real config files must remain server-only and ignored by Git.
- Sanitize all downloadable zips.
- V0 / existing production workflows must remain untouched unless Andreas explicitly requests otherwise.

## Current live safety posture

- Live EDXEIX submission remains disabled.
- V3 live gate remains closed.
- The EDXEIX adapter remains skeleton/non-live.
- No EDXEIX call was made by this milestone.
- No AADE call was made by this milestone.
- No production submission tables were touched by this milestone.
- No SQL migrations were run for this milestone.
- Production pre-ride tool remained untouched:
  - `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php`
  - `https://gov.cabnet.app/ops/pre-ride-email-tool.php`

## Latest completed milestone

The v3.0.80–v3.0.99 cleanup/audit phase is complete and committed on the live/repo workflow.

The phase added or verified:

- v3.0.80 ops navigation de-bloat and route index/archive.
- v3.0.81–v3.0.82 public route exposure audit and `.htaccess` detection hotfix.
- v3.0.83–v3.0.86 public utility relocation/reference cleanup planning.
- v3.0.87 phase 1 public utility reference cleanup.
- v3.0.88–v3.0.91 Phase 2 preview and wrapper/noise filtering.
- v3.0.89 legacy public utility wrapper and registry.
- v3.0.90 wrapper/navigation links.
- v3.0.92–v3.0.93 legacy public utility usage audit and stable summary fields.
- v3.0.94–v3.0.95 quiet-period audit and stable classification fields.
- v3.0.96–v3.0.97 stats source audit and navigation link.
- v3.0.98–v3.0.99 aggregate legacy public utility readiness board and navigation link.

## Legacy utility checkpoint

The six reviewed legacy guarded public-root utilities remain in place:

```text
/bolt-api-smoke-test.php
/bolt-fleet-orders-watch.php
/bolt_stage_edxeix_jobs.php
/bolt_submission_worker.php
/bolt_sync_orders.php
/bolt_sync_reference.php
```

The latest readiness board confirmed:

```text
ok=true
version=v3.0.98-legacy-public-utility-readiness-board
move_now=0
delete_now=0
redirect_now=0
final_blocks=[]
```

No route retirement is approved.

## Current route classification

Future compatibility-stub review candidates only:

```text
/bolt-api-smoke-test.php
/bolt-fleet-orders-watch.php
```

Keep unchanged until more evidence review:

```text
/bolt_stage_edxeix_jobs.php
/bolt_submission_worker.php
/bolt_sync_orders.php
/bolt_sync_reference.php
```

Stats-source audit found:

```text
cpanel_only=4
live_log=0
move_now=0
delete_now=0
final_blocks=[]
```

## Known cosmetic issue

`/ops/_shell.php` note contains:

```text
legacystats source audit navigation
```

Preferred future text:

```text
legacy stats source audit navigation
```

This is cosmetic only and does not affect safety or function.

## Recommended next step

Commit this documentation milestone package.

Then the next safest development options are:

1. Tiny cosmetic shell-note patch only, if desired.
2. No-break quiet-period tracking/readiness docs.
3. Continue V3 pre-live work, still closed-gate and non-live.
4. Do not move/delete/redirect legacy public-root utilities without explicit approval.
