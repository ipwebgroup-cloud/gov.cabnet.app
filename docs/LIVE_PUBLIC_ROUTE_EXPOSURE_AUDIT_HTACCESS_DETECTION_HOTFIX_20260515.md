# Live Public Route Exposure Audit — .htaccess Detection Hotfix

Date: 2026-05-15  
Version: v3.0.82-public-route-exposure-audit-htaccess-detection

## Purpose

Fixes a false warning in the read-only public route exposure audit.

The live `.htaccess` already denies direct access to `.user.ini` through a `FilesMatch` rule, but the v3.0.81 audit regex did not reliably recognize the escaped form:

```apache
<FilesMatch "^(_auth_prepend\.php|config\.php|\.env|\.user\.ini)$">
    Require all denied
</FilesMatch>
```

## Change

- Adds a more robust `.htaccess` target-deny detector.
- Recognizes both literal and escaped filename forms.
- Keeps the audit read-only.
- No route changes.
- No SQL changes.
- No Bolt, EDXEIX, AADE, or DB calls.

## Expected result

On the live server, `htaccess_denies_user_ini` should now report `true`, and the false warning should disappear.

## Safety

Live EDXEIX submission remains disabled. Production pre-ride tool remains untouched.
