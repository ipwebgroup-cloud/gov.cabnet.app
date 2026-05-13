(function () {
  'use strict';
  if (typeof browser === 'undefined' || !browser.storage || !browser.storage.local) { return; }
  if (!/edxeix\.yme\.gov\.gr$/.test(location.hostname)) { return; }

  var VERSION = 'v3.0.16-helper-fill-callback';
  var PANEL_ID = 'gov-cabnet-edxeix-helper-v3-panel';
  var CALLBACK_URL = 'https://gov.cabnet.app/ops/pre-ride-email-v3-helper-callback.php';
  var lastResults = [];

  function qs(sel, root) { try { return (root || document).querySelector(sel); } catch (e) { return null; } }
  function qsa(sel, root) { try { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); } catch (e) { return []; } }
  function txt(v) { return String(v == null ? '' : v).trim(); }
  function two(v) { v = String(v || ''); return v.length === 1 ? '0' + v : v; }
  function norm(v) {
    return txt(v).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/[ίϊΐ]/g, 'ι').replace(/[ή]/g, 'η').replace(/[ύϋΰ]/g, 'υ')
      .replace(/[ό]/g, 'ο').replace(/[ά]/g, 'α').replace(/[έ]/g, 'ε')
      .replace(/[ώ]/g, 'ω').replace(/ς/g, 'σ').replace(/[^a-z0-9α-ω]+/g, ' ')
      .replace(/\s+/g, ' ').trim();
  }
  function delay(ms) { return new Promise(function (resolve) { setTimeout(resolve, ms); }); }
  function mark(el, color) { if (el && el.style) { el.style.outline = '3px solid ' + (color || '#6d28d9'); el.style.outlineOffset = '2px'; } }
  function fire(el) {
    if (!el) { return; }
    ['input', 'change', 'keyup', 'keydown', 'blur'].forEach(function (type) {
      try { el.dispatchEvent(new Event(type, { bubbles: true, cancelable: true })); } catch (e) {}
    });
    try { if (typeof el.onchange === 'function') { el.onchange(); } } catch (e) {}
    try { if (window.jQuery) { window.jQuery(el).trigger('input').trigger('change').trigger('blur'); } } catch (e) {}
  }
  function nativeSet(el, value) {
    if (!el) { return false; }
    var valueText = txt(value);
    try {
      var proto = el instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype :
        (el instanceof HTMLSelectElement ? HTMLSelectElement.prototype : HTMLInputElement.prototype);
      var desc = Object.getOwnPropertyDescriptor(proto, 'value');
      if (desc && desc.set) { desc.set.call(el, valueText); } else { el.value = valueText; }
    } catch (e) { try { el.value = valueText; } catch (e2) {} }
    try { el.setAttribute('value', valueText); } catch (e) {}
    fire(el); mark(el);
    return true;
  }
  function realOptions(select) {
    return qsa('option', select).filter(function (opt) {
      var value = txt(opt.value);
      var label = norm((opt.textContent || '') + ' ' + value);
      return value !== '' && label && !/παρακαλουμε|please select|select|επιλεξτε|choose/.test(label);
    });
  }
  function setSelectExact(name, value, label) {
    var sel = qs('select[name="' + name + '"]');
    if (!sel) { lastResults.push('MISSING select ' + name); return false; }
    var wanted = txt(value);
    var opts = qsa('option', sel);
    var opt = wanted ? opts.find(function (o) { return txt(o.value) === wanted; }) : null;
    if (!opt && label) {
      var n = norm(label);
      opt = realOptions(sel).find(function (o) {
        var t = norm(o.textContent || o.label || '');
        return t.indexOf(n) !== -1 || n.indexOf(t) !== -1;
      }) || null;
    }
    if (!opt) {
      mark(sel, '#b45309');
      lastResults.push('NOT SELECTED ' + name + '=' + wanted);
      return false;
    }
    opts.forEach(function (o) { o.selected = false; try { o.removeAttribute('selected'); } catch(e) {} });
    opt.selected = true;
    try { opt.setAttribute('selected', 'selected'); } catch (e) {}
    nativeSet(sel, opt.value);
    sel.value = opt.value;
    fire(sel); mark(sel);
    lastResults.push('OK select ' + name + ': ' + txt(opt.textContent) + ' [' + txt(opt.value) + ']');
    return true;
  }
  function setAll(selector, value, label) {
    var els = qsa(selector);
    if (!els.length) { lastResults.push('MISSING ' + label); return false; }
    els.forEach(function (el) { nativeSet(el, value); });
    lastResults.push('OK ' + label + ' (' + els.length + ')');
    return true;
  }
  function setOne(selector, value, label) {
    var el = qs(selector);
    if (!el) { lastResults.push('MISSING ' + label); return false; }
    nativeSet(el, value);
    lastResults.push('OK ' + label);
    return true;
  }
  function formatDateTime(raw, fallbackDateIso, fallbackTime) {
    raw = txt(raw); fallbackDateIso = txt(fallbackDateIso); fallbackTime = txt(fallbackTime);
    var m = raw.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{1,2}):(\d{2})(?::\d{2})?/);
    if (m) { return m[3] + '/' + m[2] + '/' + m[1] + ' ' + two(m[4]) + ':' + m[5]; }
    m = raw.match(/^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{1,2}):(\d{2})/);
    if (m) { return m[1] + '/' + m[2] + '/' + m[3] + ' ' + two(m[4]) + ':' + m[5]; }
    m = fallbackDateIso.match(/^(\d{4})-(\d{2})-(\d{2})$/); var tm = fallbackTime.match(/^(\d{1,2}):(\d{2})/);
    if (m && tm) { return m[3] + '/' + m[2] + '/' + m[1] + ' ' + two(tm[1]) + ':' + tm[2]; }
    return raw || fallbackTime || fallbackDateIso;
  }
  function getPrice(payload) {
    var v = txt(payload.priceAmount || payload.priceText || payload.price || '');
    var nums = v.match(/\d+(?:[.,]\d+)?/g) || [];
    return nums.length ? nums[nums.length - 1].replace(',', '.') : '';
  }
  function getTimes(payload) {
    var start = formatDateTime(payload.pickupDateTime || payload.estimatedPickupDateTime || payload.startedAt || '', payload.pickupDateIso || '', payload.pickupTime || '');
    var end = formatDateTime(payload.endDateTime || payload.estimatedEndDateTime || payload.endedAt || '', payload.pickupDateIso || '', '');
    return { drafted: start, started: start, ended: end, price: getPrice(payload) };
  }
  async function loadPayload() {
    var data = await browser.storage.local.get(['govCabnetV3LatestPayload']);
    return data.govCabnetV3LatestPayload || null;
  }
  async function reportFillResult(payload, eventType, eventStatus, message) {
    if (!payload || !payload.queueId || !payload.dedupeKey) { return false; }
    try {
      var body = {
        queueId: String(payload.queueId || ''),
        dedupeKey: String(payload.dedupeKey || ''),
        eventType: eventType || 'helper_diagnostic_reported',
        eventStatus: eventStatus || 'ok',
        message: message || '',
        helperVersion: VERSION,
        pageUrl: location.href,
        locationHost: location.hostname,
        savedAt: payload.savedAt || '',
        results: lastResults.slice(0, 80)
      };
      var res = await fetch(CALLBACK_URL, {
        method: 'POST',
        mode: 'cors',
        credentials: 'omit',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      var json = null;
      try { json = await res.json(); } catch (e) {}
      if (!res.ok || !json || !json.ok) {
        lastResults.push('WARN callback failed: ' + (json && json.error ? json.error : ('HTTP ' + res.status)));
        return false;
      }
      lastResults.push('OK V3 callback event recorded: ' + eventType + ' #' + (json.event_id || ''));
      return true;
    } catch (e) {
      lastResults.push('WARN callback error: ' + (e && e.message ? e.message : String(e)));
      return false;
    }
  }
  function output(text, type) {
    var el = qs('#gov-cabnet-edxeix-helper-v3-output');
    if (!el) { return; }
    el.textContent = text || '';
    el.style.color = type === 'bad' ? '#991b1b' : (type === 'warn' ? '#92400e' : '#065f46');
  }
  function shouldRedirect(payload) {
    if (!payload || !payload.lessorId || !/\/dashboard\/lease-agreement\/create/.test(location.pathname)) { return false; }
    return new URLSearchParams(location.search || '').get('lessor') !== txt(payload.lessorId);
  }
  async function openCorrectCompany() {
    var p = await loadPayload();
    if (!p || !p.lessorId) { output('No V3 company/lessor ID saved.', 'bad'); return; }
    await reportFillResult(p, 'helper_redirect_company', 'redirect', 'V3 helper opened the correct EDXEIX company form.');
    location.href = location.origin + '/dashboard/lease-agreement/create?lessor=' + encodeURIComponent(txt(p.lessorId));
  }
  async function fillPayload(payload) {
    lastResults = [];
    if (!payload) { output('No V3 saved transfer. Go to gov.cabnet.app /ops/pre-ride-email-toolv3.php or V3 queue and save a payload.', 'bad'); return false; }
    lastResults.push('V3 helper ' + VERSION + ' fill-only');
    lastResults.push('No POST button exists in V3 helper. Operator must review and save manually inside EDXEIX.');
    lastResults.push('Payload queue=' + txt(payload.queueId) + ' dedupe=' + txt(payload.dedupeKey));
    lastResults.push('Payload company=' + txt(payload.lessorId) + ' driver=' + txt(payload.driverId) + ' vehicle=' + txt(payload.vehicleId));
    await reportFillResult(payload, 'helper_fill_started', 'started', 'V3 helper started fill-only operation.');

    if (shouldRedirect(payload)) { await openCorrectCompany(); return false; }

    try {
      var passenger = txt(payload.passengerName || payload.customerName || payload.customer || payload.lesseeName || '');
      var pickup = txt(payload.pickupAddress || payload.pickup || payload.boardingPoint || '');
      var dropoff = txt(payload.dropoffAddress || payload.dropoff || payload.destinationAddress || payload.disembarkPoint || '');
      var times = getTimes(payload);

      var lessorSelect = qs('select[name="lessor"]');
      if (lessorSelect && txt(lessorSelect.value) === txt(payload.lessorId)) {
        mark(lessorSelect);
        lastResults.push('OK lessor already correct: ' + txt(payload.lessorId));
      } else {
        setSelectExact('lessor', payload.lessorId, payload.lessor || payload.operator || '');
        lastResults.push('WARN lessor was changed by V3 helper; waiting for EDXEIX reset.');
        await delay(1400);
      }

      function fillSelectsOnly() {
        setSelectExact('driver', payload.driverId, payload.driver || payload.driverName || '');
        setSelectExact('vehicle', payload.vehicleId, payload.vehicle || payload.vehiclePlate || '');
        if (payload.startingPointId) { setSelectExact('starting_point_id', payload.startingPointId, payload.startingPointLabel || ''); }
        else { lastResults.push('MISSING starting point: choose manually.'); mark(qs('select[name="starting_point_id"]'), '#b45309'); }
        var natural = qs('input[name="lessee[type]"][value="natural"]') || qs('#lessee_natural');
        if (natural) { natural.checked = true; fire(natural); mark(natural); }
        else { lastResults.push('MISSING lessee natural radio'); }
      }
      function fillTextOnly(roundLabel) {
        setAll('input[name="lessee[name]"]', passenger, 'Passenger name ' + roundLabel);
        setOne('textarea[name="boarding_point"]', pickup, 'Pickup / boarding point ' + roundLabel);
        setOne('textarea[name="disembark_point"]', dropoff, 'Drop-off / disembark point ' + roundLabel);
        if (times.drafted) { setOne('input[name="drafted_at"]', times.drafted, 'Drafted at ' + roundLabel); }
        if (times.started) { setOne('input[name="started_at"]', times.started, 'Started at ' + roundLabel); }
        if (times.ended) { setOne('input[name="ended_at"]', times.ended, 'Ended at ' + roundLabel); }
        if (times.price) { setOne('input[name="price"]', times.price, 'Price ' + roundLabel); }
      }

      fillSelectsOnly();
      await delay(1200);
      for (var i = 1; i <= 8; i++) {
        fillTextOnly('V3 stabilize ' + i);
        if (i === 4) { fillSelectsOnly(); }
        output('V3 fill-only stabilizing... ' + i + '/8\n\n' + lastResults.slice(-18).join('\n'), 'warn');
        await delay(650);
      }
      await reportFillResult(payload, 'helper_fill_completed', 'ok', 'V3 helper completed fill-only operation. Operator must still verify and save manually.');
      output('V3 fill-only finished. Callback recorded when queue ID exists. Verify every visible field. Save manually in EDXEIX only after review.\n\n' + lastResults.join('\n'), 'ok');
      return true;
    } catch (e) {
      lastResults.push('ERROR fill failed: ' + (e && e.message ? e.message : String(e)));
      await reportFillResult(payload, 'helper_fill_failed', 'failed', 'V3 helper fill-only operation failed.');
      output('V3 fill-only failed.\n\n' + lastResults.join('\n'), 'bad');
      return false;
    }
  }
  async function copyDiagnostic() {
    var p = await loadPayload();
    var lines = [];
    lines.push('Gov Cabnet EDXEIX Helper V3 diagnostic ' + VERSION);
    lines.push('URL: ' + location.href);
    lines.push('Saved transfer: ' + ((p && p.savedAt) || 'none'));
    lines.push('Queue ID: ' + ((p && p.queueId) || ''));
    lines.push('Dedupe: ' + ((p && p.dedupeKey) || ''));
    lines.push('Passenger: ' + ((p && (p.passengerName || p.customerName || p.customer)) || ''));
    lines.push('Company ID: ' + ((p && p.lessorId) || ''));
    lines.push('Driver ID: ' + ((p && p.driverId) || ''));
    lines.push('Vehicle ID: ' + ((p && p.vehicleId) || ''));
    lines.push('Starting point ID: ' + ((p && p.startingPointId) || ''));
    lines.push('Last results:');
    lastResults.forEach(function (r) { lines.push('- ' + r); });
    var text = lines.join('\n');
    await reportFillResult(p, 'helper_diagnostic_reported', 'diagnostic', 'V3 helper diagnostic copied/reported.');
    try { await navigator.clipboard.writeText(text); output('V3 diagnostic copied. Callback recorded when queue ID exists.', 'ok'); } catch (e) { console.log(text); output(text, 'warn'); }
  }
  async function buildPanel() {
    if (qs('#' + PANEL_ID)) { return; }
    var p = await loadPayload();
    var div = document.createElement('div');
    div.id = PANEL_ID;
    div.style.cssText = 'position:fixed;left:18px;bottom:18px;z-index:2147483647;width:360px;background:#fff;border:2px solid #6d28d9;border-radius:12px;box-shadow:0 14px 35px rgba(8,18,37,.25);padding:12px;font-family:Arial,Helvetica,sans-serif;color:#07152f;';
    var saved = p && p.savedAt ? new Date(p.savedAt).toLocaleString() : 'No V3 saved transfer yet';
    div.innerHTML = '<div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;margin-bottom:8px;"><strong>Gov Cabnet EDXEIX Helper V3 ' + VERSION + '</strong><button type="button" id="gov-cabnet-edxeix-helper-v3-close" style="border:0;background:#e5e7eb;border-radius:6px;padding:2px 7px;cursor:pointer;">×</button></div>' +
      '<div style="font-size:12px;color:#41577a;margin-bottom:4px;">Saved: ' + saved + '</div>' +
      '<div style="font-size:12px;color:#41577a;margin-bottom:4px;">Queue ID: ' + ((p && p.queueId) || 'none') + ' · Dedupe: ' + ((p && p.dedupeKey) || 'none') + '</div>' +
      '<div style="font-size:12px;color:#41577a;margin-bottom:8px;">' + (p ? ('Company ID: ' + (p.lessorId||'missing') + ' · Driver ID: ' + (p.driverId||'missing') + ' · Vehicle ID: ' + (p.vehicleId||'missing')) : 'No saved V3 IDs') + '</div>' +
      '<button type="button" id="gov-cabnet-edxeix-helper-v3-company" style="width:100%;border:1px solid #6d28d9;border-radius:9px;background:#ede9fe;color:#5b21b6;font-weight:700;padding:9px;cursor:pointer;font-size:13px;margin-bottom:8px;">Open correct company form</button>' +
      '<button type="button" id="gov-cabnet-edxeix-helper-v3-fill" style="width:100%;border:0;border-radius:9px;background:#6d28d9;color:#fff;font-weight:700;padding:11px;cursor:pointer;font-size:14px;">Fill using V3 exact IDs</button>' +
      '<button type="button" id="gov-cabnet-edxeix-helper-v3-diagnostic" style="width:100%;border:1px solid #cbd5e1;border-radius:9px;background:#f8fafc;color:#0f172a;font-weight:700;padding:9px;cursor:pointer;font-size:13px;margin-top:8px;">Copy/report V3 diagnostic</button>' +
      '<div id="gov-cabnet-edxeix-helper-v3-output" style="white-space:pre-wrap;font-size:12px;line-height:1.35;margin-top:9px;color:#41577a;max-height:230px;overflow:auto;">V3 is fill-only. No POST/save action is available here. Fill events are reported to the V3 queue when queue ID exists.</div>';
    document.documentElement.appendChild(div);
    qs('#gov-cabnet-edxeix-helper-v3-close').addEventListener('click', function () { div.remove(); });
    qs('#gov-cabnet-edxeix-helper-v3-company').addEventListener('click', openCorrectCompany);
    qs('#gov-cabnet-edxeix-helper-v3-fill').addEventListener('click', async function () { var payload = await loadPayload(); await fillPayload(payload); });
    qs('#gov-cabnet-edxeix-helper-v3-diagnostic').addEventListener('click', copyDiagnostic);
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', buildPanel); } else { buildPanel(); }
})();
