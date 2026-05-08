-- gov.cabnet.app v6.3.0
-- Purpose: permanently block receipt-only Bolt mail bookings from EDXEIX live submission.
-- Safety: additive/protective update only; no deletes; no AADE rows changed.
-- Run after backing up cabnet_gov.

UPDATE normalized_bookings
SET
  never_submit_live = 1,
  edxeix_ready = 0,
  live_submit_block_reason = 'aade_receipt_only_no_edxeix_submission_allowed',
  updated_at = NOW()
WHERE
  (
    LOWER(COALESCE(source_system, '')) = 'bolt_mail'
    OR LOWER(COALESCE(source_type, '')) = 'bolt_mail'
    OR LOWER(COALESCE(source, '')) = 'bolt_mail'
    OR COALESCE(order_reference, '') LIKE 'mail:%'
    OR COALESCE(source_trip_id, '') LIKE 'mail:%'
    OR COALESCE(external_order_id, '') LIKE 'mail:%'
    OR LOWER(COALESCE(live_submit_block_reason, '')) LIKE '%receipt%'
    OR LOWER(COALESCE(live_submit_block_reason, '')) LIKE '%no_edxeix%'
  );

-- Verification:
-- SELECT id, source_system, order_reference, never_submit_live, edxeix_ready, live_submit_block_reason
-- FROM normalized_bookings
-- WHERE source_system='bolt_mail' OR order_reference LIKE 'mail:%'
-- ORDER BY id DESC
-- LIMIT 20;
