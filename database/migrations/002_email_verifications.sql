-- Email verification: user.email_verified_at + email_verifications token table.
-- The runner swallows "Duplicate column" errors for the ALTER, so re-running
-- this migration is safe.

ALTER TABLE users
    ADD COLUMN email_verified_at DATETIME NULL DEFAULT NULL AFTER email;

CREATE TABLE IF NOT EXISTS email_verifications (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    -- Must match users.id exactly (signed INT) or InnoDB rejects the FK below
    -- with errno 150 "Foreign key constraint is incorrectly formed".
    user_id     INT             NOT NULL,
    token_hash  CHAR(64)        NOT NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at  DATETIME        NOT NULL,
    consumed_at DATETIME        NULL DEFAULT NULL,
    INDEX idx_token_hash (token_hash),
    INDEX idx_user_id    (user_id),
    CONSTRAINT fk_email_verifications_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
