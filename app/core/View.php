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

    /**
     * Data for a page's script, as a JSON block the browser never executes:
     *   <?= View::json('product-data', ['variants' => $variants]) ?>
     * and in the script: JSON.parse(document.getElementById('product-data').textContent).
     * The flags escape < > & ' " so no value can close the tag early.
     */
    public static function json(string $id, mixed $data): string {
        return '<script type="application/json" id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">'
            . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
            . '</script>';
    }

    /** A page script tag with a cache-busting version: <?= View::script('/js/pages/cart.js') ?> */
    public static function script(string $path): string {
        return '<script src="' . htmlspecialchars(Asset::url($path), ENT_QUOTES, 'UTF-8') . '"></script>';
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
