# gov.cabnet.app .gitignore housekeeping — 2026-05-11

This patch updates `.gitignore` only.

Purpose:
- Keep server-only config files out of Git.
- Keep one-off deployment patch README/docs out of GitHub Desktop changes.
- Preserve existing runtime/log/archive ignore rules.

Upload/extract into the local GitHub Desktop repository root only.
No server upload is required for this patch.

After extracting, GitHub Desktop should show only `.gitignore` as the intended commit.
If `PATCH_README.md` or temporary docs still appear, they are already tracked or staged; unstage/delete them before committing.
