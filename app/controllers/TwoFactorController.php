<?php

/**
 * The second step of an admin's sign-in (audit S5): after the password, the
 * 6-digit code from an authenticator app (Totp), or one of the account's
 * single-use recovery codes. An admin without two-step sign-in sets it up
 * here first, so nobody reaches /admin with the password alone.
 *
 * AuthController::login() leaves a correct admin password "pending" in the
 * session instead of signing in. It lasts PENDING_SECONDS and allows
 * TRIES_PER_SIGN_IN wrong codes; then the password is asked again. Across
 * sign-ins, FAILURES_PER_ACCOUNT wrong codes within 15 minutes pause the
 * account's admin sign-in and email the shop: whoever is typing them knows
 * the password.
 *
 * Lost phone and recovery codes: php database/reset_admin_2fa.php.
 */
class TwoFactorController extends Controller {
    private const PENDING_SECONDS = 600;
    private const TRIES_PER_SIGN_IN = 5;
    private const FAILURES_PER_ACCOUNT = 10;
    private const RECOVERY_CODES = 10;
    // No 0/o or 1/l/i, so a code copied from paper reads back unambiguously.
    private const RECOVERY_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    /** Called once an admin's password is right; the code is asked next. */
    public static function begin(int $userId): void {
        session_regenerate_id(true);
        $_SESSION['two_factor'] = ['user_id' => $userId, 'since' => time(), 'tries' => 0];
    }

    // GET /login/two-factor
    public function show(): void {
        $admin = $this->pendingAdmin();
        if ($admin['totp_enabled_at'] === null) $this->redirect('/login/two-factor/setup');
        $this->render('auth/two_factor');
    }

    // POST /login/two-factor
    public function verify(): void {
        $admin = $this->pendingAdmin();
        if ($admin['totp_enabled_at'] === null) $this->redirect('/login/two-factor/setup');
        $userId = (int)$admin['id'];
        if ($this->failures($userId) >= self::FAILURES_PER_ACCOUNT) {
            $this->startOver(I18n::t('auth.2fa.paused'));
            return;
        }

        $input = (string)($_POST['code'] ?? '');
        $lastStep = $admin['totp_last_step'] !== null ? (int)$admin['totp_last_step'] : null;
        $step = Totp::verify((string)$admin['totp_secret'], preg_replace('/\s+/', '', $input), $lastStep);
        if ($step !== null) {
            // Only if no other request used this code first.
            $upd = $this->db->prepare("UPDATE users SET totp_last_step = ? WHERE id = ? AND (totp_last_step IS NULL OR totp_last_step < ?)");
            $upd->execute([$step, $userId, $step]);
            if ($upd->rowCount() === 1) {
                $this->finish($userId);
                $this->redirect('/admin');
            }
        } elseif (($left = $this->useRecoveryCode($userId, $input)) !== null) {
            $this->finish($userId);
            $this->render('auth/two_factor_codes', ['codesLeft' => $left]);
            return;
        }
        $this->failed($admin, false);
    }

    // GET /login/two-factor/setup
    public function showSetup(): void {
        $admin = $this->pendingAdmin();
        if ($admin['totp_enabled_at'] !== null) $this->redirect('/login/two-factor');
        $this->renderSetup($admin);
    }

    // POST /login/two-factor/setup
    public function completeSetup(): void {
        $admin = $this->pendingAdmin();
        if ($admin['totp_enabled_at'] !== null) $this->redirect('/login/two-factor');
        $userId = (int)$admin['id'];
        if ($this->failures($userId) >= self::FAILURES_PER_ACCOUNT) {
            $this->startOver(I18n::t('auth.2fa.paused'));
            return;
        }

        // The secret shown on the setup page; a code proves the app has it.
        $secret = (string)($_SESSION['two_factor']['secret'] ?? '');
        $step = $secret === '' ? null : Totp::verify($secret, preg_replace('/\s+/', '', (string)($_POST['code'] ?? '')));
        if ($step === null) {
            $this->failed($admin, true);
            return;
        }

        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODES; $i++) {
            $code = '';
            for ($j = 0; $j < 10; $j++) {
                $code .= self::RECOVERY_ALPHABET[random_int(0, strlen(self::RECOVERY_ALPHABET) - 1)];
            }
            $codes[] = substr($code, 0, 5) . '-' . substr($code, 5);
        }
        $this->db->beginTransaction();
        try {
            $upd = $this->db->prepare("UPDATE users SET totp_secret = ?, totp_enabled_at = UTC_TIMESTAMP(), totp_last_step = ? WHERE id = ? AND totp_enabled_at IS NULL");
            $upd->execute([$secret, $step, $userId]);
            if ($upd->rowCount() !== 1) {
                // Set up meanwhile in another tab: use that one.
                $this->db->rollBack();
                $this->redirect('/login/two-factor');
            }
            $this->db->prepare("DELETE FROM admin_recovery_codes WHERE user_id = ?")->execute([$userId]);
            $ins = $this->db->prepare("INSERT INTO admin_recovery_codes (user_id, code_hash) VALUES (?, ?)");
            foreach ($codes as $code) {
                $ins->execute([$userId, self::recoveryHash($code)]);
            }
            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log('Two-factor setup failed: ' . $e->getMessage());
            $this->renderSetup($admin, I18n::t('auth.2fa.setup_failed'));
            return;
        }

        $this->finish($userId);
        $this->render('auth/two_factor_codes', ['codes' => $codes]);
    }

    /**
     * The admin half-way through signing in. Anyone else, or one who took
     * longer than PENDING_SECONDS, is sent back to the password.
     */
    private function pendingAdmin(): array {
        $pending = $_SESSION['two_factor'] ?? null;
        if (!is_array($pending) || empty($pending['user_id'])) {
            $this->redirect('/login');
        }
        if (time() - (int)($pending['since'] ?? 0) > self::PENDING_SECONDS) {
            $this->startOver(I18n::t('auth.2fa.expired'));
            exit;
        }
        // The role is read again: an account demoted meanwhile gets no code step.
        $stmt = $this->db->prepare("SELECT id, email, totp_secret, totp_enabled_at, totp_last_step FROM users WHERE id = ? AND role = 'admin'");
        $stmt->execute([(int)$pending['user_id']]);
        $admin = $stmt->fetch();
        if (!$admin) {
            unset($_SESSION['two_factor']);
            $this->redirect('/login');
        }
        return $admin;
    }

    /** A wrong code: count it, then ask again, or start over after too many. */
    private function failed(array $admin, bool $setup): void {
        $userId = (int)$admin['id'];
        $this->db->prepare("INSERT INTO login_attempts (ip_hash, identifier) VALUES (?, ?)")->execute([ClientIp::hash(), '2fa:' . $userId]);
        $_SESSION['two_factor']['tries'] = (int)($_SESSION['two_factor']['tries'] ?? 0) + 1;

        $failures = $this->failures($userId);
        if ($failures >= self::FAILURES_PER_ACCOUNT) {
            if ($failures === self::FAILURES_PER_ACCOUNT) {
                PaymentAlert::adminSignInPaused($this->db, (string)$admin['email'], $failures);
            }
            $this->startOver(I18n::t('auth.2fa.paused'));
        } elseif ($_SESSION['two_factor']['tries'] >= self::TRIES_PER_SIGN_IN) {
            $this->startOver(I18n::t('auth.2fa.too_many'));
        } elseif ($setup) {
            $this->renderSetup($admin, I18n::t('auth.2fa.wrong'));
        } else {
            $this->render('auth/two_factor', ['error' => I18n::t('auth.2fa.wrong')]);
        }
    }

    /** Wrong codes for this account in the last 15 minutes, from any sign-in. */
    private function failures(int $userId): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND created_at > NOW() - INTERVAL 15 MINUTE");
        $stmt->execute(['2fa:' . $userId]);
        return (int)$stmt->fetchColumn();
    }

    /** Use up one of the account's recovery codes; how many are left, or null if $input isn't one. */
    private function useRecoveryCode(int $userId, string $input): ?int {
        if (strlen(preg_replace('/[^a-z0-9]/i', '', $input)) !== 10) {
            return null;
        }
        $upd = $this->db->prepare("UPDATE admin_recovery_codes SET used_at = UTC_TIMESTAMP() WHERE user_id = ? AND code_hash = ? AND used_at IS NULL");
        $upd->execute([$userId, self::recoveryHash($input)]);
        if ($upd->rowCount() !== 1) {
            return null;
        }
        $left = $this->db->prepare("SELECT COUNT(*) FROM admin_recovery_codes WHERE user_id = ? AND used_at IS NULL");
        $left->execute([$userId]);
        return (int)$left->fetchColumn();
    }

    /** Codes are random enough (about 50 bits) for a plain SHA-256. */
    private static function recoveryHash(string $code): string {
        return hash('sha256', strtolower(preg_replace('/[^a-z0-9]/i', '', $code)));
    }

    private function renderSetup(array $admin, ?string $error = null): void {
        // One secret per sign-in, kept in the session until a code proves the app has it.
        $secret = $_SESSION['two_factor']['secret'] ??= Totp::newSecret();
        $this->render('auth/two_factor_setup', [
            'secret' => trim(chunk_split($secret, 4, ' ')),
            'uri'    => Totp::uri($secret, (string)$admin['email'], Business::name() ?: 'Costaspressjr'),
            'error'  => $error,
        ]);
    }

    private function finish(int $userId): void {
        unset($_SESSION['two_factor']);
        Auth::login($userId, 'admin');
    }

    /** Back to the password, with $message on the sign-in page. */
    private function startOver(string $message): void {
        unset($_SESSION['two_factor']);
        $this->render('auth/login', ['error' => $message]);
    }

    private function redirect(string $to): never {
        header('Location: ' . $to);
        exit;
    }
}
