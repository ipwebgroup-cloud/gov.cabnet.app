# Recommendations

1. Rotate credentials that appeared in previous exported ZIPs before final production handoff.
2. Delete temporary public scripts from production after verification, especially cleanup/schema/path-check helpers.
3. Remove or ignore stale static `ops/index.html`; use `ops/index.php`.
4. Add HTTP auth/IP restrictions to `/ops` and sensitive JSON endpoints.
5. Keep real config files outside Git and outside public webroot.
6. Keep `app.dry_run = true` until a real future Bolt candidate passes readiness/preflight.
7. Require manual owner approval before enabling any live EDXEIX POST.
8. Create a small backup before every SQL migration.
9. Add a route audit checklist before each deployment.
10. Add a post-deploy readiness screenshot/report to each release package.
