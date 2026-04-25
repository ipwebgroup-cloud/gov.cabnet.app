-- gov.cabnet.app — LAB dry-run cleanup preview
-- Safe/read-only SQL: counts LAB/test/never-submit-live rows that the cleanup UI targets.
-- Does not delete or update data.

SELECT COUNT(*) AS lab_test_normalized_bookings
FROM normalized_bookings
WHERE
  source_system LIKE '%lab%'
  OR source_system LIKE '%local_test%'
  OR source LIKE '%lab%'
  OR order_reference LIKE 'LAB-%'
  OR external_order_id LIKE 'LAB-%'
  OR is_test_booking = 1
  OR never_submit_live = 1
  OR test_booking_created_by IS NOT NULL;

SELECT COUNT(DISTINCT j.id) AS linked_local_jobs
FROM submission_jobs j
JOIN normalized_bookings b ON b.id = j.normalized_booking_id
WHERE
  b.source_system LIKE '%lab%'
  OR b.source_system LIKE '%local_test%'
  OR b.source LIKE '%lab%'
  OR b.order_reference LIKE 'LAB-%'
  OR b.external_order_id LIKE 'LAB-%'
  OR b.is_test_booking = 1
  OR b.never_submit_live = 1
  OR b.test_booking_created_by IS NOT NULL;

SELECT COUNT(DISTINCT a.id) AS linked_attempts
FROM submission_attempts a
JOIN submission_jobs j ON j.id = a.submission_job_id
JOIN normalized_bookings b ON b.id = j.normalized_booking_id
WHERE
  b.source_system LIKE '%lab%'
  OR b.source_system LIKE '%local_test%'
  OR b.source LIKE '%lab%'
  OR b.order_reference LIKE 'LAB-%'
  OR b.external_order_id LIKE 'LAB-%'
  OR b.is_test_booking = 1
  OR b.never_submit_live = 1
  OR b.test_booking_created_by IS NOT NULL;
