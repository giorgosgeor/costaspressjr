<?php

/**
 * What every admin controller shares: the admin-only gate and reading the
 * record id from the URL (/admin/products/edit/12 → 12).
 */
abstract class AdminController extends Controller {
    protected function requireAdmin(): void {
        Auth::requireAdmin();
    }

    // ==================== HELPER METHODS ====================

    protected function getIdFromUrl(): int {
        $uri = $_SERVER['REQUEST_URI'];
        $parts = explode('/', trim($uri, '/'));
        return (int) end($parts);
    }
}
