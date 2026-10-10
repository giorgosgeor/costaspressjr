-- Security audit S5. Admin accounts sign in with a code from an
-- authenticator app (TOTP) as well as the password (TwoFactorController).
ALTER TABLE users ADD COLUMN totp_secret VARCHAR(64) NULL;
ALTER TABLE users ADD COLUMN totp_enabled_at DATETIME NULL;
-- The time step of the last code accepted, so each code works only once.
ALTER TABLE users ADD COLUMN totp_last_step BIGINT NULL;

-- Single-use codes for when the phone is lost. Only SHA-256 hashes are kept;
-- the codes themselves are shown once, when two-step sign-in is set up.
CREATE TABLE IF NOT EXISTS admin_recovery_codes (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    code_hash  CHAR(64)     NOT NULL,
    used_at    DATETIME     NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_recovery_codes_user (user_id),
    CONSTRAINT fk_recovery_codes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
