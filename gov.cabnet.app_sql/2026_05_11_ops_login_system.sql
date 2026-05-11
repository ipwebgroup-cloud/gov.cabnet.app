-- gov.cabnet.app — Operator login system tables
-- Safe additive migration. No existing tables are dropped or modified.

CREATE TABLE IF NOT EXISTS ops_users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(80) NOT NULL,
    email VARCHAR(190) NULL,
    display_name VARCHAR(190) NOT NULL,
    role ENUM('admin', 'operator', 'viewer') NOT NULL DEFAULT 'operator',
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    last_login_ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ops_users_username (username),
    UNIQUE KEY uq_ops_users_email (email),
    KEY idx_ops_users_active_role (is_active, role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ops_login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    login_name VARCHAR(190) NOT NULL DEFAULT '',
    success TINYINT(1) NOT NULL DEFAULT 0,
    reason VARCHAR(80) NOT NULL DEFAULT '',
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    user_agent VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ops_login_attempts_window (success, created_at),
    KEY idx_ops_login_attempts_login_window (login_name, created_at),
    KEY idx_ops_login_attempts_ip_window (ip_address, created_at),
    KEY idx_ops_login_attempts_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ops_audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(80) NOT NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    user_agent VARCHAR(500) NOT NULL DEFAULT '',
    meta_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ops_audit_user_created (user_id, created_at),
    KEY idx_ops_audit_event_created (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
