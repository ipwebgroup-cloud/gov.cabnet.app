(function () {
  'use strict';

  if (typeof browser === 'undefined' || !browser.storage || !browser.storage.local) { return; }
  if (!/edxeix\.yme\.gov\.gr$/.test(location.hostname)) { return; }

  var VERSION = 'v6.6.15-stabilized-fill';
  var PANEL_ID = 'gov-cabnet-edxeix-helper-panel';
  var lastPayload = null;
  var lastResults = [];

  function qs(sel, root) { try { return (root || document).querySelector(sel); } catch (e) { return null; } }
  function qsa(sel, root) { try { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); } catch (e) { return []; } }
  function txt(v) { return String(v == null ? '' : v).trim(); }
  function two(v) { v = String(v || ''); return v.length === 1 ? '0' + v : v; }
  function nowText() { try { return new Date().toLocaleString(); } catch (e) { return String(new Date()); } }
  function norm(v) {
    return txt(v).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/[ίϊΐ]/g, 'ι').replace(/[ή]/g, 'η').replace(/[ύϋΰ]/g, 'υ')
      .replace(/[ό]/g, 'ο').replace(/[ά]/g, 'α').replace(/[έ]/g, 'ε')
      .replace(/[ώ]/g, 'ω').replace(/ς/g, 'σ').replace(/[^a-z0-9α-ω]+/g, ' ')
      .replace(/\s+/g, ' ').trim();
  }
  function delay(ms) { return new Promise(function (resolve) { setTimeout(resolve, ms); }); }
  function mark(el, color) { if (el && el.style) { el.style.outline = '3px solid ' + (color || '#059669'); el.style.outlineOffset = '2px'; } }
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
      lastResults.push('NOT SELECTED ' + name + '=' + wanted + ' options: ' + realOptions(sel).map(function (o) { return txt(o.textContent) + '[' + txt(o.value) + ']'; }).join(' | '));
      return false;
    }

    opts.forEach(function (o) { o.selected = false; try { o.removeAttribute('selected'); } catch(e) {} });
    opt.selected = true;
    try { opt.setAttribute('selected', 'selected'); } catch (e) {}
    try { sel.selectedIndex = opts.indexOf(opt); } catch (e) {}
    nativeSet(sel, opt.value);
    sel.value = opt.value;
    fire(sel); mark(sel);
    lastResults.push('OK select ' + name + ': ' + txt(opt.textContent) + ' [' + txt(opt.value) + ']');
    return true;
  }
  function setAll(selector, value, label) {
    var els = qsa(selector);
    if (!els.length) { lastResults.push('MISSING ' + label + ' selector=' + selector); return false; }
    els.forEach(function (el) { nativeSet(el, value); });
    lastResults.push('OK ' + label + ' (' + els.length + ')');
    return true;
  }
  function setOne(selector, value, label) {
    var el = qs(selector);
    if (!el) { lastResults.push('MISSING ' + label + ' selector=' + selector); return false; }
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
  function parseGreekDT(v) {
    var m = txt(v).match(/^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{1,2}):(\d{2})/);
    if (!m) { return null; }
    return new Date(Number(m[3]), Number(m[2]) - 1, Number(m[1]), Number(m[4]), Number(m[5]), 0);
  }
  function chooseStartingPointId(payload) {
    var select = qs('select[name="starting_point_id"]');
    if (!select) { return ''; }
    var saved = txt(payload.startingPointId);
    if (saved && qsa('option', select).some(function (o) { return txt(o.value) === saved; })) { return saved; }
    var pickup = norm(payload.pickupAddress || payload.pickup || '');
    var opts = realOptions(select);
    if (/chora|χωρα|ntavias|νταβιασ|port|λιμανι/.test(pickup)) {
      var chora = opts.find(function (o) { return /χωρα|port|λιμανι/.test(norm(o.textContent)); });
      if (chora) { return txt(chora.value); }
    }
    if (opts.length === 1) { return txt(opts[0].value); }
    // For fill preview, choose the first available live option and warn; POST still requires operator verification.
    if (opts.length > 1) {
      lastResults.push('WARN starting point ambiguous; selected first live option. Verify manually.');
      return txt(opts[0].value);
    }
    return '';
  }
  function coordsValue() { var el = qs('input[name="coordinates"], input#locationCoordinates'); return txt(el && el.value); }
  function findToken() { return txt((qs('input[name="_token"]') || {}).value || (qs('meta[name="csrf-token"]') || {}).content || ''); }
  async function loadPayload() {
    var data = await browser.storage.local.get(['govCabnetLatestPayload']);
    return data.govCabnetLatestPayload || null;
  }
  async function setAutoAction(action) { await browser.storage.local.set({ govCabnetAutoAction: action || '' }); }
  async function getAutoAction() { var data = await browser.storage.local.get(['govCabnetAutoAction']); return data.govCabnetAutoAction || ''; }
  function output(text, type) {
    var el = qs('#gov-cabnet-edxeix-helper-output');
    if (!el) { return; }
    el.textContent = text || '';
    el.style.color = type === 'bad' ? '#991b1b' : (type === 'warn' ? '#92400e' : '#065f46');
  }
  function shouldRedirect(payload) {
    if (!payload || !payload.lessorId || !/\/dashboard\/lease-agreement\/create/.test(location.pathname)) { return false; }
    return new URLSearchParams(location.search || '').get('lessor') !== txt(payload.lessorId);
  }
  async function openCorrectCompany(action) {
    var p = await loadPayload();
    if (!p || !p.lessorId) { output('No company/lessor ID saved.', 'bad'); return; }
    await setAutoAction(action || 'fill');
    location.href = location.origin + '/dashboard/lease-agreement/create?lessor=' + encodeURIComponent(txt(p.lessorId));
  }
  async function fillPayload(payload) {
    lastPayload = payload;
    lastResults = [];
    if (!payload) { output('No saved Bolt transfer. Go to gov.cabnet.app and click Save + open EDXEIX.', 'bad'); return false; }
    if (shouldRedirect(payload)) { await openCorrectCompany('fill'); return false; }

    var passenger = txt(payload.passengerName || payload.customerName || payload.customer || payload.lesseeName || '');
    var pickup = txt(payload.pickupAddress || payload.pickup || payload.boardingPoint || '');
    var dropoff = txt(payload.dropoffAddress || payload.dropoff || payload.destinationAddress || payload.disembarkPoint || '');
    var times = getTimes(payload);

    lastResults.push('Helper ' + VERSION + ' at ' + nowText());
    lastResults.push('Payload company=' + txt(payload.lessorId) + ' driver=' + txt(payload.driverId) + ' vehicle=' + txt(payload.vehicleId));

    var lessorSelect = qs('select[name="lessor"]');
    if (lessorSelect && txt(lessorSelect.value) === txt(payload.lessorId)) {
      mark(lessorSelect);
      lastResults.push('OK lessor already correct: ' + txt(payload.lessorId));
    } else {
      setSelectExact('lessor', payload.lessorId, payload.lessor || payload.operator || '');
      lastResults.push('WARN lessor was changed by helper; waiting for EDXEIX reset.');
      await delay(1400);
    }

    function fillSelectsOnly() {
      setSelectExact('driver', payload.driverId, payload.driver || payload.driverName || '');
      setSelectExact('vehicle', payload.vehicleId, payload.vehicle || payload.vehiclePlate || '');
      var spId = chooseStartingPointId(payload);
      if (spId) { setSelectExact('starting_point_id', spId, ''); }
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

    // EDXEIX resets text fields shortly after driver/vehicle/starting-point changes.
    // Fill selects first, wait, then repeatedly stabilize text fields while page JS settles.
    fillSelectsOnly();
    await delay(1200);

    for (var i = 1; i <= 12; i++) {
      fillTextOnly('stabilize ' + i);
      if (i === 4 || i === 8) {
        // Keep IDs selected, but avoid changing the lessor again because it can reset the form.
        fillSelectsOnly();
      }
      output('Stabilizing EDXEIX fields... ' + i + '/12\n\n' + lastResults.slice(-18).join('\n'), 'warn');
      await delay(700);
    }

    output('Stabilized fill finished. Verify every visible field. Do not POST old rides.\n\n' + lastResults.join('\n'), 'ok');
    return true;
  }
  async function postPayload() {
    var payload = await loadPayload();
    if (!payload) { output('No saved payload.', 'bad'); return; }
    if (shouldRedirect(payload)) { await openCorrectCompany('post'); return; }
    await fillPayload(payload);
    await delay(300);

    var times = getTimes(payload);
    var start = parseGreekDT(times.started);
    var problems = [];
    if (!start || isNaN(start.getTime())) { problems.push('start date/time is invalid'); }
    else if ((start.getTime() - Date.now()) / 60000 < 20) { problems.push('start date/time is not at least 20 minutes in the future'); }

    var lessor = txt(qs('select[name="lessor"]') && qs('select[name="lessor"]').value || payload.lessorId);
    var driver = txt(qs('select[name="driver"]') && qs('select[name="driver"]').value || payload.driverId);
    var vehicle = txt(qs('select[name="vehicle"]') && qs('select[name="vehicle"]').value || payload.vehicleId);
    var sp = txt(qs('select[name="starting_point_id"]') && qs('select[name="starting_point_id"]').value || '');
    var coords = coordsValue();

    if (!lessor) { problems.push('missing lessor/company'); }
    if (!driver) { problems.push('missing driver'); }
    if (!vehicle) { problems.push('missing vehicle'); }
    if (!sp) { problems.push('missing starting point'); }
    if (!coords || coords === '40,24') { problems.push('map point not selected; click the exact pickup point on the EDXEIX map first'); }
    if (problems.length) { var msg = 'POST blocked. Fix first:\n- ' + problems.join('\n- '); output(msg, 'bad'); alert(msg); return; }

    var passenger = txt(payload.passengerName || payload.customerName || payload.customer || '');
    var pickup = txt(payload.pickupAddress || payload.pickup || '');
    var dropoff = txt(payload.dropoffAddress || payload.dropoff || '');
    if (!confirm('POST/SAVE EDXEIX rental contract?\n\nCompany: ' + lessor + '\nDriver: ' + driver + '\nVehicle: ' + vehicle + '\nStarting point: ' + sp + '\nCoordinates: ' + coords + '\n\nPassenger: ' + passenger + '\nPickup: ' + pickup + '\nDrop-off: ' + dropoff + '\nStart: ' + times.started + '\nEnd: ' + times.ended + '\nPrice: ' + times.price + '\n\nPress OK only after visual verification.')) { output('POST cancelled.', 'warn'); return; }

    var token = findToken();
    if (!token) { alert('CSRF token not found. Refresh EDXEIX.'); return; }
    var fd = new FormData();
    fd.append('_token', token);
    fd.append('lessor', lessor);
    fd.append('lessee[type]', 'natural');
    fd.append('lessee[name]', passenger);
    fd.append('driver', driver);
    fd.append('vehicle', vehicle);
    fd.append('starting_point_id', sp);
    fd.append('boarding_point', pickup);
    fd.append('coordinates', coords);
    fd.append('disembark_point', dropoff);
    fd.append('drafted_at', times.drafted || times.started || '');
    fd.append('started_at', times.started || '');
    fd.append('ended_at', times.ended || '');
    fd.append('price', times.price || '');
    fd.append('broker', txt(qs('input[name="broker"]') && qs('input[name="broker"]').value || ''));

    output('Submitting to EDXEIX...', 'warn');
    fetch(location.origin + '/dashboard/lease-agreement', { method: 'POST', credentials: 'include', body: fd, headers: { 'X-CSRF-TOKEN': token, 'Accept': 'text/html,application/xhtml+xml,application/xml,application/json' }, redirect: 'follow' })
      .then(function (response) { return response.text().then(function (html) { document.open(); document.write(html); document.close(); }); })
      .catch(function (error) { console.error(error); alert('POST failed: ' + error.message); output('POST failed: ' + error.message, 'bad'); });
  }
  function diagnostic(payload) {
    var lines = [];
    lines.push('Gov Cabnet EDXEIX Helper diagnostic ' + VERSION);
    lines.push('URL: ' + location.href);
    lines.push('Saved transfer: ' + ((payload && payload.savedAt) || 'none'));
    lines.push('Source passenger: ' + ((payload && (payload.passengerName || payload.customerName || payload.customer)) || ''));
    lines.push('Source lessor: ' + ((payload && payload.lessor) || '') + ' ID=' + ((payload && payload.lessorId) || ''));
    lines.push('Source driver: ' + ((payload && (payload.driver || payload.driverName)) || '') + ' ID=' + ((payload && payload.driverId) || ''));
    lines.push('Source vehicle: ' + ((payload && (payload.vehicle || payload.vehiclePlate)) || '') + ' ID=' + ((payload && payload.vehicleId) || ''));
    lines.push('Starting point ID: ' + ((payload && payload.startingPointId) || ''));
    lines.push('Coordinates: ' + coordsValue());
    lines.push(''); lines.push('Selects found:');
    qsa('select').forEach(function (s, i) { lines.push('[' + i + '] name=' + (s.name||'') + ' id=' + (s.id||'') + ' value=' + (s.value||'') + ' options=' + realOptions(s).length); realOptions(s).forEach(function (o) { lines.push('  - ' + txt(o.textContent) + ' [' + txt(o.value) + ']'); }); });
    lines.push(''); lines.push('Inputs/textareas found:');
    qsa('input, textarea').slice(0,100).forEach(function (el, i) { lines.push('[' + i + '] tag=' + el.tagName + ' type=' + (el.type||'') + ' name=' + (el.name||'') + ' id=' + (el.id||'') + ' value=' + (el.value||'')); });
    lines.push(''); lines.push('Last results:');
    lastResults.forEach(function (r) { lines.push('- ' + r); });
    return lines.join('\n');
  }
  async function copyDiagnostic() {
    var payload = await loadPayload(); var text = diagnostic(payload);
    try { await navigator.clipboard.writeText(text); output('Diagnostic copied.', 'ok'); } catch (e) { console.log(text); output(text, 'warn'); }
  }
  async function buildPanel() {
    if (qs('#' + PANEL_ID)) { return; }
    var p = await loadPayload();
    var div = document.createElement('div');
    div.id = PANEL_ID;
    div.style.cssText = 'position:fixed;right:18px;bottom:18px;z-index:2147483647;width:360px;background:#fff;border:2px solid #059669;border-radius:12px;box-shadow:0 14px 35px rgba(8,18,37,.25);padding:12px;font-family:Arial,Helvetica,sans-serif;color:#07152f;';
    var saved = p && p.savedAt ? new Date(p.savedAt).toLocaleString() : 'No saved transfer yet';
    div.innerHTML = '<div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;margin-bottom:8px;"><strong>Gov Cabnet EDXEIX Helper ' + VERSION + '</strong><button type="button" id="gov-cabnet-edxeix-helper-close" style="border:0;background:#e5e7eb;border-radius:6px;padding:2px 7px;cursor:pointer;">×</button></div>' +
      '<div style="font-size:12px;color:#41577a;margin-bottom:4px;">Saved: ' + saved + '</div>' +
      '<div style="font-size:12px;color:#41577a;margin-bottom:8px;">' + (p ? ('Company ID: ' + (p.lessorId||'missing') + ' · Driver ID: ' + (p.driverId||'missing') + ' · Vehicle ID: ' + (p.vehicleId||'missing')) : 'No saved IDs') + '</div>' +
      '<button type="button" id="gov-cabnet-edxeix-helper-company" style="width:100%;border:1px solid #059669;border-radius:9px;background:#ecfdf3;color:#065f46;font-weight:700;padding:9px;cursor:pointer;font-size:13px;margin-bottom:8px;">Open correct company form</button>' +
      '<button type="button" id="gov-cabnet-edxeix-helper-fill" style="width:100%;border:0;border-radius:9px;background:#059669;color:#fff;font-weight:700;padding:11px;cursor:pointer;font-size:14px;">Fill using exact IDs</button>' +
      '<button type="button" id="gov-cabnet-edxeix-helper-post" style="width:100%;border:0;border-radius:9px;background:#b45309;color:#fff;font-weight:700;padding:11px;cursor:pointer;font-size:14px;margin-top:8px;">POST / Save reviewed form</button>' +
      '<button type="button" id="gov-cabnet-edxeix-helper-diagnostic" style="width:100%;border:1px solid #cbd5e1;border-radius:9px;background:#f8fafc;color:#0f172a;font-weight:700;padding:9px;cursor:pointer;font-size:13px;margin-top:8px;">Copy diagnostic</button>' +
      '<div id="gov-cabnet-edxeix-helper-output" style="white-space:pre-wrap;font-size:12px;line-height:1.35;margin-top:9px;color:#41577a;max-height:230px;overflow:auto;">Fill first. Do not POST old rides. POST is blocked unless trip is future and map point exists.</div>';
    document.documentElement.appendChild(div);
    qs('#gov-cabnet-edxeix-helper-close').addEventListener('click', function () { div.remove(); });
    qs('#gov-cabnet-edxeix-helper-company').addEventListener('click', function () { openCorrectCompany('fill'); });
    qs('#gov-cabnet-edxeix-helper-fill').addEventListener('click', async function () { var payload = await loadPayload(); await fillPayload(payload); });
    qs('#gov-cabnet-edxeix-helper-post').addEventListener('click', postPayload);
    qs('#gov-cabnet-edxeix-helper-diagnostic').addEventListener('click', copyDiagnostic);
    var act = await getAutoAction();
    if (act === 'fill' || act === 'post') {
      await setAutoAction('');
      await delay(900);
      if (act === 'post') { postPayload(); } else { var p2 = await loadPayload(); fillPayload(p2); }
    }
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', buildPanel); } else { buildPanel(); }
})();
