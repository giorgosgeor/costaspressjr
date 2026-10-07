<?php
/**
 * Loaded first by public/index.php and by the command-line scripts in
 * database/: reads .env and makes every class loadable on first use.
 *
 * Where classes live (one class per file, file named after the class):
 *   app/core/               the small framework — routing, sessions, CSRF,
 *                           translations, views, mail, uploads
 *   app/services/           the shop's own logic — pricing, Stripe, orders,
 *                           pickup points, the shop assistant
 *   app/models/             database access for carts and saved designs
 *   app/controllers/        one controller per area of the shop
 *   app/controllers/admin/  the admin panel's controllers
 *
 * Templates are not classes: they live in views/ and are rendered with
 * View::render() (controllers call $this->render()).
 */

require __DIR__ . '/core/Env.php';
Env::load(__DIR__ . '/../.env');

spl_autoload_register(static function (string $class): void {
    foreach (['core', 'services', 'models', 'controllers', 'controllers/admin'] as $dir) {
        $file = __DIR__ . "/$dir/$class.php";
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

require __DIR__ . '/helpers.php';
