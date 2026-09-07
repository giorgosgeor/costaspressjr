<?php

class Auth {
    public static function check(): bool {
        return isset($_SESSION['user_id']);
    }

    public static function userId(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    public static function role(): ?string {
        return $_SESSION['user_role'] ?? null;
    }

    public static function isAdmin(): bool {
        return self::role() === 'admin';
    }

    public static function isCustomer(): bool {
        return self::check() && self::role() === 'customer';
    }

    public static function login(int $userId, string $role = 'customer'): void {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = $role;
    }

    public static function logout(): void {
        // Clear in-memory data, expire the cookie, then destroy the store —
        // session_destroy() alone leaves the cookie (and with it the session id)
        // alive in the browser.
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    /**
     * The user id shopping flows should operate as: the logged-in account, or
     * the session's GUEST user row (role 'guest', unroutable placeholder email,
     * unusable password) created on demand. Guests browse, design, add to cart
     * and check out; they can never log in as the guest row, and
     * AuthController::mergeGuestCart folds the cart into a real account on
     * login/registration.
     *
     * @param bool $createGuest create the guest row if none exists yet. Keep
     *                          false on read paths so crawlers don't mint rows.
     */
    public static function effectiveUserId(PDO $db, bool $createGuest = false): ?int {
        if (self::check()) {
            return self::userId();
        }

        $gid = (int)($_SESSION['guest_user_id'] ?? 0);
        if ($gid > 0) {
            return $gid;
        }
        if (!$createGuest) {
            return null;
        }

        // 20-hex-char token: collisions are practically impossible, but retry
        // anyway since users.email is UNIQUE.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $token = bin2hex(random_bytes(10));
            try {
                $stmt = $db->prepare(
                    "INSERT INTO users (username, email, phone, password_hash, role) VALUES (?, ?, '', ?, 'guest')"
                );
                // .invalid is an RFC 2606 reserved TLD — this address can never
                // receive mail. The hash is of random bytes nobody knows, so the
                // row can never be logged into.
                $stmt->execute([
                    'guest_' . $token,
                    'guest_' . $token . '@guest.invalid',
                    password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                ]);
                $gid = (int)$db->lastInsertId();
                $_SESSION['guest_user_id'] = $gid;
                return $gid;
            } catch (PDOException $e) {
                error_log('Guest user creation failed (attempt ' . ($attempt + 1) . '): ' . $e->getMessage());
            }
        }
        return null;
    }

    /**
     * Require login - redirect to login page if not authenticated
     */
    public static function requireLogin(): void {
        if (!self::check()) {
            header('Location: /login');
            exit;
        }
    }

    /**
     * Require admin role - redirect if not admin
     */
    public static function requireAdmin(): void {
        self::requireLogin();
        if (!self::isAdmin()) {
            header('Location: /');
            exit;
        }
    }
}
