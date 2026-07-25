<?php
// app/controllers/AuthController.php

class AuthController {
    private PDO $db;

    private const MAX_ATTEMPTS_PER_IP   = 10;
    private const MAX_ATTEMPTS_PER_USER = 5;
    private const WINDOW_SECONDS        = 900;

    private const VERIFICATION_TTL_HOURS = 24;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function showLogin(): void {
        $redirect = $this->safeRedirect($_GET['redirect'] ?? '');
        require __DIR__ . '/../views/auth/login.php';
    }

    public function login(): void {
        $identifier = trim((string)($_POST['identifier'] ?? ''));
        $password   = (string)($_POST['password'] ?? '');
        $redirect   = $this->safeRedirect($_POST['redirect'] ?? '');

        if ($identifier === '' || $password === '') {
            $error = 'Invalid credentials';
            require __DIR__ . '/../views/auth/login.php';
            return;
        }

        if ($this->isRateLimited($identifier)) {
            $error = 'Too many attempts. Please try again in a few minutes.';
            require __DIR__ . '/../views/auth/login.php';
            return;
        }

        $stmt = $this->db->prepare("SELECT id, password_hash, role FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->recordFailedAttempt($identifier);
            $error = 'Invalid credentials';
            require __DIR__ . '/../views/auth/login.php';
            return;
        }

        $this->clearAttempts($identifier);

        Auth::login($user['id'], $user['role']);
        // Ensure user has a cart
        require_once __DIR__ . '/../models/Cart.php';
        $cartModel = new \Cart($this->db);
        $cartModel->getOrCreateCartId($user['id']);

        if ($user['role'] === 'admin') {
            header('Location: /admin');
        } elseif ($redirect) {
            header('Location: ' . $redirect);
        } else {
            header('Location: /');
        }
        exit;
    }

    private function ipHash(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        // Hashing the IP means operators don't store raw PII in the attempts table.
        return hash('sha256', $ip . '|costaspressjr');
    }

    private function isRateLimited(string $identifier): bool {
        try {
            $since = gmdate('Y-m-d H:i:s', time() - self::WINDOW_SECONDS);

            $stmt = $this->db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_hash = ? AND created_at > ?");
            $stmt->execute([$this->ipHash(), $since]);
            if ((int)$stmt->fetchColumn() >= self::MAX_ATTEMPTS_PER_IP) return true;

            $stmt = $this->db->prepare("SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND created_at > ?");
            $stmt->execute([mb_strtolower($identifier), $since]);
            return (int)$stmt->fetchColumn() >= self::MAX_ATTEMPTS_PER_USER;
        } catch (PDOException $e) {
            error_log('Login rate-limit check failed: ' . $e->getMessage());
            return false;
        }
    }

    private function recordFailedAttempt(string $identifier): void {
        try {
            $stmt = $this->db->prepare("INSERT INTO login_attempts (ip_hash, identifier) VALUES (?, ?)");
            $stmt->execute([$this->ipHash(), mb_strtolower($identifier)]);

            if (random_int(1, 50) === 1) {
                $cutoff = gmdate('Y-m-d H:i:s', time() - (self::WINDOW_SECONDS * 4));
                $this->db->prepare("DELETE FROM login_attempts WHERE created_at < ?")->execute([$cutoff]);
            }
        } catch (PDOException $e) {
            error_log('Login attempt record failed: ' . $e->getMessage());
        }
    }

    private function clearAttempts(string $identifier): void {
        try {
            $stmt = $this->db->prepare("DELETE FROM login_attempts WHERE identifier = ? OR ip_hash = ?");
            $stmt->execute([mb_strtolower($identifier), $this->ipHash()]);
        } catch (PDOException $e) {
            error_log('Login attempt cleanup failed: ' . $e->getMessage());
        }
    }

    /** Only allow same-site relative paths (no protocol, no external hosts) */
    private function safeRedirect(string $url): string {
        $url = trim($url);
        if ($url === '' || $url[0] !== '/' || str_starts_with($url, '//')) return '';
        // Strip query-string fragments that look like protocol injection
        if (preg_match('/[^\x20-\x7E]|:/', $url)) return '';
        return $url;
    }

    public function showRegister(): void {
        require __DIR__ . '/../views/auth/register.php';
    }

    public function register(): void {
        $username = trim((string)($_POST['username'] ?? ''));
        $email    = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $phone    = trim((string)($_POST['phone'] ?? ''));

        $error = $this->validateRegistration($username, $email, $password);
        if ($error !== null) {
            require __DIR__ . '/../views/auth/register.php';
            return;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("INSERT INTO users (username, email, phone, password_hash) VALUES (?, ?, ?, ?)");
        try {
            $stmt->execute([$username, $email, $phone, $hash]);
        } catch (PDOException $e) {
            $error = 'Email, username, or phone already registered';
            require __DIR__ . '/../views/auth/register.php';
            return;
        }

        $userId = (int)$this->db->lastInsertId();
        Auth::login($userId, 'customer');

        require_once __DIR__ . '/../models/Cart.php';
        $cartModel = new \Cart($this->db);
        $cartModel->getOrCreateCartId($userId);

        $this->sendVerificationEmail($userId, $email, $username);

        header('Location: /home');
        exit;
    }

    /**
     * Generate a verification token for the user, store its hash, and send
     * the link by email. Silently logs failures — registration must not fail
     * just because the mailer can't reach SMTP.
     */
    private function sendVerificationEmail(int $userId, string $email, string $username): void {
        try {
            $token    = bin2hex(random_bytes(32));
            $hash     = hash('sha256', $token);
            $expires  = gmdate('Y-m-d H:i:s', time() + self::VERIFICATION_TTL_HOURS * 3600);

            $this->db->prepare("DELETE FROM email_verifications WHERE user_id = ? AND consumed_at IS NULL")
                ->execute([$userId]);

            $stmt = $this->db->prepare("INSERT INTO email_verifications (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $hash, $expires]);

            $base = rtrim((string)Env::get('APP_URL', ''), '/');
            if ($base === '') {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            }
            $link = $base . '/verify-email?token=' . urlencode($token);
            $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
            $safeName = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

            $html = "<p>Hi $safeName,</p>"
                  . "<p>Welcome to Costaspressjr! Please confirm your email address by clicking the link below:</p>"
                  . "<p><a href=\"$safeLink\">Verify my email</a></p>"
                  . "<p>This link expires in " . self::VERIFICATION_TTL_HOURS . " hours. If you didn't create an account, you can ignore this message.</p>";
            $text = "Hi $username,\n\nWelcome to Costaspressjr! Confirm your email by opening this link:\n$link\n\nThis link expires in " . self::VERIFICATION_TTL_HOURS . " hours.";

            Mailer::send($email, 'Confirm your Costaspressjr email', $html, $text);
        } catch (Throwable $e) {
            error_log('Email verification send failed: ' . $e->getMessage());
        }
    }

    public function verifyEmail(): void {
        $token = (string)($_GET['token'] ?? '');
        if ($token === '' || strlen($token) !== 64 || !ctype_xdigit($token)) {
            $this->renderVerificationResult(false, 'Invalid or missing token.');
            return;
        }

        $hash = hash('sha256', $token);
        $stmt = $this->db->prepare("SELECT id, user_id, expires_at, consumed_at FROM email_verifications WHERE token_hash = ? LIMIT 1");
        $stmt->execute([$hash]);
        $row = $stmt->fetch();

        if (!$row) {
            $this->renderVerificationResult(false, 'This verification link is not recognised.');
            return;
        }
        if ($row['consumed_at'] !== null) {
            $this->renderVerificationResult(true, 'Your email is already verified.');
            return;
        }
        if (strtotime($row['expires_at']) < time()) {
            $this->renderVerificationResult(false, 'This verification link has expired. Please request a new one from your account page.');
            return;
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE users SET email_verified_at = UTC_TIMESTAMP() WHERE id = ?")
                ->execute([$row['user_id']]);
            $this->db->prepare("UPDATE email_verifications SET consumed_at = UTC_TIMESTAMP() WHERE id = ?")
                ->execute([$row['id']]);
            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log('Email verification commit failed: ' . $e->getMessage());
            $this->renderVerificationResult(false, 'Something went wrong. Please try again in a moment.');
            return;
        }

        $this->renderVerificationResult(true, 'Your email is verified. Thanks!');
    }

    public function resendVerification(): void {
        Auth::requireLogin();
        $userId = Auth::userId();

        $stmt = $this->db->prepare("SELECT username, email, email_verified_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if ($user && $user['email_verified_at'] === null) {
            $this->sendVerificationEmail((int)$userId, (string)$user['email'], (string)$user['username']);
        }

        header('Location: /account?verify=sent');
        exit;
    }

    private function renderVerificationResult(bool $success, string $message): void {
        $title   = $success ? 'Email verified' : 'Verification failed';
        $status  = $success ? 'success' : 'error';
        require __DIR__ . '/../views/auth/verify_result.php';
    }

    private function validateRegistration(string $username, string $email, string $password): ?string {
        if ($username === '' || $email === '' || $password === '') {
            return 'Please fill in all required fields.';
        }
        if (mb_strlen($username) < 3 || mb_strlen($username) > 32) {
            return 'Username must be between 3 and 32 characters.';
        }
        if (!preg_match('/^[A-Za-z0-9_.-]+$/', $username)) {
            return 'Username may only contain letters, numbers, and _ . -';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254) {
            return 'Please enter a valid email address.';
        }

        $pwError = $this->validatePasswordStrength($password);
        if ($pwError !== null) return $pwError;

        return null;
    }

    private function validatePasswordStrength(string $password): ?string {
        if (mb_strlen($password) < 8) {
            return 'Password must be at least 8 characters long.';
        }
        if (mb_strlen($password) > 200) {
            return 'Password is too long.';
        }

        $classes = 0;
        if (preg_match('/[a-z]/', $password)) $classes++;
        if (preg_match('/[A-Z]/', $password)) $classes++;
        if (preg_match('/\d/', $password))    $classes++;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $classes++;

        if ($classes < 3) {
            return 'Password must include at least three of: lowercase, uppercase, number, symbol.';
        }

        $common = ['password', 'password1', 'password123', '12345678', '123456789', '1234567890', 'qwerty123', 'letmein1', 'welcome1', 'iloveyou', 'admin123', 'changeme'];
        if (in_array(strtolower($password), $common, true)) {
            return 'That password is too common. Please choose a different one.';
        }

        return null;
    }

    public function showForgotPassword(): void {
        require __DIR__ . '/../views/auth/forgot_password.php';
    }

    public function forgotPassword(): void {
        $email = trim((string)($_POST['email'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
            require __DIR__ . '/../views/auth/forgot_password.php';
            return;
        }

        $stmt = $this->db->prepare("SELECT id, username FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Always show success to prevent email enumeration
        $success = 'If that email is registered you will receive a reset link shortly.';

        if ($user) {
            try {
                $token   = bin2hex(random_bytes(32));
                $hash    = hash('sha256', $token);
                $expires = gmdate('Y-m-d H:i:s', time() + 3600);

                $this->db->prepare("DELETE FROM password_resets WHERE user_id = ? AND consumed_at IS NULL")
                    ->execute([$user['id']]);

                $this->db->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)")
                    ->execute([$user['id'], $hash, $expires]);

                $base = rtrim((string)Env::get('APP_URL', ''), '/');
                if ($base === '') {
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $base   = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
                }
                $link     = $base . '/reset-password?token=' . urlencode($token);
                $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
                $safeName = htmlspecialchars((string)$user['username'], ENT_QUOTES, 'UTF-8');

                $html = "<p>Hi $safeName,</p>"
                      . "<p>We received a request to reset your password. Click the link below (valid for 1 hour):</p>"
                      . "<p><a href=\"$safeLink\">Reset my password</a></p>"
                      . "<p>If you didn't request this, you can ignore this email.</p>";
                $text = "Hi {$user['username']},\n\nReset your password here (valid 1 hour):\n$link\n\nIf you didn't request this, ignore this email.";

                Mailer::send($email, 'Reset your password', $html, $text);
            } catch (Throwable $e) {
                error_log('Password reset send failed: ' . $e->getMessage());
            }
        }

        require __DIR__ . '/../views/auth/forgot_password.php';
    }

    public function showResetPassword(): void {
        $token = (string)($_GET['token'] ?? '');
        if ($token === '' || strlen($token) !== 64 || !ctype_xdigit($token)) {
            $error = 'Invalid or missing reset token.';
        }
        require __DIR__ . '/../views/auth/reset_password.php';
    }

    public function resetPassword(): void {
        $token    = (string)($_POST['token'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $confirm  = (string)($_POST['confirm_password'] ?? '');

        if ($token === '' || strlen($token) !== 64 || !ctype_xdigit($token)) {
            $error = 'Invalid reset token.';
            require __DIR__ . '/../views/auth/reset_password.php';
            return;
        }

        if ($password !== $confirm) {
            $error = 'Passwords do not match.';
            require __DIR__ . '/../views/auth/reset_password.php';
            return;
        }

        $pwError = $this->validatePasswordStrength($password);
        if ($pwError !== null) {
            $error = $pwError;
            require __DIR__ . '/../views/auth/reset_password.php';
            return;
        }

        $hash = hash('sha256', $token);
        $stmt = $this->db->prepare("SELECT id, user_id, expires_at, consumed_at FROM password_resets WHERE token_hash = ? LIMIT 1");
        $stmt->execute([$hash]);
        $row = $stmt->fetch();

        if (!$row || $row['consumed_at'] !== null || strtotime($row['expires_at']) < time()) {
            $error = 'This reset link is invalid or has expired. Please request a new one.';
            require __DIR__ . '/../views/auth/reset_password.php';
            return;
        }

        $this->db->beginTransaction();
        try {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$newHash, $row['user_id']]);
            $this->db->prepare("UPDATE password_resets SET consumed_at = UTC_TIMESTAMP() WHERE id = ?")->execute([$row['id']]);
            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log('Password reset commit failed: ' . $e->getMessage());
            $error = 'Something went wrong. Please try again.';
            require __DIR__ . '/../views/auth/reset_password.php';
            return;
        }

        $success = 'Your password has been reset. You can now log in.';
        require __DIR__ . '/../views/auth/login.php';
    }

    public function logout(): void {
        Auth::logout();
        header('Location: /');
        exit;
    }
}
