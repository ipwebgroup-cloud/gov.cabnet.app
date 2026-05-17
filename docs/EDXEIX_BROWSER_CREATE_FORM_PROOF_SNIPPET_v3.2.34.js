/*
 * gov.cabnet.app — EDXEIX browser create-form proof snippet v3.2.34
 * Run only on the logged-in EDXEIX create page:
 * https://edxeix.yme.gov.gr/dashboard/lease-agreement/create
 *
 * It copies sanitized JSON to clipboard.
 * It does NOT copy cookies, raw CSRF token, raw _token value, raw HTML, or field values.
 */
(async () => {
  const expected = [
    'lessor', 'driver', 'vehicle', 'starting_point', 'boarding_point',
    'disembark_point', 'lessee', 'started_at', 'ended_at', 'price'
  ];
  const enc = new TextEncoder();
  const sha256hex = async (value) => {
    const buf = await crypto.subtle.digest('SHA-256', enc.encode(String(value || '')));
    return Array.from(new Uint8Array(buf)).map(b => b.toString(16).padStart(2, '0')).join('');
  };
  const uniq = (arr) => Array.from(new Set(arr.filter(Boolean).map(v => String(v).trim()).filter(Boolean)));
  const attr = (el, name) => (el && el.getAttribute(name)) ? el.getAttribute(name) : '';
  const forms = Array.from(document.querySelectorAll('form'));
  const scoreForm = (form) => {
    const names = Array.from(form.querySelectorAll('input,select,textarea')).map(el => attr(el, 'name'));
    return expected.filter(name => names.includes(name)).length;
  };
  let form = forms.slice().sort((a, b) => scoreForm(b) - scoreForm(a))[0] || null;
  const els = form ? Array.from(form.querySelectorAll('input,select,textarea')) : [];
  const inputs = form ? Array.from(form.querySelectorAll('input')) : [];
  const selects = form ? Array.from(form.querySelectorAll('select')) : [];
  const textareas = form ? Array.from(form.querySelectorAll('textarea')) : [];
  const hidden = inputs.filter(el => String(attr(el, 'type')).toLowerCase() === 'hidden');
  const tokenEl = form ? form.querySelector('input[name="_token"]') : null;
  const token = tokenEl ? String(tokenEl.value || '') : '';
  const tokenHash = token ? (await sha256hex(token)).slice(0, 16) : '';
  const names = uniq(els.map(el => attr(el, 'name')));
  const proof = {
    version: 'v3.2.34-browser-create-form-proof',
    captured_at: new Date().toISOString(),
    page_url: location.href,
    final_url: location.href,
    document_title: document.title || '',
    form_present: !!form,
    form_method: form ? String(attr(form, 'method') || 'GET').toUpperCase() : '',
    form_action_safe: form ? new URL(attr(form, 'action') || location.href, location.href).href : '',
    token_present: !!token,
    token_hash_16: tokenHash,
    input_names: uniq(inputs.map(el => attr(el, 'name'))),
    select_names: uniq(selects.map(el => attr(el, 'name'))),
    textarea_names: uniq(textareas.map(el => attr(el, 'name'))),
    hidden_names: uniq(hidden.map(el => attr(el, 'name'))),
    all_field_names: names,
    expected_fields_present: expected.filter(name => names.includes(name)),
    expected_fields_missing: expected.filter(name => !names.includes(name)),
    counts: {
      forms: forms.length,
      inputs: inputs.length,
      selects: selects.length,
      textareas: textareas.length,
      hidden: hidden.length,
      all_names: names.length
    },
    safety: {
      raw_cookie_included: false,
      raw_token_included: false,
      raw_csrf_included: false,
      raw_html_included: false,
      field_values_included: false,
      edxeix_post_performed: false
    }
  };
  const text = JSON.stringify(proof, null, 2);
  try {
    await navigator.clipboard.writeText(text);
    alert('Gov Cabnet v3.2.34 sanitized EDXEIX form proof copied to clipboard. Paste it into /ops/edxeix-browser-create-form-proof.php');
  } catch (err) {
    console.log(text);
    alert('Clipboard copy failed. The sanitized proof was printed to the browser console.');
  }
})();
