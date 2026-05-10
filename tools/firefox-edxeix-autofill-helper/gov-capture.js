(function () {
  'use strict';

  if (typeof browser === 'undefined' || !browser.storage || !browser.storage.local) {
    window.postMessage({ type: 'GOV_CABNET_EDXEIX_PAYLOAD_SAVED', ok: false, error: 'browser.storage unavailable' }, '*');
    return;
  }

  window.addEventListener('message', async function (event) {
    if (event.source !== window) { return; }
    var msg = event.data || {};
    if (!msg || msg.type !== 'GOV_CABNET_EDXEIX_PAYLOAD') { return; }

    var payload = msg.payload || {};
    payload.savedAt = payload.savedAt || new Date().toISOString();
    payload.source = payload.source || 'gov.cabnet.app pre-ride email tool';

    try {
      await browser.storage.local.set({
        govCabnetLatestPayload: payload,
        govCabnetLatestPayloadSavedAt: payload.savedAt
      });
      window.postMessage({ type: 'GOV_CABNET_EDXEIX_PAYLOAD_SAVED', ok: true, savedAt: payload.savedAt }, '*');
    } catch (e) {
      window.postMessage({ type: 'GOV_CABNET_EDXEIX_PAYLOAD_SAVED', ok: false, error: String(e && e.message ? e.message : e) }, '*');
    }
  });
})();
