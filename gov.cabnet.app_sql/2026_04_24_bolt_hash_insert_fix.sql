-- gov.cabnet.app Bolt dedupe hash hardening
-- Optional reference SQL. The PHP cleanup script performs the live repair.
-- This file does not submit anything to EDXEIX.

UPDATE `bolt_raw_payloads`
SET `payload_hash` = SHA2(CONCAT('payload|', COALESCE(`external_reference`, ''), '|', `id`), 256)
WHERE `payload_hash` IS NULL OR `payload_hash` = '';

UPDATE `bolt_raw_payloads`
SET `dedupe_hash` = COALESCE(NULLIF(`payload_hash`, ''), SHA2(CONCAT('bolt_raw_payloads|', COALESCE(`external_reference`, ''), '|', `id`), 256))
WHERE `dedupe_hash` IS NULL OR `dedupe_hash` = '';

UPDATE `normalized_bookings`
SET `dedupe_hash` = SHA2(CONCAT('normalized_bookings|', COALESCE(`source_system`, 'bolt'), '|', COALESCE(NULLIF(`external_order_id`, ''), NULLIF(`order_reference`, ''), `id`)), 256)
WHERE `dedupe_hash` IS NULL OR `dedupe_hash` = '';
