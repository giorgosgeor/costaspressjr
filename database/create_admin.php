<?php
/**
 * Create an admin account — the way to get the first one onto a fresh
 * production database (database/export_for_production.php leaves users out).
 *
 *   php database/create_admin.php --email=you@example.com --username=Giorgos
 *
 * The password is asked for at the prompt, never taken as an argument: the
 * command line ends up in shell history and the process list. Input is
 * hidden on Linux/macOS; on Windows it is echoed, so clear the screen after.
 */

require __DIR__ . '/../app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$opts     = getopt('', ['email:', 'username:']);
$email    = trim((string)($opts['email'] ?? ''));
$username = trim((string)($opts['username'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $username === '') {
    fwrite(STDERR, "usage: php database/create_admin.php --email=you@example.com --username=Name\n");
    exit(1);
}

function prompt(string $label): string {
    echo $label;
    $hide = DIRECTORY_SEPARATOR === '/' && stream_isatty(STDIN);
    if ($hide) shell_exec('stty -echo');
    $value = rtrim((string)fgets(STDIN), "\r\n");
    if ($hide) { shell_exec('stty echo'); echo "\n"; }
    return $value;
}

$password = prompt('Password (12+ characters): ');
if (mb_strlen($password) < 12) {
    fwrite(STDERR, "Too short: an admin can see every order and customer, so use 12 characters or more.\n");
    exit(1);
}
if (prompt('Repeat password: ') !== $password) {
    fwrite(STDERR, "The passwords don't match.\n");
    exit(1);
}

$db = require __DIR__ . '/../app/config/database.php';

$stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetchColumn()) {
    fwrite(STDERR, "An account with $email already exists.\n");
    exit(1);
}

// Verified from the start: the admin never gets a verification email.
$db->prepare("INSERT INTO users (username, email, phone, password_hash, role, email_verified_at) VALUES (?, ?, '', ?, 'admin', NOW())")
   ->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);

echo "Admin account created for $email (id " . $db->lastInsertId() . "). Log in at " . rtrim((string)Env::get('APP_URL', ''), '/') . "/login\n";
