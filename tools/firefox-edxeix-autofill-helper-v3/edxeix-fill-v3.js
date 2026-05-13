(function () {
  'use strict';

  if (typeof browser === 'undefined' || !browser.storage || !browser.storage.local) { return; }
  if (!/edxeix\.yme\.gov\.gr$/.test(location.hostname)) { return; }

  var VERSION = 'v3.0.17-starting-point-retry';
  var PANEL_ID = 'gov-cabnet-edxeix-helper-v3-panel';
  var CALLBACK_URL = 'https://gov.cabnet.app/ops/pre-ride-email-v3-helper-callback.php';
  var lastResults = [];

  function qs(sel, root) { try { return (root || document).querySelector(sel); } catch (e) { return null; } }
  function qsa(sel, root) { try { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); } catch (e) { return []; } }
  function txt(v) { return String(v == null ? '' : v).trim(); }
  function two(v) { v = String(v || ''); return v.length === 1 ? '0' + v : v; }
  function delay(ms) { return new Promise(function (resolve) { setTimeout(resolve, ms); }); }

  function norm(v) {
    return txt(v).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/[ίϊΐ]/g, 'ι').replace(/[ή]/g, 'η').replace(/[ύϋΰ]/g, 'υ')
      .replace(/[ό]/g, 'ο').replace(/[ά]/g, 'α').replace(/[έ]/g, 'ε')
      .replace(/[ώ]/g, 'ω').replace(/ς/g, 'σ').replace(/[^a-z0-9α-ω]+/g, ' ')
      .replace(/\s+/g, ' ').trim();
  }

  function mark(el, color) {
    if (el && el.style) {
      el.style.outline = '3px solid ' + (color || '#6d28d9');
      el.style.outlineOffset = '2px';
    }
  }

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
    fire(el);
    mark(el);
    return true;
  }

  function output(text, type) {
    var el = qs('#gov-cabnet-edxeix-helper-v3-output');
    if (!el) { return; }
    el.textContent = text || '';
    el.style.color = type === 'bad' ? '#991b1b' : (type === 'warn' ? '#92400e' : '#065f46');
  }

  async function loadPayload() {
    var data = await browser.storage.local.get(['govCabnetV3LatestPayload']);
    return data.govCabnetV3LatestPayload || null;
  }

  async function reportEvent(payload, eventType, status, message, context) {
    payload = payload || {};
    var queueId = txt(payload.queueId || payload.queue_id || '');
    var dedupeKey = txt(payload.dedupeKey || payload.dedupe_key || '');
    if (!queueId || !dedupeKey) { return; }

    try {
      await fetch(CALLBACK_URL, {
        method: 'POST',
        credentials: 'omit',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          queue_id: queueId,
          queueId: queueId,
          dedupe_key: dedupeKey,
          dedupeKey: dedupeKey,
          event_type: eventType,
          eventType: eventType,
          event_status: status || 'info',
          status: status || 'info',
          event_message: message || '',
          message: message || '',
          context: context || {},
          helper_version: VERSION,
          page_url: location.href,
          user_agent: navigator.userAgent
        })
      });
    } catch (e) {
      // Callback must never block the operator.
      console.warn('V3 callback failed', e);
    }
  }

  function realOptions(select) {
    return qsa('option', select).filter(function (opt) {
      var value = txt(opt.value);
      var label = norm((opt.textContent || '') + ' ' + value);
      return value !== '' && label && !/παρακαλουμε|please select|select|επιλεξτε|choose/.test(label);
    });
  }

  function findSelectByNames(names) {
    var selects = qsa('select');
    for (var i = 0; i < selects.length; i++) {
      var el = selects[i];
      var n = txt(el.getAttribute('name'));
      var id = txt(el.getAttribute('id'));
      for (var j = 0; j < names.length; j++) {
        if (n === names[j] || id === names[j]) { return el; }
      }
    }
    return null;
  }

  function visibleText(el) {
    return norm(el ? (el.innerText || el.textContent || el.value || '') : '');
  }

  function controlFromLabel(label, selector) {
    var htmlFor = label && label.getAttribute && label.getAttribute('for');
    if (htmlFor) {
      var byFor = document.getElementById(htmlFor);
      if (byFor && byFor.matches && byFor.matches(selector)) { return byFor; }
    }
    var inside = label && label.querySelector && label.querySelector(selector);
    if (inside) { return inside; }

    var node = label;
    for (var depth = 0; depth < 7 && node; depth += 1, node = node.parentElement) {
      var found = node.querySelector && node.querySelector(selector);
      if (found) { return found; }
      var next = node.nextElementSibling;
      if (next) {
        if (next.matches && next.matches(selector)) { return next; }
        var inNext = next.querySelector && next.querySelector(selector);
        if (inNext) { return inNext; }
      }
    }
    return null;
  }

  function findSelectNearLabel(labelRegex) {
    var labels = qsa('label, .form-label, .control-label, strong, b, span, div, p, td, th');
    for (var i = 0; i < labels.length; i++) {
      if (!labelRegex.test(visibleText(labels[i]))) { continue; }
      var control = controlFromLabel(labels[i], 'select');
      if (control) { return control; }
    }
    return null;
  }

  function optionScore(opt, wanted, labels) {
    var value = txt(opt.value);
    var labelText = txt(opt.textContent || opt.label || '');
    var valueNorm = norm(value);
    var textNorm = norm(labelText + ' ' + value);
    var wantedNorm = norm(wanted);
    var wantedDigits = txt(wanted).replace(/\D+/g, '');
    var valueDigits = value.replace(/\D+/g, '');
    var score = 0;

    if (wantedNorm && valueNorm === wantedNorm) { score = Math.max(score, 1000); }
    if (wantedDigits && valueDigits && valueDigits === wantedDigits) { score = Math.max(score, 950); }
    if (wantedDigits && valueDigits && (valueDigits.indexOf(wantedDigits) !== -1 || wantedDigits.indexOf(valueDigits) !== -1)) { score = Math.max(score, 850); }
    if (wantedNorm && textNorm === wantedNorm) { score = Math.max(score, 800); }
    if (wantedNorm && (textNorm.indexOf(wantedNorm) !== -1 || wantedNorm.indexOf(textNorm) !== -1)) { score = Math.max(score, 650); }

    (labels || []).forEach(function (label) {
      var n = norm(label);
      if (!n) { return; }
      if (textNorm === n) { score = Math.max(score, 760); }
      if (textNorm.indexOf(n) !== -1 || n.indexOf(textNorm) !== -1) { score = Math.max(score, 560); }
      n.split(' ').forEach(function (part) {
        if (part.length >= 4 && textNorm.indexOf(part) !== -1) { score = Math.max(score, 120); }
      });
    });

    return score;
  }

  function selectOption(select, option, label) {
    if (!select || !option) { return false; }
    qsa('option', select).forEach(function (o) {
      o.selected = false;
      try { o.removeAttribute('selected'); } catch (e) {}
    });
    option.selected = true;
    try { option.setAttribute('selected', 'selected'); } catch (e) {}
    nativeSet(select, option.value);
    select.value = option.value;
    fire(select);
    mark(select);
    lastResults.push('OK select ' + label + ': ' + txt(option.textContent || option.value) + ' [' + txt(option.value) + ']');
    return true;
  }

  function setSelectRobust(names, wanted, labels, description, labelRegex, allowSingleOptionFallback) {
    var select = findSelectByNames(names) || (labelRegex ? findSelectNearLabel(labelRegex) : null);
    if (!select) {
      lastResults.push('MISSING select ' + description + ' names=' + names.join('|'));
      return false;
    }

    var options = realOptions(select);
    if (!options.length) {
      mark(select, '#b45309');
      lastResults.push('NO REAL OPTIONS for ' + description + ' yet; current options=' + qsa('option', select).length);
      return false;
    }

    var best = null;
    var bestScore = 0;
    options.forEach(function (opt) {
      var s = optionScore(opt, wanted, labels || []);
      if (s > bestScore) { best = opt; bestScore = s; }
    });

    if (!best && allowSingleOptionFallback && options.length === 1) {
      best = options[0];
      bestScore = 50;
      lastResults.push('WARN ' + description + ': using single available option fallback.');
    }

    if (!best || bestScore < 50) {
      mark(select, '#b45309');
      var sample = options.slice(0, 6).map(function (o) { return txt(o.textContent || o.value) + '[' + txt(o.value) + ']'; }).join(' | ');
      lastResults.push('NOT SELECTED ' + description + ' wanted=' + txt(wanted) + ' labels=' + (labels || []).map(txt).filter(Boolean).join(' / ') + ' options=' + sample);
      return false;
    }

    return selectOption(select, best, description);
  }

  async function waitAndSetSelectRobust(names, wanted, labels, description, labelRegex, allowSingleOptionFallback, attempts, waitMs) {
    attempts = attempts || 12;
    waitMs = waitMs || 700;
    for (var i = 1; i <= attempts; i++) {
      if (setSelectRobust(names, wanted, labels, description + ' attempt ' + i, labelRegex, allowSingleOptionFallback)) {
        return true;
      }
      output('V3 waiting for ' + description + ' options... ' + i + '/' + attempts + '\n\n' + lastResults.slice(-14).join('\n'), 'warn');
      await delay(waitMs);
    }
    return false;
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
    m = fallbackDateIso.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    var tm = fallbackTime.match(/^(\d{1,2}):(\d{2})/);
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

  function shouldRedirect(payload) {
    if (!payload || !payload.lessorId || !/\/dashboard\/lease-agreement\/create/.test(location.pathname)) { return false; }
    return new URLSearchParams(location.search || '').get('lessor') !== txt(payload.lessorId);
  }

  async function openCorrectCompany() {
    var p = await loadPayload();
    if (!p || !p.lessorId) { output('No V3 company/lessor ID saved.', 'bad'); return; }
    await reportEvent(p, 'helper_redirect_company', 'info', 'Opening EDXEIX company form for lessor ' + txt(p.lessorId), { lessorId: txt(p.lessorId) });
    location.href = location.origin + '/dashboard/lease-agreement/create?lessor=' + encodeURIComponent(txt(p.lessorId));
  }

  async function fillPayload(payload) {
    lastResults = [];
    if (!payload) {
      output('No V3 saved transfer. Select a V3 queue row and click Save + open EDXEIX company form.', 'bad');
      return false;
    }

    await reportEvent(payload, 'helper_fill_started', 'started', 'V3 helper fill started.', { helperVersion: VERSION, url: location.href });

    if (shouldRedirect(payload)) {
      await openCorrectCompany();
      return false;
    }

    try {
      var passenger = txt(payload.passengerName || payload.customerName || payload.customer || payload.lesseeName || '');
      var pickup = txt(payload.pickupAddress || payload.pickup || payload.boardingPoint || '');
      var dropoff = txt(payload.dropoffAddress || payload.dropoff || payload.destinationAddress || payload.disembarkPoint || '');
      var times = getTimes(payload);
      var startPointLabels = [
        payload.startingPointLabel,
        payload.startingPointName,
        payload.startingPoint,
        payload.startingPointText,
        payload.starting_point_label,
        payload.starting_point_name,
        payload.pickupStartingPointLabel
      ].map(txt).filter(Boolean);

      lastResults.push('V3 helper ' + VERSION + ' fill-only');
      lastResults.push('No POST/save button exists in V3 helper. Operator must review and save manually inside EDXEIX.');
      lastResults.push('Payload queue=' + txt(payload.queueId) + ' dedupe=' + txt(payload.dedupeKey));
      lastResults.push('Payload company=' + txt(payload.lessorId) + ' driver=' + txt(payload.driverId) + ' vehicle=' + txt(payload.vehicleId) + ' start=' + txt(payload.startingPointId));

      await waitAndSetSelectRobust(['lessor'], payload.lessorId, [payload.lessor, payload.operator], 'lessor', /εκμισθωτης|lessor/, false, 4, 700);
      await delay(1600);

      function fillTextOnly(roundLabel) {
        setAll('input[name="lessee[name]"]', passenger, 'Passenger name ' + roundLabel);
        setOne('textarea[name="boarding_point"]', pickup, 'Pickup / boarding point ' + roundLabel);
        setOne('textarea[name="disembark_point"]', dropoff, 'Drop-off / disembark point ' + roundLabel);
        if (times.drafted) { setOne('input[name="drafted_at"]', times.drafted, 'Drafted at ' + roundLabel); }
        if (times.started) { setOne('input[name="started_at"]', times.started, 'Started at ' + roundLabel); }
        if (times.ended) { setOne('input[name="ended_at"]', times.ended, 'Ended at ' + roundLabel); }
        if (times.price) { setOne('input[name="price"]', times.price, 'Price ' + roundLabel); }
      }

      async function fillSelects(roundLabel) {
        await waitAndSetSelectRobust(['driver'], payload.driverId, [payload.driver, payload.driverName], 'driver ' + roundLabel, /οδηγος|driver/, false, 5, 550);
        await waitAndSetSelectRobust(['vehicle'], payload.vehicleId, [payload.vehicle, payload.vehiclePlate], 'vehicle ' + roundLabel, /οχημα|vehicle/, false, 5, 550);
        await waitAndSetSelectRobust(
          ['starting_point_id', 'starting_point', 'start_point_id', 'lease_agreement[starting_point_id]', 'lease[starting_point_id]'],
          payload.startingPointId,
          startPointLabels,
          'starting point ' + roundLabel,
          /σημειο εναρξης|σημειο εκκινησης|starting point|start point/,
          true,
          14,
          650
        );

        var natural = qs('input[name="lessee[type]"][value="natural"]') || qs('#lessee_natural');
        if (natural) { natural.checked = true; fire(natural); mark(natural); lastResults.push('OK lessee natural radio ' + roundLabel); }
        else { lastResults.push('MISSING lessee natural radio ' + roundLabel); }
      }

      await fillSelects('initial');
      await delay(900);
      for (var i = 1; i <= 8; i++) {
        fillTextOnly('V3 stabilize ' + i);
        if (i === 2 || i === 5 || i === 8) { await fillSelects('stabilize ' + i); }
        output('V3 fill-only stabilizing... ' + i + '/8\n\n' + lastResults.slice(-20).join('\n'), 'warn');
        await delay(650);
      }

      var missingStarting = false;
      var startSelect = findSelectByNames(['starting_point_id', 'starting_point', 'start_point_id', 'lease_agreement[starting_point_id]', 'lease[starting_point_id]']) || findSelectNearLabel(/σημειο εναρξης|σημειο εκκινησης|starting point|start point/);
      if (startSelect && !txt(startSelect.value)) { missingStarting = true; }

      var status = missingStarting ? 'warning' : 'ok';
      var message = missingStarting ? 'V3 helper finished but starting point was not selected automatically.' : 'V3 helper fill completed.';
      await reportEvent(payload, 'helper_fill_completed', status, message, {
        helperVersion: VERSION,
        results: lastResults.slice(-80),
        missingStartingPoint: missingStarting,
        startingPointValue: startSelect ? txt(startSelect.value) : '',
        url: location.href
      });

      output((missingStarting ? 'V3 fill finished with warning: starting point still needs manual selection.' : 'V3 fill-only finished.') + '\nVerify every visible field. Save manually in EDXEIX only after review.\n\n' + lastResults.join('\n'), missingStarting ? 'warn' : 'ok');
      return !missingStarting;
    } catch (e) {
      lastResults.push('ERROR ' + (e && e.message ? e.message : String(e)));
      await reportEvent(payload, 'helper_fill_failed', 'failed', 'V3 helper fill failed: ' + (e && e.message ? e.message : String(e)), { results: lastResults.slice(-80), url: location.href });
      output('V3 helper fill failed. Copy diagnostic and paste to Sophion.\n\n' + lastResults.join('\n'), 'bad');
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
    lines.push('Starting point options:');
    var startSelect = findSelectByNames(['starting_point_id', 'starting_point', 'start_point_id', 'lease_agreement[starting_point_id]', 'lease[starting_point_id]']) || findSelectNearLabel(/σημειο εναρξης|σημειο εκκινησης|starting point|start point/);
    if (startSelect) {
      realOptions(startSelect).forEach(function (o) { lines.push('- ' + txt(o.textContent || o.value) + ' [' + txt(o.value) + ']'); });
    } else {
      lines.push('- starting point select not found');
    }
    lines.push('Last results:');
    lastResults.forEach(function (r) { lines.push('- ' + r); });
    var text = lines.join('\n');
    try { await navigator.clipboard.writeText(text); output('V3 diagnostic copied.', 'ok'); } catch (e) { console.log(text); output(text, 'warn'); }
    await reportEvent(p, 'helper_diagnostic_reported', 'ok', 'Operator copied/reported V3 diagnostic.', { diagnostic: text.slice(0, 12000), helperVersion: VERSION });
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
      '<div style="font-size:12px;color:#41577a;margin-bottom:8px;">' + (p ? ('Company ID: ' + (p.lessorId || 'missing') + ' · Driver ID: ' + (p.driverId || 'missing') + ' · Vehicle ID: ' + (p.vehicleId || 'missing') + ' · Start ID: ' + (p.startingPointId || 'missing')) : 'No saved V3 IDs') + '</div>' +
      '<button type="button" id="gov-cabnet-edxeix-helper-v3-company" style="width:100%;border:1px solid #6d28d9;border-radius:9px;background:#ede9fe;color:#5b21b6;font-weight:700;padding:9px;cursor:pointer;font-size:13px;margin-bottom:8px;">Open correct company form</button>' +
      '<button type="button" id="gov-cabnet-edxeix-helper-v3-fill" style="width:100%;border:0;border-radius:9px;background:#6d28d9;color:#fff;font-weight:700;padding:11px;cursor:pointer;font-size:14px;">Fill using V3 exact IDs</button>' +
      '<button type="button" id="gov-cabnet-edxeix-helper-v3-diagnostic" style="width:100%;border:1px solid #cbd5e1;border-radius:9px;background:#f8fafc;color:#0f172a;font-weight:700;padding:9px;cursor:pointer;font-size:13px;margin-top:8px;">Copy/report V3 diagnostic</button>' +
      '<div id="gov-cabnet-edxeix-helper-v3-output" style="white-space:pre-wrap;font-size:12px;line-height:1.35;margin-top:9px;color:#41577a;max-height:230px;overflow:auto;">V3 is fill-only. No POST/save action is available here.</div>';
    document.documentElement.appendChild(div);
    qs('#gov-cabnet-edxeix-helper-v3-close').addEventListener('click', function () { div.remove(); });
    qs('#gov-cabnet-edxeix-helper-v3-company').addEventListener('click', openCorrectCompany);
    qs('#gov-cabnet-edxeix-helper-v3-fill').addEventListener('click', async function () { var payload = await loadPayload(); await fillPayload(payload); });
    qs('#gov-cabnet-edxeix-helper-v3-diagnostic').addEventListener('click', copyDiagnostic);
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', buildPanel); } else { buildPanel(); }
})();
