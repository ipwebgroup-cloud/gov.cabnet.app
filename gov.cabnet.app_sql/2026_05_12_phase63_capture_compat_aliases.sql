-- gov.cabnet.app — Phase 63
-- EDXEIX submit capture compatibility aliases
--
-- Purpose:
-- The sanitized capture writer stores canonical columns such as:
--   form_action_host, form_action_path, required_field_names_json,
--   map_address_field_name, map_lat_field_name, map_lng_field_name
-- Some older/readiness pages still read compatibility names such as:
--   action_host, action_path, required_field_names, coordinate_field_names
--
-- This additive migration adds safe alias columns and keeps them populated.
-- It does not store cookies, sessions, CSRF token values, credentials, or private config.
-- It does not enable live EDXEIX submission.

ALTER TABLE ops_edxeix_submit_captures
  ADD COLUMN IF NOT EXISTS action_host VARCHAR(190) NULL AFTER form_action_path,
  ADD COLUMN IF NOT EXISTS action_path VARCHAR(500) NULL AFTER action_host,
  ADD COLUMN IF NOT EXISTS coordinate_field_names TEXT NULL AFTER map_address_field_name,
  ADD COLUMN IF NOT EXISTS required_field_names TEXT NULL AFTER required_field_names_json;

UPDATE ops_edxeix_submit_captures
SET
  action_host = COALESCE(NULLIF(action_host, ''), form_action_host),
  action_path = COALESCE(NULLIF(action_path, ''), form_action_path),
  coordinate_field_names = COALESCE(
    NULLIF(coordinate_field_names, ''),
    TRIM(BOTH '\n' FROM CONCAT_WS('\n',
      NULLIF(map_address_field_name, ''),
      NULLIF(map_lat_field_name, ''),
      NULLIF(map_lng_field_name, '')
    ))
  ),
  required_field_names = COALESCE(
    NULLIF(required_field_names, ''),
    TRIM(BOTH '\n' FROM REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(required_field_names_json, ''), '[', ''), ']', ''), '\",\"', '\n'), '\"', ''))
  );

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
    TRIM(BOTH '\n' FROM CONCAT_WS('\n',
      NULLIF(NEW.map_address_field_name, ''),
      NULLIF(NEW.map_lat_field_name, ''),
      NULLIF(NEW.map_lng_field_name, '')
    ))
  );
  SET NEW.required_field_names = COALESCE(
    NULLIF(NEW.required_field_names, ''),
    TRIM(BOTH '\n' FROM REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(NEW.required_field_names_json, ''), '[', ''), ']', ''), '\",\"', '\n'), '\"', ''))
  );
END$$

CREATE TRIGGER trg_ops_edxeix_submit_captures_bu_compat
BEFORE UPDATE ON ops_edxeix_submit_captures
FOR EACH ROW
BEGIN
  SET NEW.action_host = COALESCE(NULLIF(NEW.action_host, ''), NEW.form_action_host);
  SET NEW.action_path = COALESCE(NULLIF(NEW.action_path, ''), NEW.form_action_path);
  SET NEW.coordinate_field_names = COALESCE(
    NULLIF(NEW.coordinate_field_names, ''),
    TRIM(BOTH '\n' FROM CONCAT_WS('\n',
      NULLIF(NEW.map_address_field_name, ''),
      NULLIF(NEW.map_lat_field_name, ''),
      NULLIF(NEW.map_lng_field_name, '')
    ))
  );
  SET NEW.required_field_names = COALESCE(
    NULLIF(NEW.required_field_names, ''),
    TRIM(BOTH '\n' FROM REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(NEW.required_field_names_json, ''), '[', ''), ']', ''), '\",\"', '\n'), '\"', ''))
  );
END$$

DELIMITER ;
