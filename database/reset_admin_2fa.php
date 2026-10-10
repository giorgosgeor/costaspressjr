<?php
/**
 * Turn off two-step sign-in for an admin who lost both the phone and the
 * recovery codes, or who is moving to a new phone. The next sign-in with the
 * password sets it up again, with new recovery codes (TwoFactorController).
 *
 *   php database/reset_admin_2fa.php --email=you@example.com
 *
 * No shell? In phpMyAdmin, set totp_secret and totp_enabled_at to NULL on
 * that admin's row in users.
 */

require __DIR__ . '/../app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$email = trim((string)(getopt('', ['email:'])['email'] ?? ''));
if ($email === '') {
    fwrite(STDERR, "usage: php database/reset_admin_2fa.php --email=you@example.com\n");
    exit(1);
}

$pdo = require __DIR__ . '/../app/config/database.php';
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = 'admin'");
$stmt->execute([$email]);
$id = $stmt->fetchColumn();
if (!$id) {
    fwrite(STDERR, "No admin account has that email.\n");
    exit(1);
}

$pdo->prepare("UPDATE users SET totp_secret = NULL, totp_enabled_at = NULL, totp_last_step = NULL WHERE id = ?")->execute([$id]);
$pdo->prepare("DELETE FROM admin_recovery_codes WHERE user_id = ?")->execute([$id]);
echo "Two-step sign-in is off for $email. The next sign-in sets it up again.\n";
