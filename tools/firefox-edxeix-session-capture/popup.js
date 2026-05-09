'use strict';

const EXT_VERSION = '0.1.4';
const EDXEIX_ORIGIN = 'https://edxeix.yme.gov.gr';
const GOV_ORIGIN = 'https://gov.cabnet.app';
const CAPTURE_URL = GOV_ORIGIN + '/ops/edxeix-session-capture.php';
const PAYLOAD_URL = GOV_ORIGIN + '/edxeix-extension-payload.php';

function $(id) {
  return document.getElementById(id);
}

function setStatus(message, kind = 'ok') {
  const el = $('status');
  el.textContent = message;
  el.className = kind === 'bad' ? 'warn' : 'okbox';
}

function safeJsonPreview(obj) {
  $('payloadPreview').textContent = JSON.stringify(obj, null, 2);
}

async function getActiveTab() {
  const tabs = await browser.tabs.query({ active: true, currentWindow: true });
  if (!tabs || !tabs[0]) {
    throw new Error('No active tab found.');
  }
  return tabs[0];
}

async function requireActiveEdxeixTab() {
  const tab = await getActiveTab();
  if (!tab.url || !tab.url.startsWith(EDXEIX_ORIGIN + '/')) {
    throw new Error('Active tab must be an EDXEIX page.');
  }
  return tab;
}

async function saveSessionFromCurrentTab() {
  setStatus('Reading current EDXEIX tab and cookies...');

  const tab = await requireActiveEdxeixTab();

  const cookies = await browser.cookies.getAll({ url: EDXEIX_ORIGIN });
  const cookieHeader = cookies
    .filter(c => c && c.name && typeof c.value === 'string')
    .map(c => `${c.name}=${c.value}`)
    .join('; ');

  if (!cookieHeader) {
    throw new Error('No EDXEIX cookies found. Make sure you are logged in.');
  }

  const csrfResults = await browser.tabs.executeScript(tab.id, {
    code: `(() => {
      const input = document.querySelector('input[name="_token"]');
      const meta = document.querySelector('meta[name="csrf-token"]');
      return (input && input.value) || (meta && meta.content) || '';
    })();`
  });

  const csrfToken = (csrfResults && csrfResults[0]) ? String(csrfResults[0]) : '';

  const body = new URLSearchParams();
  body.set('cookie_header', cookieHeader);
  body.set('csrf_token', csrfToken);
  body.set('extension_version', EXT_VERSION);

  const res = await fetch(CAPTURE_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
    body: body.toString(),
    cache: 'no-store'
  });

  const text = await res.text();
  let json;
  try {
    json = JSON.parse(text);
  } catch (e) {
    throw new Error('Session capture endpoint did not return JSON.');
  }

  safeJsonPreview({
    ok: json.ok,
    script: json.script,
    session_ready: json.session_ready,
    live_submit_enabled: json.live_submit_enabled,
    http_submit_enabled: json.http_submit_enabled,
    message: json.message || null
  });

  if (!res.ok || !json.ok) {
    throw new Error(json.error || json.message || 'Session capture failed.');
  }

  setStatus('EDXEIX browser session saved server-side. Live server submit remains disabled.');
}

async function fetchLockedPayload() {
  setStatus('Fetching locked payload...');

  const bookingId = $('bookingId').value.trim();
  const url = new URL(PAYLOAD_URL);
  if (bookingId) {
    url.searchParams.set('booking_id', bookingId);
  }

  const res = await fetch(url.toString(), { cache: 'no-store' });
  const text = await res.text();

  let json;
  try {
    json = JSON.parse(text);
  } catch (e) {
    throw new Error('Payload endpoint did not return JSON.');
  }

  if (!res.ok || !json.ok) {
    safeJsonPreview(json);
    throw new Error(json.error || json.message || 'Payload is not ready.');
  }

  await browser.storage.local.set({ edxeixLockedPayload: json });

  safeJsonPreview({
    ok: true,
    booking_id: json.booking_id,
    order_reference: json.order_reference,
    payload_hash: json.payload_hash,
    driver: json.analysis_summary && json.analysis_summary.driver_name,
    vehicle_plate: json.analysis_summary && json.analysis_summary.vehicle_plate,
    started_at: json.analysis_summary && json.analysis_summary.started_at,
    price: json.payload && json.payload.price
  });

  setStatus('Locked payload loaded. Open the EDXEIX create form tab and click fill.');
}

function fillEdxeixPayloadIntoPage(payload) {
  const summary = { filled: [], missing: [], skipped: [] };

  function attrEscape(value) {
    return String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
  }

  function byNameOrId(names) {
    for (const name of names) {
      const byId = document.getElementById(name);
      if (byId) return byId;

      const byName = document.querySelector(`[name="${attrEscape(name)}"]`);
      if (byName) return byName;
    }
    return null;
  }

  function fire(el) {
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
    if (window.jQuery) {
      try { window.jQuery(el).trigger('change'); } catch (e) {}
    }
  }

  function setValue(names, value, label) {
    if (value === undefined || value === null || String(value) === '') {
      summary.skipped.push(label);
      return;
    }

    const el = byNameOrId(names);
    if (!el) {
      summary.missing.push(label);
      return;
    }

    let str = String(value);

    if (el.type === 'datetime-local') {
      str = str.replace(' ', 'T').slice(0, 16);
    }

    if (el.tagName === 'SELECT') {
      let matched = false;
      for (const opt of Array.from(el.options || [])) {
        if (String(opt.value) === str || String(opt.textContent).trim() === str) {
          el.value = opt.value;
          matched = true;
          break;
        }
      }
      if (!matched) {
        el.value = str;
      }
    } else if (el.type === 'checkbox') {
      el.checked = ['1', 'true', 'yes', 'on'].includes(str.toLowerCase());
    } else {
      el.value = str;
    }

    el.style.outline = '2px solid #16a34a';
    fire(el);
    summary.filled.push(label);
  }

  function setRadio(names, value, label) {
    if (value === undefined || value === null || String(value) === '') {
      summary.skipped.push(label);
      return;
    }

    const str = String(value);

    for (const name of names) {
      const radio = document.querySelector(`input[type="radio"][name="${attrEscape(name)}"][value="${attrEscape(str)}"]`);
      if (radio) {
        radio.checked = true;
        radio.style.outline = '2px solid #16a34a';
        fire(radio);
        summary.filled.push(label);
        return;
      }
    }

    setValue(names, value, label);
  }

  const lessee = payload.lessee || {};

  setValue(['broker', 'broker_id'], payload.broker, 'broker');
  setValue(['lessor', 'lessor_id'], payload.lessor, 'lessor');

  setRadio(['lessee[type]', 'lessee_type', 'customer_type'], lessee.type, 'lessee type');
  setValue(['lessee[name]', 'lessee_name', 'customer_name'], lessee.name, 'lessee name');
  setValue(['lessee[vat_number]', 'lessee_vat_number', 'customer_vat_number'], lessee.vat_number, 'lessee VAT');
  setValue(['lessee[legal_representative]', 'lessee_legal_representative', 'customer_representative'], lessee.legal_representative, 'lessee representative');

  setValue(['driver', 'driver_id'], payload.driver, 'driver');
  setValue(['vehicle', 'vehicle_id'], payload.vehicle, 'vehicle');
  setValue(['starting_point', 'starting_point_id'], payload.starting_point || payload.starting_point_id, 'starting point');

  setValue(['boarding_point', 'pickup_address'], payload.boarding_point, 'boarding point');
  setValue(['coordinates'], payload.coordinates, 'coordinates');
  setValue(['disembark_point', 'destination_address'], payload.disembark_point, 'disembark point');

  setValue(['drafted_at'], payload.drafted_at, 'drafted at');
  setValue(['started_at'], payload.started_at, 'started at');
  setValue(['ended_at'], payload.ended_at, 'ended at');
  setValue(['price'], payload.price, 'price');

  const notice = document.createElement('div');
  notice.textContent = `Cabnet filled EDXEIX form fields. Filled: ${summary.filled.length}. Missing: ${summary.missing.length}. Review manually before submitting.`;
  notice.style.cssText = 'position:fixed;z-index:999999;left:16px;right:16px;bottom:16px;background:#064e3b;color:white;padding:12px;border-radius:8px;font:14px Arial,sans-serif;box-shadow:0 8px 30px rgba(0,0,0,.25)';
  document.body.appendChild(notice);
  setTimeout(() => notice.remove(), 12000);

  return summary;
}

async function fillCurrentForm() {
  setStatus('Filling active EDXEIX form tab...');

  const tab = await requireActiveEdxeixTab();
  const stored = await browser.storage.local.get('edxeixLockedPayload');
  const locked = stored.edxeixLockedPayload;

  if (!locked || !locked.payload) {
    throw new Error('No locked payload loaded. Click “Fetch locked payload” first.');
  }

  const code = `(${fillEdxeixPayloadIntoPage.toString()})(${JSON.stringify(locked.payload)});`;
  const result = await browser.tabs.executeScript(tab.id, { code });
  const summary = result && result[0] ? result[0] : {};

  safeJsonPreview({
    ok: true,
    booking_id: locked.booking_id,
    order_reference: locked.order_reference,
    fill_summary: summary
  });

  setStatus('Form filled. Review every field in EDXEIX, then submit manually.');
}

async function run(handler) {
  try {
    await handler();
  } catch (err) {
    setStatus(err && err.message ? err.message : String(err), 'bad');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  $('saveSession').addEventListener('click', () => run(saveSessionFromCurrentTab));
  $('fetchPayload').addEventListener('click', () => run(fetchLockedPayload));
  $('fillForm').addEventListener('click', () => run(fillCurrentForm));

  browser.storage.local.get('edxeixLockedPayload').then((stored) => {
    if (stored && stored.edxeixLockedPayload) {
      safeJsonPreview({
        cached_booking_id: stored.edxeixLockedPayload.booking_id,
        cached_order_reference: stored.edxeixLockedPayload.order_reference,
        cached_payload_hash: stored.edxeixLockedPayload.payload_hash
      });
    }
  }).catch(() => {});
});
