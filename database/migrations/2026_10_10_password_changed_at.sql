-- Security audit S7. A password reset signs the account out everywhere:
-- Auth::refresh() ends any session that signed in before this time (UTC).
ALTER TABLE users ADD COLUMN password_changed_at DATETIME NULL;
