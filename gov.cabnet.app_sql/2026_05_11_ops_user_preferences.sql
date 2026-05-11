-- gov.cabnet.app — Operator UI preferences
-- Safe additive migration. No existing tables are dropped or modified.

CREATE TABLE IF NOT EXISTS ops_user_preferences (
    user_id BIGINT UNSIGNED NOT NULL,
    default_landing_path VARCHAR(190) NOT NULL DEFAULT '/ops/home.php',
    sidebar_density ENUM('comfortable', 'compact') NOT NULL DEFAULT 'comfortable',
    table_density ENUM('comfortable', 'compact') NOT NULL DEFAULT 'comfortable',
    show_safety_notices TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    KEY idx_ops_user_preferences_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
