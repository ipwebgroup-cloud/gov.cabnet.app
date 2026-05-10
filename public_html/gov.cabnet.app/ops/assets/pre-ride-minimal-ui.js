/* gov.cabnet.app — Pre-Ride Email Tool minimal staff UI
 * v6.6.16-minimal-ui
 * UI-only layer. No DB writes, no EDXEIX calls, no AADE calls.
 */
(function () {
  'use strict';

  var VERSION = 'v6.6.16-minimal-ui';

  function norm(value) {
    return String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();
  }

  function label(el) {
    if (!el) return '';
    return norm(el.textContent || el.value || el.getAttribute('aria-label') || el.title || '');
  }

  function allClickable() {
    return Array.prototype.slice.call(document.querySelectorAll('button, a, input[type="submit"], input[type="button"]'));
  }

  function findClickable(needles) {
    var lower = needles.map(norm);
    return allClickable().find(function (el) {
      var text = label(el);
      return lower.some(function (needle) { return text.indexOf(needle) !== -1; });
    }) || null;
  }

  function clickExisting(needles, missingMessage) {
    var el = findClickable(needles);
    if (!el) {
      setStatus(missingMessage || 'Required workflow button was not found on this page.', 'bad');
      return false;
    }
    el.click();
    return true;
  }

  function pageText() {
    return document.body ? document.body.innerText || '' : '';
  }

  function setStatus(message, mode) {
    var box = document.getElementById('gc-staff-status');
    if (!box) return;
    box.className = 'gc-staff-status ' + (mode || '');
    box.textContent = message;
  }

  function detectStatus() {
    var text = pageText();
    if (/IDs READY/i.test(text) || /IDs\s+READY/i.test(text)) {
      setStatus('Ready: IDs are resolved. Review the details, then open EDXEIX.', 'ready');
      return;
    }
    if (/CHECK IDS/i.test(text) || /not mapped/i.test(text) || /mapping conflict/i.test(text)) {
      setStatus('Attention: IDs need review before EDXEIX can be opened.', 'warn');
      return;
    }
    if (/READY TO REVIEW/i.test(text)) {
      setStatus('Email parsed. Waiting for DB ID confirmation.', 'warn');
      return;
    }
    setStatus('Step 1: Load the latest server email and check IDs.', '');
  }

  function hideNonWorkflowButtons() {
    allClickable().forEach(function (el) {
      if (el.closest('.gc-staff-shell')) return;
      var text = label(el);
      if (
        text.indexOf('load safe sample') !== -1 ||
        text.indexOf('parse email') !== -1 ||
        text === 'clear' ||
        text.indexOf('copy') !== -1
      ) {
        el.classList.add('gc-hide-button');
      }
    });
  }

  function buildPanel() {
    if (document.getElementById('gc-staff-shell')) return;

    document.body.classList.add('gc-minimal-ui');

    var shell = document.createElement('section');
    shell.id = 'gc-staff-shell';
    shell.className = 'gc-staff-shell';
    shell.innerHTML =
      '<div class="gc-staff-head">' +
        '<div>' +
          '<h1 class="gc-staff-title">Pre-Ride EDXEIX Assistant</h1>' +
          '<p class="gc-staff-subtitle">Use only these buttons for the current office workflow. The operator still verifies all fields before saving in EDXEIX.</p>' +
        '</div>' +
        '<div class="gc-staff-badges">' +
          '<span class="gc-staff-badge">NO AADE</span>' +
          '<span class="gc-staff-badge">NO AUTO POST</span>' +
          '<span class="gc-staff-badge">EDXEIX IDS FROM DB</span>' +
        '</div>' +
      '</div>' +
      '<div class="gc-staff-grid">' +
        '<div class="gc-staff-step">' +
          '<div><strong>1. Load latest email</strong><p>Reads the newest server copy of the Bolt pre-ride email and checks driver, vehicle, and company IDs.</p></div>' +
          '<button type="button" class="gc-staff-btn load" id="gc-load-latest">Load latest email + check IDs</button>' +
        '</div>' +
        '<div class="gc-staff-step">' +
          '<div><strong>2. Open EDXEIX</strong><p>Use only after the status says IDs READY. Opens the correct EDXEIX company page for the Firefox helper.</p></div>' +
          '<button type="button" class="gc-staff-btn open" id="gc-open-edxeix">Save + open EDXEIX</button>' +
        '</div>' +
        '<div class="gc-staff-step">' +
          '<div><strong>3. Manual fallback</strong><p>Only use if the latest server email is not available. Paste email below, then check IDs.</p></div>' +
          '<button type="button" class="gc-staff-btn secondary" id="gc-toggle-manual">Show manual paste fallback</button>' +
        '</div>' +
      '</div>' +
      '<div id="gc-staff-status" class="gc-staff-status">Step 1: Load the latest server email and check IDs.</div>' +
      '<div class="gc-staff-manual" id="gc-manual-box">' +
        '<p>Manual mode is visible below. Paste the email into the existing email box, then press this button.</p>' +
        '<button type="button" class="gc-staff-btn secondary" id="gc-parse-manual">Check pasted email + IDs</button> ' +
        '<button type="button" class="gc-staff-btn reset" id="gc-reset-page">Reset screen</button>' +
      '</div>';

    var nav = document.querySelector('nav');
    if (nav && nav.parentNode) {
      nav.parentNode.insertBefore(shell, nav.nextSibling);
    } else if (document.body.firstChild) {
      document.body.insertBefore(shell, document.body.firstChild);
    } else {
      document.body.appendChild(shell);
    }

    document.getElementById('gc-load-latest').addEventListener('click', function () {
      setStatus('Loading latest email and checking IDs...', '');
      clickExisting(['load latest server email + db ids', 'load latest server email'], 'Could not find the latest-email workflow button. Confirm v6.6.8+ is live.');
    });

    document.getElementById('gc-open-edxeix').addEventListener('click', function () {
      var text = pageText();
      if (!/IDs READY/i.test(text)) {
        setStatus('Blocked: IDs are not ready yet. Load/check the email first.', 'warn');
        return;
      }
      setStatus('Saving to helper and opening EDXEIX...', 'ready');
      clickExisting(['save + open edxeix', 'save for edxeix helper', 'open edxeix'], 'Could not find the Save + open EDXEIX button. Scroll down and confirm the editable form rendered.');
    });

    document.getElementById('gc-toggle-manual').addEventListener('click', function () {
      var box = document.getElementById('gc-manual-box');
      box.classList.toggle('open');
      this.textContent = box.classList.contains('open') ? 'Hide manual paste fallback' : 'Show manual paste fallback';
    });

    document.getElementById('gc-parse-manual').addEventListener('click', function () {
      setStatus('Checking pasted email and DB IDs...', '');
      clickExisting(['parse email + db ids', 'parse email'], 'Could not find the manual parse button.');
    });

    document.getElementById('gc-reset-page').addEventListener('click', function () {
      var clear = findClickable(['clear']);
      if (clear) clear.click();
      else window.location.href = window.location.pathname + '?v=6616';
    });

    hideNonWorkflowButtons();
    detectStatus();
  }

  function refresh() {
    hideNonWorkflowButtons();
    detectStatus();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', buildPanel);
  } else {
    buildPanel();
  }

  window.addEventListener('load', function () {
    buildPanel();
    refresh();
    setInterval(refresh, 1500);
  });

  window.govCabnetMinimalUiVersion = VERSION;
})();
