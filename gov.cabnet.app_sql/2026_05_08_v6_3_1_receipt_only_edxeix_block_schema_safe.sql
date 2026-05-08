/*
  gov.cabnet.app v6.3.1
  Schema-safe receipt-only EDXEIX blocker.

  Purpose:
  - Protect AADE receipt-only mail bookings from EDXEIX live submission.
  - Avoid source_type because this live schema does not have that column.
*/

UPDATE normalized_bookings
SET
  never_submit_live = 1,
  edxeix_ready = 0,
  live_submit_block_reason = 'aade_receipt_only_no_edxeix_submission_allowed',
  updated_at = NOW()
WHERE
  (
    LOWER(COALESCE(source_system, '')) = 'bolt_mail'
    OR LOWER(COALESCE(source, '')) = 'bolt_mail'
    OR COALESCE(order_reference, '') LIKE 'mail:%'
    OR COALESCE(source_trip_id, '') LIKE 'mail:%'
    OR COALESCE(external_order_id, '') LIKE 'mail:%'
    OR LOWER(COALESCE(live_submit_block_reason, '')) LIKE '%receipt%'
    OR LOWER(COALESCE(live_submit_block_reason, '')) LIKE '%no_edxeix%'
  );

SELECT
  COUNT(*) AS receipt_only_edxeix_blocked
FROM normalized_bookings
WHERE
  never_submit_live = 1
  AND edxeix_ready = 0
  AND live_submit_block_reason = 'aade_receipt_only_no_edxeix_submission_allowed';
