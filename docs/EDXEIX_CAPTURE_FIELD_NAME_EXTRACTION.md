# EDXEIX Capture Field Name Extraction — Phase 63

## Purpose

The EDXEIX Submit Capture form must store sanitized form metadata only.

Allowed:

- form method
- form action host/path
- field names
- select/dropdown field names
- map/address/latitude/longitude field names
- sanitized notes

Forbidden:

- cookies
- session values
- CSRF token values
- passwords
- credentials
- passenger data from real rides
- raw EDXEIX HTML containing private/session values

## Browser-console extractor

Run this only while viewing the EDXEIX form page in your already-authenticated browser session.
It reads field names and form structure only. It does not output field values.

```javascript
(() => {
  const clean = (v) => String(v || '').trim();
  const forms = Array.from(document.forms).map((form, formIndex) => {
    const fields = Array.from(form.querySelectorAll('input, select, textarea, button')).map((el) => ({
      tag: el.tagName.toLowerCase(),
      type: clean(el.getAttribute('type') || el.type || ''),
      name: clean(el.getAttribute('name') || ''),
      id: clean(el.id || ''),
      required: !!el.required,
      autocomplete: clean(el.getAttribute('autocomplete') || ''),
      options: el.tagName.toLowerCase() === 'select'
        ? Array.from(el.options).slice(0, 30).map((o) => ({ value_present: clean(o.value) !== '', text: clean(o.textContent).slice(0, 80) }))
        : []
    })).filter((f) => f.name || f.id);

    const names = [...new Set(fields.map((f) => f.name).filter(Boolean))];
    const requiredNames = [...new Set(fields.filter((f) => f.required && f.name).map((f) => f.name))];
    const selectNames = [...new Set(fields.filter((f) => f.tag === 'select' && f.name).map((f) => f.name))];
    const hiddenNames = [...new Set(fields.filter((f) => f.type === 'hidden' && f.name).map((f) => f.name))];
    const possibleCsrfNames = hiddenNames.filter((n) => /csrf|token|authenticity|_token/i.test(n));
    const possibleMapNames = names.filter((n) => /lat|lng|lon|coord|map|address|location|point/i.test(n));

    return {
      formIndex,
      method: clean(form.method || 'POST').toUpperCase(),
      action: clean(form.getAttribute('action') || form.action || ''),
      possibleCsrfNames,
      possibleMapNames,
      requiredNames,
      selectNames,
      hiddenNames,
      allNames: names,
      fields
    };
  });

  const result = {
    capturedAt: new Date().toISOString(),
    pageHost: location.host,
    pagePath: location.pathname,
    safety: {
      valuesIncluded: false,
      cookiesIncluded: false,
      sessionValuesIncluded: false,
      csrfTokenValuesIncluded: false
    },
    forms
  };

  const text = JSON.stringify(result, null, 2);
  console.log(text);
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(() => console.log('Sanitized field-name report copied to clipboard.'));
  }
  return result;
})();
```

## How to use the output

Open:

```text
https://gov.cabnet.app/ops/edxeix-submit-capture.php
```

Fill only sanitized metadata:

- **Capture status:** `candidate` while testing, `validated` only after manual verification.
- **Form method:** use the extractor `method`.
- **EDXEIX form action URL or path:** use the extractor `action`.
- **CSRF field name only:** use one of `possibleCsrfNames`; never paste the token value.
- **Map/address field name:** use the relevant entry from `possibleMapNames`.
- **Latitude/Longitude field names:** use relevant `lat/lng/lon` entries from `possibleMapNames`.
- **Required field names:** one field name per line, using actual EDXEIX `name` attributes.
- **Select/dropdown field names:** use `selectNames`, especially the actual names for lessor/company, driver, vehicle, and starting point.
- **Sanitized notes:** describe observations only; no private values.

After saving, open:

```text
https://gov.cabnet.app/ops/mobile-submit-qa-dashboard.php
```

Expected target:

```text
QA summary: 6/6
CAPTURE READY
LIVE SUBMIT BLOCKED
```
