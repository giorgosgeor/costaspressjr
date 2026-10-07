<?php

/**
 * Renders the PHP templates in views/.
 *
 * A template sees only the variables it is handed — not the controller, not
 * the controller's other locals — so what a page needs is visible at the
 * call site: View::render('shop/product', ['product' => $p]).
 *
 * Templates stay presentation-only: echo, if, foreach, includes of layouts
 * and partials. Queries and business rules belong in controllers, models and
 * services; page CSS and JS belong in public/css and public/js.
 */
class View {
    public static function path(string $template): string {
        return __DIR__ . '/../../views/' . $template . '.php';
    }

    public static function render(string $template, array $data = []): void {
        $file = self::path($template);
        if (!is_file($file)) {
            throw new RuntimeException("View not found: $template");
        }
        // A static closure: the template gets $data as variables and nothing
        // else in scope (no $this).
        (static function (string $__file, array $__data): void {
            extract($__data, EXTR_SKIP);
            require $__file;
        })($file, $data);
    }
}
