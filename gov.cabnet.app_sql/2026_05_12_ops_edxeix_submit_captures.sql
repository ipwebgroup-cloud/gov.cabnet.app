-- gov.cabnet.app — EDXEIX submit sanitized capture table
-- Safe additive migration. No existing tables are dropped or modified.
-- Purpose: store sanitized research metadata for the future server-side EDXEIX submitter.
-- Do not store cookies, sessions, CSRF token values, passwords, or private credentials here.

CREATE TABLE IF NOT EXISTS ops_edxeix_submit_captures (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    capture_status ENUM('draft', 'candidate', 'validated', 'retired') NOT NULL DEFAULT 'draft',
    form_method VARCHAR(10) NOT NULL DEFAULT 'POST',
    form_action_host VARCHAR(190) NOT NULL DEFAULT '',
    form_action_path VARCHAR(500) NOT NULL DEFAULT '',
    csrf_field_name VARCHAR(190) NOT NULL DEFAULT '',
    map_lat_field_name VARCHAR(190) NOT NULL DEFAULT '',
    map_lng_field_name VARCHAR(190) NOT NULL DEFAULT '',
    map_address_field_name VARCHAR(190) NOT NULL DEFAULT '',
    required_field_names_json JSON NULL,
    select_field_names_json JSON NULL,
    sanitized_summary_json JSON NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ops_edxeix_submit_captures_status_created (capture_status, created_at),
    KEY idx_ops_edxeix_submit_captures_user_created (user_id, created_at),
    KEY idx_ops_edxeix_submit_captures_action (form_action_host, form_action_path(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
