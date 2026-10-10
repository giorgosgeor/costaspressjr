<?php
/**
 * Front controller. Every request that isn't a real file under public/ comes
 * here (see public/.htaccess): load the app, apply the error and session
 * policy, check the CSRF token, and hand the request to the router. The URL
 * table is app/routes.php.
 */
require __DIR__ . '/../app/bootstrap.php';

$isProd = Env::get('APP_ENV', 'production') === 'production';

// Development shows PHP notices on the page — but never inside a JSON
// response, where one stray "Deprecated: …" line makes the whole reply
// unreadable (that is how PHP 8.5's curl_close() notice silently broke the
// checkout). They are still logged either way.
$reqPath   = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
    || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')
    || str_starts_with($reqPath, '/api/')
    || $reqPath === '/stripe/webhook';
ini_set('display_errors', ($isProd || $wantsJson) ? '0' : '1');
ini_set('display_startup_errors', $isProd ? '0' : '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if ($isProd) {
    ErrorHandler::register();
    if ($problems = ProductionCheck::problems()) {
        throw new RuntimeException('Production config incomplete: ' . implode('; ', $problems));
    }
}

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $isProd,
    'httponly' => true,
    'samesite' => 'Lax',
]);
// Only session ids this server issued: one chosen by someone else (fixation)
// is replaced by a new id instead of being adopted.
ini_set('session.use_strict_mode', '1');
session_start();

Asset::setPublicRoot(__DIR__);
I18n::init();
// Stripe's webhook can't carry our CSRF token; it is authenticated by its
// signature instead (Stripe::verifyWebhook).
if ($reqPath !== '/stripe/webhook') {
    Csrf::validateRequest();
}

$db = require __DIR__ . '/../app/config/database.php';
Auth::refresh($db);

$router = new Router();
require __DIR__ . '/../app/routes.php';
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
