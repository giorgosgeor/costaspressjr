<?php
// app/controllers/AuthController.php

class AuthController extends Controller {
    private const MAX_ATTEMPTS_PER_IP   = 10;
    private const MAX_ATTEMPTS_PER_USER = 5;
    private const WINDOW_SECONDS        = 900;

    // A bcrypt hash at PHP 8.5's default cost (12) of a random password
    // nobody knows: login() checks it when the account doesn't exist.
    private const DUMMY_HASH = '$2y$12$mLM0WsYjZStSmABCVPr7UeqFU4loeoUJJn.CO8ZSQGX5LQtw/ItnG';

    // A confirmation link works for an hour, like a password-reset link.
    // Resend issues a fresh one; earlier unexpired links keep working.
    private const VERIFICATION_TTL_HOURS = 1;

    public function showLogin(): void {
        $redirect = $this->safeRedirect($_GET['redirect'] ?? '');
        $this->render('auth/login', ['redirect' => $redirect]);
    }

    public function login(): void {
        $identifier = trim((string)($_POST['identifier'] ?? ''));
        $password   = (string)($_POST['password'] ?? '');
        $redirect   = $this->safeRedirect($_POST['redirect'] ?? '');

        if ($identifier === '' || $password === '') {
            $error = 'Invalid credentials';
            $this->render('auth/login', ['redirect' => $redirect, 'error' => $error]);
            return;
        }

        if ($this->isRateLimited($identifier)) {
            $error = 'Too many attempts. Please try again in a few minutes.';
            $this->render('auth/login', ['redirect' => $redirect, 'error' => $error]);
            return;
        }

        $stmt = $this->db->prepare("SELECT id, email, username, password_hash, role FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        // An unknown account is checked against a dummy hash, so the reply
        // takes as long as for a real one and doesn't reveal which exist (S6).
        if (!password_verify($password, $user ? $user['password_hash'] : self::DUMMY_HASH) || !$user) {
            $this->recordFailedAttempt($identifier);
            $error = 'Invalid credentials';
            $this->render('auth/login', ['redirect' => $redirect, 'error' => $error]);
            return;
        }

        // Older hashes (a lower cost) move to the current default, so every
        // account's check takes the same time.
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }

        $this->clearAttempts([$user['email'], $user['username']]);

        // An admin also needs the code from an authenticator app (audit S5);
        // TwoFactorController signs them in once that is right.
        if ($user['role'] === 'admin') {
            TwoFactorController::begin((int)$user['id']);
            header('Location: /login/two-factor');
            exit;
        }

        Auth::login($user['id'], $user['role']);
        // Ensure user has a cart, then pull in anything they added as a guest
        $cartModel = new \Cart($this->db);
        $cartModel->getOrCreateCartId($user['id']);
        $this->mergeGuestCart((int)$user['id']);

        if ($redirect) {
            header('Location: ' . $redirect);
        } else {
            header('Location: /');
        }
        exit;
    }

    private function ipHash(): string {
        // Hashing the IP means operators don't store raw PII in the attempts
        // table. The visitor's own IP, also behind a proxy (ClientIp).
        return ClientIp::hash();
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

    /**
     * After a successful sign-in, forget the failed attempts against this
     * account (typed as its email or its username), so earlier typos don't
     * count against its next sign-in. Nothing more. Never rows matched by IP:
     * signing in to one's own account used to wipe that IP's attempts against
     * every other account too, allowing unlimited guessing. And never the
     * forgot:/resend: or contact throttles that share this table.
     */
    private function clearAttempts(array $identifiers): void {
        $keys = array_values(array_unique(array_filter(
            array_map(fn($s) => mb_strtolower(trim((string)$s)), $identifiers),
            fn($s) => $s !== ''
        )));
        if (!$keys) return;
        try {
            $in = implode(',', array_fill(0, count($keys), '?'));
            $stmt = $this->db->prepare("DELETE FROM login_attempts WHERE identifier IN ($in) AND identifier <> 'contact' AND identifier NOT LIKE '%:%'");
            $stmt->execute($keys);
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
        // Browsers read "\" as "/", so "/\evil.example" is "//evil.example".
        if (str_contains($url, '\\')) return '';
        // Control characters and anything else outside printable ASCII (browsers
        // drop tabs and newlines, so "/\t/evil.example" is "//evil.example" too),
        // and colons that look like protocol injection.
        if (preg_match('/[^\x20-\x7E]|:/', $url)) return '';
        return $url;
    }

    public function showRegister(): void {
        $this->render('auth/register');
    }

    public function register(): void {
        $username = trim((string)($_POST['username'] ?? ''));
        $email    = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $phone    = trim((string)($_POST['phone'] ?? ''));

        $error = $this->validateRegistration($username, $email, $password);
        // Each registration emails the address given, so it is throttled like
        // "forgot password": otherwise the form sends mail to anyone, and
        // can be used to check addresses in bulk (audit S6).
        if ($error === null && $this->isMailRateLimited('register', $email)) {
            $error = 'Too many attempts. Please try again in a few minutes.';
        }
        if ($error !== null) {
            $this->render('auth/register', ['username' => $username, 'email' => $email, 'phone' => $phone, 'error' => $error]);
            return;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("INSERT INTO users (username, email, phone, password_hash) VALUES (?, ?, ?, ?)");
        try {
            $stmt->execute([$username, $email, $phone, $hash]);
        } catch (PDOException $e) {
            $error = 'Email, username, or phone already registered';
            $this->render('auth/register', ['username' => $username, 'email' => $email, 'phone' => $phone, 'error' => $error]);
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

            // Earlier links stay valid until they expire. Each Resend used to
            // cancel the ones before it, so the email the customer actually
            // opened — usually the first in their inbox — said "not
            // recognised". Every link goes to the same address (customers
            // cannot change theirs; if that is ever added, clear this table
            // for the user there). Only expired rows are tidied away.
            $this->db->prepare("DELETE FROM email_verifications WHERE user_id = ? AND consumed_at IS NULL AND expires_at < UTC_TIMESTAMP()")
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
            $validFor = self::VERIFICATION_TTL_HOURS === 1 ? '1 hour' : self::VERIFICATION_TTL_HOURS . ' hours';

            $html = "<p>Hi $safeName,</p>"
                  . "<p>Welcome to Costaspressjr! Please confirm your email address by clicking the link below:</p>"
                  . "<p><a href=\"$safeLink\">Verify my email</a></p>"
                  . "<p>This link expires in " . $validFor . ". If you didn't create an account, you can ignore this message.</p>";
            $text = "Hi $username,\n\nWelcome to Costaspressjr! Confirm your email by opening this link:\n$link\n\nThis link expires in " . $validFor . ".";

            Mailer::later($email, 'Confirm your Costaspressjr email', $html, $text);
        } catch (Throwable $e) {
            error_log('Email verification send failed: ' . $e->getMessage());
        }
    }

    public function verifyEmail(): void {
        $token = (string)($_GET['token'] ?? '');
        if ($token === '' || strlen($token) !== 64 || !ctype_xdigit($token)) {
            $this->renderVerificationResult(false, I18n::t('auth.verify.msg_invalid'));
            return;
        }

        $hash = hash('sha256', $token);
        $stmt = $this->db->prepare("SELECT id, user_id, expires_at, consumed_at FROM email_verifications WHERE token_hash = ? LIMIT 1");
        $stmt->execute([$hash]);
        $row = $stmt->fetch();

        if (!$row) {
            $this->renderVerificationResult(false, I18n::t('auth.verify.msg_unknown'));
            return;
        }
        if ($row['consumed_at'] !== null) {
            $this->renderVerificationResult(true, I18n::t('auth.verify.msg_already'));
            return;
        }
        // expires_at is written with gmdate(), so it is read back as UTC. Plain
        // strtotime() would read it in PHP's zone and, on a server ahead of
        // UTC, expire the link hours early.
        if (strtotime($row['expires_at'] . ' UTC') < time()) {
            $this->renderVerificationResult(false, I18n::t('auth.verify.msg_expired'));
            return;
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE users SET email_verified_at = UTC_TIMESTAMP() WHERE id = ? AND email_verified_at IS NULL")
                ->execute([$row['user_id']]);
            // All of the user's links are spent now, so any other email they
            // open afterwards says "already confirmed" rather than failing.
            $this->db->prepare("UPDATE email_verifications SET consumed_at = UTC_TIMESTAMP() WHERE user_id = ? AND consumed_at IS NULL")
                ->execute([$row['user_id']]);
            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log('Email verification commit failed: ' . $e->getMessage());
            $this->renderVerificationResult(false, I18n::t('auth.verify.msg_error'));
            return;
        }

        $this->renderVerificationResult(true, I18n::t('auth.verify.msg_done'));
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
        CurrentUser::snoozeVerifyBanner();

        // Back to the page the banner was clicked on (it is on every page),
        // not always to /account. Only a path on this site is trusted.
        $back = '';
        $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
        if ($referer !== '' && parse_url($referer, PHP_URL_HOST) === parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
            $query = parse_url($referer, PHP_URL_QUERY);
            $back  = $this->safeRedirect((string)parse_url($referer, PHP_URL_PATH) . ($query ? '?' . $query : ''));
        }
        if (str_starts_with($back, '/verify-email')) {
            $back = '';
        }
        header('Location: ' . ($back !== '' ? $back : '/account'));
        exit;
    }

    private function renderVerificationResult(bool $success, string $message): void {
        $title   = I18n::t($success ? 'auth.verify.title_done' : 'auth.verify.title_failed');
        $status  = $success ? 'success' : 'error';
        // A failed link can be replaced on the spot when the visitor is signed
        // in and still unconfirmed; otherwise they are pointed to sign in.
        $canResend = !$success && CurrentUser::needsEmailVerification($this->db);
        $this->render('auth/verify_result', ['title' => $title, 'status' => $status, 'message' => $message, 'canResend' => $canResend]);
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
        $this->render('auth/forgot_password');
    }

    public function forgotPassword(): void {
        $email = trim((string)($_POST['email'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
            $this->render('auth/forgot_password', ['error' => $error]);
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
            $this->render('auth/forgot_password', ['success' => $success]);
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

                // After the reply: its timing must not show that the address has an account.
                Mailer::later($email, 'Reset your password', $html, $text);
            } catch (Throwable $e) {
                error_log('Password reset send failed: ' . $e->getMessage());
            }
        }

        $this->render('auth/forgot_password', ['success' => $success]);
    }

    public function showResetPassword(): void {
        $token = (string)($_GET['token'] ?? '');
        if ($token === '' || strlen($token) !== 64 || !ctype_xdigit($token)) {
            $error = 'Invalid or missing reset token.';
        }
        $this->render('auth/reset_password', ['token' => $token, 'error' => $error ?? null]);
    }

    public function resetPassword(): void {
        $token    = (string)($_POST['token'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $confirm  = (string)($_POST['confirm_password'] ?? '');

        if ($token === '' || strlen($token) !== 64 || !ctype_xdigit($token)) {
            $error = 'Invalid reset token.';
            $this->render('auth/reset_password', ['token' => $token, 'error' => $error]);
            return;
        }

        if ($password !== $confirm) {
            $error = 'Passwords do not match.';
            $this->render('auth/reset_password', ['token' => $token, 'error' => $error]);
            return;
        }

        $pwError = $this->validatePasswordStrength($password);
        if ($pwError !== null) {
            $error = $pwError;
            $this->render('auth/reset_password', ['token' => $token, 'error' => $error]);
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
            $this->render('auth/reset_password', ['token' => $token, 'error' => $error]);
            return;
        }

        $this->db->beginTransaction();
        try {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            // password_changed_at signs out every session from before the reset
            // (Auth::refresh). PHP's clock, like the sessions' login time.
            $this->db->prepare("UPDATE users SET password_hash = ?, password_changed_at = ? WHERE id = ?")->execute([$newHash, gmdate('Y-m-d H:i:s'), $row['user_id']]);
            $this->db->prepare("UPDATE password_resets SET consumed_at = UTC_TIMESTAMP() WHERE id = ?")->execute([$row['id']]);
            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log('Password reset commit failed: ' . $e->getMessage());
            $error = 'Something went wrong. Please try again.';
            $this->render('auth/reset_password', ['token' => $token, 'error' => $error]);
            return;
        }

        $success = 'Your password has been reset. You can now log in.';
        $this->render('auth/login', ['success' => $success]);
    }

    public function logout(): void {
        Auth::logout();
        header('Location: /');
        exit;
    }
}
