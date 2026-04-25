-- gov.cabnet.app — classify existing local LAB dry-run attempts as dry-run only
-- Purpose:
-- - Existing submission_attempts rows created before the dry-run marker patch may have empty response_body.
-- - This marks only attempts connected to LAB/test/never-live normalized bookings.
-- - It does not mark real Bolt rows and does not create or send any EDXEIX request.

UPDATE submission_attempts AS a
JOIN submission_jobs AS j ON j.id = a.submission_job_id
JOIN normalized_bookings AS b ON b.id = j.normalized_booking_id
SET
  a.response_status = 0,
  a.success = 0,
  a.response_body = JSON_OBJECT(
    'ok', true,
    'mode', 'dry_run_worker',
    'dry_run_allowed', true,
    'live_submission_allowed', false,
    'would_submit_to_edxeix', false,
    'note', 'DRY RUN ONLY. No EDXEIX HTTP request was performed.'
  )
WHERE
  (a.response_body IS NULL OR a.response_body = '')
  AND (
    b.source_system LIKE '%lab%'
    OR b.source LIKE '%lab%'
    OR b.order_reference LIKE 'LAB-%'
    OR b.external_order_id LIKE 'LAB-%'
    OR b.is_test_booking = 1
    OR b.never_submit_live = 1
  );
