-- gov.cabnet.app — V3 closed-gate canary verification
-- Date: 2026-05-14
-- Purpose: read-only verification for the validated canary queue row #716.
-- Safety: SELECT only. No writes. No schema changes.

SELECT
  id,
  queue_status,
  customer_name,
  pickup_datetime,
  TIMESTAMPDIFF(MINUTE, NOW(), pickup_datetime) AS minutes_until,
  driver_name,
  vehicle_plate,
  lessor_id,
  driver_id,
  vehicle_id,
  starting_point_id,
  submitted_at,
  failed_at,
  last_error,
  created_at,
  updated_at
FROM pre_ride_email_v3_queue
WHERE id = 716;

SELECT
  id,
  queue_id,
  approval_status,
  approval_scope,
  approved_by,
  approved_at,
  expires_at,
  revoked_at,
  created_at,
  updated_at
FROM pre_ride_email_v3_live_submit_approvals
WHERE queue_id = 716
ORDER BY id DESC;
