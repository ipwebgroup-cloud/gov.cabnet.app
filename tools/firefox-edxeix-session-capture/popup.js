(() => {
  'use strict';

  const api = typeof browser !== 'undefined' ? browser : chrome;
  const VERSION = '0.1.2';
  const GOV_CAPTURE_ENDPOINT = 'https://gov.cabnet.app/ops/edxeix-session-capture.php';
  const GOV_SESSION_URL = 'https://gov.cabnet.app/ops/edxeix-session.php';
  const GOV_LIVE_GATE_URL = 'https://gov.cabnet.app/ops/live-submit.php';
  const FIXED_SUBMIT_URL = 'https://edxeix.yme.gov.gr/dashboard/lease-agreement';
  const EDXEIX_URL_FOR_COOKIES = 'https://edxeix.yme.gov.gr/dashboard/lease-agreement/create';

  let captured = null;

  const el = (id) => document.getElementById(id);
  const status = el('status');
  const captureBtn = el('captureBtn');
  const saveBtn = el('saveBtn');
  const openSessionBtn = el('openSessionBtn');
  const openLiveGateBtn = el('openLiveGateBtn');

  function setStatus(message, type = 'neutral') {
    status.textContent = message;
    status.className = `status ${type}`;
  }

  function setText(id, text) {
    el(id).textContent = String(text);
  }

  function validTabUrl(url) {
    try {
      const u = new URL(url);
      return u.protocol === 'https:'
        && u.hostname === 'edxeix.yme.gov.gr'
        && u.pathname.indexOf('/dashboard/lease-agreement') === 0;
    } catch (_) {
      return false;
    }
  }

  function isCreateFormUrl(url) {
    try {
      const u = new URL(url);
      return u.protocol === 'https:'
        && u.hostname === 'edxeix.yme.gov.gr'
        && u.pathname === '/dashboard/lease-agreement/create';
    } catch (_) {
      return false;
    }
  }

  function looksPlaceholder(value) {
    const v = String(value || '').toUpperCase();
    return !v || ['PASTE', 'REPLACE', 'EXAMPLE', 'DUMMY', 'DEMO', 'TODO', 'COOKIE_HEADER', 'CSRF_TOKEN', 'YYYY-MM-DD', 'HH:MM:SS'].some((marker) => v.includes(marker));
  }

  function cookieHeaderFromCookies(cookies) {
    const seen = new Set();
    return cookies
      .filter((cookie) => cookie && cookie.name)
      .sort((a, b) => {
        const ap = (a.path || '').length;
        const bp = (b.path || '').length;
        return bp - ap || String(a.name).localeCompare(String(b.name));
      })
      .filter((cookie) => {
        const key = cookie.name;
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
      })
      .map((cookie) => `${cookie.name}=${cookie.value || ''}`)
      .join('; ');
  }

  async function getActiveTab() {
    const tabs = await api.tabs.query({ active: true, currentWindow: true });
    return tabs && tabs.length ? tabs[0] : null;
  }

  async function captureFromPage(tabId) {
    const code = `(() => {
      const tokenInput = document.querySelector('input[name="_token"]');
      const forms = Array.from(document.querySelectorAll('form'));
      const preferred = forms.find((form) => String(form.action || '').includes('/dashboard/lease-agreement')) || forms[0] || null;
      return {
        page_url: location.href,
        title: document.title,
        detected_form_action: preferred ? String(preferred.action || '') : '',
        detected_form_method: preferred ? String(preferred.method || 'POST').toUpperCase() : 'POST',
        csrf_token: tokenInput ? String(tokenInput.value || '') : '',
        visible_cookie_length: String(document.cookie || '').length
      };
    })();`;
    const result = await api.tabs.executeScript(tabId, { code });
    return result && result.length ? result[0] : null;
  }

  async function captureCookies() {
    const cookies = await api.cookies.getAll({ url: EDXEIX_URL_FOR_COOKIES });
    return {
      count: cookies.length,
      header: cookieHeaderFromCookies(cookies),
    };
  }

  function updateDetectedState(data) {
    setText('tabState', data && data.page_url ? (isCreateFormUrl(data.page_url) ? 'ok' : 'lease page') : 'missing');
    setText('actionState', 'fixed');
    setText('csrfState', data && data.csrf_token ? `${data.csrf_token.length} chars` : 'missing');
    setText('cookieState', data && data.cookie_count ? `${data.cookie_count} cookies` : 'missing');
    setText('cookieLength', data && data.cookie_header ? `${data.cookie_header.length}` : '0');
    setText('versionState', VERSION);
    saveBtn.disabled = !(data && data.csrf_token && data.cookie_header);
  }

  async function doCapture() {
    setStatus('Capturing from active EDXEIX tab...', 'warn');
    saveBtn.disabled = true;
    captured = null;

    const tab = await getActiveTab();
    if (!tab || !validTabUrl(tab.url || '')) {
      updateDetectedState(null);
      setStatus('Open the EDXEIX lease-agreement create form first, then click this extension.', 'bad');
      return;
    }

    const page = await captureFromPage(tab.id);
    const cookieData = await captureCookies();

    const data = {
      page_url: page ? page.page_url : (tab.url || ''),
      fixed_submit_url: FIXED_SUBMIT_URL,
      detected_form_action: page ? page.detected_form_action : '',
      detected_form_method: page ? page.detected_form_method : 'POST',
      form_method: 'POST',
      csrf_token: page ? page.csrf_token : '',
      cookie_header: cookieData.header,
      cookie_count: cookieData.count,
    };

    updateDetectedState(data);

    const warnings = [];
    if (!isCreateFormUrl(data.page_url)) warnings.push('not on /create form');
    if (!data.csrf_token || looksPlaceholder(data.csrf_token)) warnings.push('CSRF token missing/placeholder');
    if (!data.cookie_header || looksPlaceholder(data.cookie_header)) warnings.push('cookie header missing/placeholder');

    captured = data;

    if (warnings.length) {
      setStatus(`Captured with warnings: ${warnings.join(', ')}.`, 'bad');
    } else {
      setStatus('Captured. Click save to update server-only session values.', 'good');
    }
  }

  async function doSave() {
    if (!captured) {
      setStatus('Capture values first.', 'bad');
      return;
    }

    setStatus('Saving to gov.cabnet.app server-only storage...', 'warn');
    saveBtn.disabled = true;

    const body = new URLSearchParams();
    body.set('form_method', 'POST');
    body.set('cookie_header', captured.cookie_header || '');
    body.set('csrf_token', captured.csrf_token || '');
    body.set('source_url', captured.page_url || '');
    body.set('detected_form_action', captured.detected_form_action || '');
    body.set('extension_version', VERSION);

    let response;
    let json;
    try {
      response = await fetch(GOV_CAPTURE_ENDPOINT, {
        method: 'POST',
        credentials: 'omit',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: body.toString(),
      });
      json = await response.json();
    } catch (err) {
      saveBtn.disabled = false;
      setStatus(`Save failed: ${err.message || err}`, 'bad');
      return;
    }

    if (!response.ok || !json || !json.ok || !json.saved) {
      saveBtn.disabled = false;
      const errors = json && json.errors ? json.errors.join(', ') : (json && json.error ? json.error : `HTTP ${response.status}`);
      setStatus(`Server refused save: ${errors}`, 'bad');
      return;
    }

    setStatus('Saved. Live flags remain disabled. Click a verification button below.', 'good');
  }

  async function openTab(url) {
    await api.tabs.create({ url });
  }

  async function init() {
    setText('versionState', VERSION);
    const tab = await getActiveTab();
    setText('tabState', tab && validTabUrl(tab.url || '') ? (isCreateFormUrl(tab.url || '') ? 'EDXEIX create tab detected' : 'EDXEIX lease tab detected') : 'open EDXEIX form');
    setText('actionState', 'fixed');
  }

  captureBtn.addEventListener('click', () => { doCapture().catch((err) => setStatus(`Capture failed: ${err.message || err}`, 'bad')); });
  saveBtn.addEventListener('click', () => { doSave().catch((err) => setStatus(`Save failed: ${err.message || err}`, 'bad')); });
  openSessionBtn.addEventListener('click', () => { openTab(GOV_SESSION_URL).catch((err) => setStatus(`Could not open session page: ${err.message || err}`, 'bad')); });
  openLiveGateBtn.addEventListener('click', () => { openTab(GOV_LIVE_GATE_URL).catch((err) => setStatus(`Could not open live gate: ${err.message || err}`, 'bad')); });

  init().catch(() => {});
})();
