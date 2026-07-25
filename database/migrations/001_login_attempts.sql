-- Track failed login attempts for rate-limiting.
-- Idempotent: CREATE TABLE IF NOT EXISTS so re-running this is harmless.

CREATE TABLE IF NOT EXISTS login_attempts (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_hash     CHAR(64)     NOT NULL,
    identifier  VARCHAR(191) NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time         (ip_hash, created_at),
    INDEX idx_identifier_time (identifier, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
