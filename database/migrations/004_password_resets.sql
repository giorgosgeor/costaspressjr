CREATE TABLE IF NOT EXISTS password_resets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    token_hash  CHAR(64) NOT NULL,
    expires_at  DATETIME NOT NULL,
    consumed_at DATETIME NULL DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT UTC_TIMESTAMP(),
    INDEX idx_token_hash (token_hash),
    INDEX idx_user_id    (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
