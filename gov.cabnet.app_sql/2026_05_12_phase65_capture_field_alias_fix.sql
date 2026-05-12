-- gov.cabnet.app — Phase 65
-- EDXEIX sanitized capture compatibility fix
--
-- Purpose:
-- 1) Preserve square brackets in EDXEIX field names such as lessee[type].
-- 2) Add/fill select_field_names compatibility alias from select_field_names_json.
-- 3) Normalize the latest sanitized lease-agreement capture so optional fields do not block dry-run readiness.
--
-- Safety:
-- - Metadata-only SQL.
-- - Does not call EDXEIX.
-- - Does not call Bolt.
-- - Does not call AADE.
-- - Does not enable live submission.
-- - Does not touch normalized bookings or production pre-ride workflow.

ALTER TABLE ops_edxeix_submit_captures
  ADD COLUMN IF NOT EXISTS select_field_names TEXT NULL AFTER required_field_names;

DROP TRIGGER IF EXISTS trg_ops_edxeix_submit_captures_bi_compat;
DROP TRIGGER IF EXISTS trg_ops_edxeix_submit_captures_bu_compat;

DELIMITER $$

CREATE TRIGGER trg_ops_edxeix_submit_captures_bi_compat
BEFORE INSERT ON ops_edxeix_submit_captures
FOR EACH ROW
BEGIN
  SET NEW.action_host = COALESCE(NULLIF(NEW.action_host, ''), NEW.form_action_host);
  SET NEW.action_path = COALESCE(NULLIF(NEW.action_path, ''), NEW.form_action_path);
  SET NEW.coordinate_field_names = COALESCE(
    NULLIF(NEW.coordinate_field_names, ''),
    NULLIF(TRIM(BOTH '\n' FROM CONCAT_WS('\n',
      NULLIF(NEW.map_address_field_name, ''),
      NULLIF(NEW.map_lat_field_name, ''),
      NULLIF(NEW.map_lng_field_name, '')
    )), '')
  );

  -- IMPORTANT: remove only the outer JSON array markers and item delimiters.
  -- Do NOT remove square brackets inside field names, e.g. lessee[type].
  SET NEW.required_field_names = CASE
    WHEN NEW.required_field_names_json IS NOT NULL AND TRIM(NEW.required_field_names_json) <> '' THEN
      NULLIF(TRIM(BOTH '\n' FROM REPLACE(REPLACE(REPLACE(COALESCE(NEW.required_field_names_json, ''), '\",\"', '\n'), '[\"', ''), '\"]', '')), '')
    ELSE NULLIF(NEW.required_field_names, '')
  END;

  SET NEW.select_field_names = CASE
    WHEN NEW.select_field_names_json IS NOT NULL AND TRIM(NEW.select_field_names_json) <> '' THEN
      NULLIF(TRIM(BOTH '\n' FROM REPLACE(REPLACE(REPLACE(COALESCE(NEW.select_field_names_json, ''), '\",\"', '\n'), '[\"', ''), '\"]', '')), '')
    ELSE NULLIF(NEW.select_field_names, '')
  END;
END$$

CREATE TRIGGER trg_ops_edxeix_submit_captures_bu_compat
BEFORE UPDATE ON ops_edxeix_submit_captures
FOR EACH ROW
BEGIN
  SET NEW.action_host = COALESCE(NULLIF(NEW.action_host, ''), NEW.form_action_host);
  SET NEW.action_path = COALESCE(NULLIF(NEW.action_path, ''), NEW.form_action_path);
  SET NEW.coordinate_field_names = COALESCE(
    NULLIF(NEW.coordinate_field_names, ''),
    NULLIF(TRIM(BOTH '\n' FROM CONCAT_WS('\n',
      NULLIF(NEW.map_address_field_name, ''),
      NULLIF(NEW.map_lat_field_name, ''),
      NULLIF(NEW.map_lng_field_name, '')
    )), '')
  );

  -- IMPORTANT: remove only the outer JSON array markers and item delimiters.
  -- Do NOT remove square brackets inside field names, e.g. lessee[type].
  SET NEW.required_field_names = CASE
    WHEN NEW.required_field_names_json IS NOT NULL AND TRIM(NEW.required_field_names_json) <> '' THEN
      NULLIF(TRIM(BOTH '\n' FROM REPLACE(REPLACE(REPLACE(COALESCE(NEW.required_field_names_json, ''), '\",\"', '\n'), '[\"', ''), '\"]', '')), '')
    ELSE NULLIF(NEW.required_field_names, '')
  END;

  SET NEW.select_field_names = CASE
    WHEN NEW.select_field_names_json IS NOT NULL AND TRIM(NEW.select_field_names_json) <> '' THEN
      NULLIF(TRIM(BOTH '\n' FROM REPLACE(REPLACE(REPLACE(COALESCE(NEW.select_field_names_json, ''), '\",\"', '\n'), '[\"', ''), '\"]', '')), '')
    ELSE NULLIF(NEW.select_field_names, '')
  END;
END$$

DELIMITER ;

-- Normalize the latest sanitized EDXEIX lease-agreement capture.
-- The console extraction gave all field names, but not every field is required for the natural-person dry-run payload.
-- This keeps optional broker/VAT/legal representative fields from incorrectly blocking dry-run validation.
UPDATE ops_edxeix_submit_captures
SET
  required_field_names_json = '["_token","lessor","lessee[type]","lessee[name]","driver","vehicle","starting_point_id","boarding_point","coordinates","disembark_point","drafted_at","started_at","ended_at","price"]',
  select_field_names_json = '["lessor","lessee[type]","driver","vehicle","starting_point_id"]',
  updated_at = NOW(),
  notes = CONCAT(
    COALESCE(NULLIF(notes, ''), 'Sanitized EDXEIX lease-agreement capture.'),
    '\nPhase 65: normalized required/select field metadata; preserved bracketed field names; optional broker/VAT/legal representative are not dry-run required.'
  )
WHERE id = (
  SELECT latest_id FROM (
    SELECT id AS latest_id
    FROM ops_edxeix_submit_captures
    WHERE form_action_host = 'edxeix.yme.gov.gr'
      AND form_action_path = '/dashboard/lease-agreement'
    ORDER BY id DESC
    LIMIT 1
  ) AS latest_capture
);

-- Backfill/refresh compatibility aliases for all existing capture rows from canonical JSON columns.
UPDATE ops_edxeix_submit_captures
SET
  action_host = COALESCE(NULLIF(action_host, ''), form_action_host),
  action_path = COALESCE(NULLIF(action_path, ''), form_action_path),
  coordinate_field_names = COALESCE(
    NULLIF(coordinate_field_names, ''),
    NULLIF(TRIM(BOTH '\n' FROM CONCAT_WS('\n',
      NULLIF(map_address_field_name, ''),
      NULLIF(map_lat_field_name, ''),
      NULLIF(map_lng_field_name, '')
    )), '')
  ),
  required_field_names = CASE
    WHEN required_field_names_json IS NOT NULL AND TRIM(required_field_names_json) <> '' THEN
      NULLIF(TRIM(BOTH '\n' FROM REPLACE(REPLACE(REPLACE(COALESCE(required_field_names_json, ''), '\",\"', '\n'), '[\"', ''), '\"]', '')), '')
    ELSE required_field_names
  END,
  select_field_names = CASE
    WHEN select_field_names_json IS NOT NULL AND TRIM(select_field_names_json) <> '' THEN
      NULLIF(TRIM(BOTH '\n' FROM REPLACE(REPLACE(REPLACE(COALESCE(select_field_names_json, ''), '\",\"', '\n'), '[\"', ''), '\"]', '')), '')
    ELSE select_field_names
  END;
