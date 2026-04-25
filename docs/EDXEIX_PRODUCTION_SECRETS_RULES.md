# EDXEIX Production Secrets Rules

The following values are secret and server-only:

- EDXEIX cookie headers
- EDXEIX CSRF tokens
- EDXEIX session JSON files
- live submit config values that contain session or auth data
- database passwords
- Bolt API credentials

Do not commit them. Do not paste them into chat. Do not include them in screenshots.

The application may report the following safe diagnostics:

- file exists: yes/no
- file readable: yes/no
- JSON valid: yes/no
- cookie present: yes/no
- cookie length in characters
- CSRF present: yes/no
- CSRF length in characters
- timestamp and age

The application must not print the actual cookie or CSRF value.
