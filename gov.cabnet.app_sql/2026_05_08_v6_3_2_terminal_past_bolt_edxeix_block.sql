/*
  gov.cabnet.app v6.3.2
  Block past/terminal Bolt API bookings from EDXEIX.

  Purpose:
  - Historical, finished, cancelled, no-show, and non-response Bolt rows
    must never become EDXEIX live submission candidates.
  - Keep EDXEIX queues untouched.
*/

UPDATE normalized_bookings
SET
  never_submit_live = 1,
  edxeix_ready = 0,
  live_submit_block_reason = CASE
    WHEN LOWER(COALESCE(order_status, '')) IN (
      'finished',
      'client_cancelled',
      'driver_cancelled_after_accept',
      'driver_did_not_respond',
      'client_did_not_show',
      'no_show',
      'cancelled'
    )
      THEN CONCAT('terminal_status_', LOWER(COALESCE(order_status, 'unknown')), '_no_edxeix_submission_allowed')
    WHEN started_at < (UTC_TIMESTAMP() + INTERVAL 3 HOUR - INTERVAL 2 MINUTE)
      THEN 'past_started_at_no_edxeix_submission_allowed'
    ELSE 'blocked_from_edxeix_submission'
  END,
  updated_at = NOW()
WHERE
  LOWER(COALESCE(source_system, '')) = 'bolt'
  AND (
    LOWER(COALESCE(order_status, '')) IN (
      'finished',
      'client_cancelled',
      'driver_cancelled_after_accept',
      'driver_did_not_respond',
      'client_did_not_show',
      'no_show',
      'cancelled'
    )
    OR started_at < (UTC_TIMESTAMP() + INTERVAL 3 HOUR - INTERVAL 2 MINUTE)
  );

SELECT
  COUNT(*) AS blocked_past_or_terminal_bolt_rows
FROM normalized_bookings
WHERE
  LOWER(COALESCE(source_system, '')) = 'bolt'
  AND never_submit_live = 1
  AND edxeix_ready = 0
  AND (
    live_submit_block_reason LIKE 'terminal_status_%'
    OR live_submit_block_reason = 'past_started_at_no_edxeix_submission_allowed'
  );
