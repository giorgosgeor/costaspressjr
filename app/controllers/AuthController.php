<?php
// app/controllers/AuthController.php

class AuthController extends Controller {
    private const MAX_ATTEMPTS_PER_IP   = 10;
    private const MAX_ATTEMPTS_PER_USER = 5;
    private const WINDOW_SECONDS        = 900;

    private const VERIFICATION_TTL_HOURS = 24;

    public function showLogin(): void {
        $redirect = $this->safeRedirect($_GET['redirect'] ?? '');
        $this->render('auth/login', get_defined_vars());
    }

    public function login(): void {
        $identifier = trim((string)($_POST['identifier'] ?? ''));
        $password   = (string)($_POST['password'] ?? '');
        $redirect   = $this->safeRedirect($_POST['redirect'] ?? '');

        if ($identifier === '' || $password === '') {
            $error = 'Invalid credentials';
            $this->render('auth/login', get_defined_vars());
            return;
        }

        if ($this->isRateLimited($identifier)) {
            $error = 'Too many attempts. Please try again in a few minutes.';
            $this->render('auth/login', get_defined_vars());
            return;
        }

        $stmt = $this->db->prepare("SELECT id, password_hash, role FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->recordFailedAttempt($identifier);
            $error = 'Invalid credentials';
            $this->render('auth/login', get_defined_vars());
            return;
        }

        $this->clearAttempts($identifier);

        Auth::login($user['id'], $user['role']);
        // Ensure user has a cart, then pull in anything they added as a guest
        $cartModel = new \Cart($this->db);
        $cartModel->getOrCreateCartId($user['id']);
        $this->mergeGuestCart((int)$user['id']);

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

    /**
     * login_attempts.created_at is stamped by MySQL's clock, in the server's
     * time zone (Asia/Nicosia here), so windows are measured on that same
     * clock. Comparing it with a PHP gmdate() string stretched a 15-minute
     * lockout to over three hours on a UTC+3 server.
     */
    private const SINCE_WINDOW = 'NOW() - INTERVAL ' . self::WINDOW_SECONDS . ' SECOND';

    private function isRateLimited(string $identifier): bool {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_hash = ? AND created_at > " . self::SINCE_WINDOW);
            $stmt->execute([$this->ipHash()]);
            if ((int)$stmt->fetchColumn() >= self::MAX_ATTEMPTS_PER_IP) return true;

            $stmt = $this->db->prepare("SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND created_at > " . self::SINCE_WINDOW);
            $stmt->execute([mb_strtolower($identifier)]);
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
                $this->db->exec("DELETE FROM login_attempts WHERE created_at < NOW() - INTERVAL " . (self::WINDOW_SECONDS * 4) . " SECOND");
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

    /**
     * A guest who built a cart and then logs in (or registers) keeps that
     * cart: move the guest cart's items into the account's cart and drop the
     * session's guest binding. cart_item_uploads reference cart_item_id, so
     * they travel with the rows automatically.
     */
    private function mergeGuestCart(int $userId): void {
        $guestId = (int)($_SESSION['guest_user_id'] ?? 0);
        unset($_SESSION['guest_user_id']);
        if ($guestId <= 0 || $guestId === $userId) return;

        try {
            $stmt = $this->db->prepare("SELECT id FROM carts WHERE user_id = ? LIMIT 1");
            $stmt->execute([$guestId]);
            $guestCart = (int)$stmt->fetchColumn();
            if (!$guestCart) return;

            $cartModel = new \Cart($this->db);
            $userCart = $cartModel->getOrCreateCartId($userId);

            $this->db->prepare("UPDATE cart_items SET cart_id = ? WHERE cart_id = ?")
                ->execute([$userCart, $guestCart]);

            $stmt = $this->db->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE cart_id = ?");
            $stmt->execute([$userCart]);
            $_SESSION['cart_count'] = (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('Guest cart merge failed: ' . $e->getMessage());
        }
    }

    /**
     * Generic throttle for endpoints that send email (forgot-password, resend
     * verification). Reuses login_attempts with a namespaced identifier so no
     * new table is needed. Limits: 3 sends per identifier and 10 per IP, per
     * rate window. Returns true when the caller should be blocked.
     */
    private function isMailRateLimited(string $namespace, string $identifier): bool {
        // login_attempts.identifier is varchar(191) — truncate so a long email
        // can't make the INSERT throw under STRICT_TRANS_TABLES.
        $key = mb_substr($namespace . ':' . mb_strtolower($identifier), 0, 191);
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_hash = ? AND identifier LIKE ? AND created_at > " . self::SINCE_WINDOW);
            $stmt->execute([$this->ipHash(), $namespace . ':%']);
            if ((int)$stmt->fetchColumn() >= 10) return true;

            $stmt = $this->db->prepare("SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND created_at > " . self::SINCE_WINDOW);
            $stmt->execute([$key]);
            if ((int)$stmt->fetchColumn() >= 3) return true;

            $this->db->prepare("INSERT INTO login_attempts (ip_hash, identifier) VALUES (?, ?)")
                ->execute([$this->ipHash(), $key]);
            return false;
        } catch (PDOException $e) {
            error_log('Mail rate-limit check failed: ' . $e->getMessage());
            return false;
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
        $this->render('auth/register', get_defined_vars());
    }

    public function register(): void {
        $username = trim((string)($_POST['username'] ?? ''));
        $email    = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $phone    = trim((string)($_POST['phone'] ?? ''));

        $error = $this->validateRegistration($username, $email, $password);
        if ($error !== null) {
            $this->render('auth/register', get_defined_vars());
            return;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("INSERT INTO users (username, email, phone, password_hash) VALUES (?, ?, ?, ?)");
        try {
            $stmt->execute([$username, $email, $phone, $hash]);
        } catch (PDOException $e) {
            $error = 'Email, username, or phone already registered';
            $this->render('auth/register', get_defined_vars());
            return;
        }

        $userId = (int)$this->db->lastInsertId();
        Auth::login($userId, 'customer');

        $cartModel = new \Cart($this->db);
        $cartModel->getOrCreateCartId($userId);
        $this->mergeGuestCart($userId);

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
        // expires_at is written with gmdate(), so it is read back as UTC. Plain
        // strtotime() would read it in PHP's zone and, on a server ahead of
        // UTC, expire the link hours early.
        if (strtotime($row['expires_at'] . ' UTC') < time()) {
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

        if ($user && $user['email_verified_at'] === null
            && !$this->isMailRateLimited('resend', (string)$user['email'])) {
            $this->sendVerificationEmail((int)$userId, (string)$user['email'], (string)$user['username']);
        }

        header('Location: /account?verify=sent');
        exit;
    }

    private function renderVerificationResult(bool $success, string $message): void {
        $title   = $success ? 'Email verified' : 'Verification failed';
        $status  = $success ? 'success' : 'error';
        $this->render('auth/verify_result', get_defined_vars());
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
        $this->render('auth/forgot_password', get_defined_vars());
    }

    public function forgotPassword(): void {
        $email = trim((string)($_POST['email'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
            $this->render('auth/forgot_password', get_defined_vars());
            return;
        }

        $stmt = $this->db->prepare("SELECT id, username FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Always show success to prevent email enumeration
        $success = 'If that email is registered you will receive a reset link shortly.';

        // Throttle BEFORE sending: without this the endpoint is an anonymous
        // mail cannon (bombing a victim's inbox / burning SMTP quota). The
        // response stays identical either way, so nothing is leaked.
        if ($this->isMailRateLimited('forgot', $email)) {
            $this->render('auth/forgot_password', get_defined_vars());
            return;
        }

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

        $this->render('auth/forgot_password', get_defined_vars());
    }

    public function showResetPassword(): void {
        $token = (string)($_GET['token'] ?? '');
        if ($token === '' || strlen($token) !== 64 || !ctype_xdigit($token)) {
            $error = 'Invalid or missing reset token.';
        }
        $this->render('auth/reset_password', get_defined_vars());
    }

    public function resetPassword(): void {
        $token    = (string)($_POST['token'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $confirm  = (string)($_POST['confirm_password'] ?? '');

        if ($token === '' || strlen($token) !== 64 || !ctype_xdigit($token)) {
            $error = 'Invalid reset token.';
            $this->render('auth/reset_password', get_defined_vars());
            return;
        }

        if ($password !== $confirm) {
            $error = 'Passwords do not match.';
            $this->render('auth/reset_password', get_defined_vars());
            return;
        }

        $pwError = $this->validatePasswordStrength($password);
        if ($pwError !== null) {
            $error = $pwError;
            $this->render('auth/reset_password', get_defined_vars());
            return;
        }

        $hash = hash('sha256', $token);
        $stmt = $this->db->prepare("SELECT id, user_id, expires_at, consumed_at FROM password_resets WHERE token_hash = ? LIMIT 1");
        $stmt->execute([$hash]);
        $row = $stmt->fetch();

        // expires_at is UTC (gmdate) — see verifyEmail(). Without the zone, a
        // one-hour reset link was already expired on a UTC+2 server.
        if (!$row || $row['consumed_at'] !== null || strtotime($row['expires_at'] . ' UTC') < time()) {
            $error = 'This reset link is invalid or has expired. Please request a new one.';
            $this->render('auth/reset_password', get_defined_vars());
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
            $this->render('auth/reset_password', get_defined_vars());
            return;
        }

        $success = 'Your password has been reset. You can now log in.';
        $this->render('auth/login', get_defined_vars());
    }

    public function logout(): void {
        Auth::logout();
        header('Location: /');
        exit;
    }
}
