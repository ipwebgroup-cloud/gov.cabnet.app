-- gov.cabnet.app — Bolt order time-column compatibility fix
-- Purpose: allow Bolt orders with no finished/drop-off timestamp to be stored.
-- This does not submit anything to EDXEIX.

ALTER TABLE `normalized_bookings`
  MODIFY COLUMN `ended_at` DATETIME NULL DEFAULT NULL;
