(function () {
  'use strict';
  if (typeof browser === 'undefined' || !browser.storage || !browser.storage.local) {
    window.postMessage({ type: 'GOV_CABNET_EDXEIX_PAYLOAD_V3_SAVED', ok: false, error: 'browser.storage unavailable' }, '*');
    return;
  }

  window.addEventListener('message', async function (event) {
    if (event.source !== window) { return; }
    var msg = event.data || {};
    if (!msg || msg.type !== 'GOV_CABNET_EDXEIX_PAYLOAD_V3') { return; }

    var payload = msg.payload || {};
    payload.savedAt = payload.savedAt || new Date().toISOString();
    payload.source = payload.source || 'gov.cabnet.app pre-ride email tool v3 isolated';

    try {
      await browser.storage.local.set({
        govCabnetV3LatestPayload: payload,
        govCabnetV3LatestPayloadSavedAt: payload.savedAt
      });
      window.postMessage({ type: 'GOV_CABNET_EDXEIX_PAYLOAD_V3_SAVED', ok: true, savedAt: payload.savedAt }, '*');
    } catch (e) {
      window.postMessage({ type: 'GOV_CABNET_EDXEIX_PAYLOAD_V3_SAVED', ok: false, error: String(e && e.message ? e.message : e) }, '*');
    }
  });
})();
