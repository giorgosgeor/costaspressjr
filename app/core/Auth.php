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
        session_destroy();
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
