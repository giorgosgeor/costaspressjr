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
    // One host name for the whole site (APP_URL's): costaspressjr.com → www.
    if (($to = canonical_host_redirect()) !== null) {
        header('Location: ' . $to, true, 301);
        exit;
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
// The address names the language: /el/about is the Greek About page, /about
// the English one. I18n::route() takes the prefix off (routes only ever see
// /about) and may send someone who reads Greek on to the Greek page. In
// Greek, links and redirects get the prefix back on the way out, so readers
// and crawlers stay in Greek.
if (($to = I18n::route($_SERVER['REQUEST_METHOD'], $wantsJson)) !== null) {
    header('Location: ' . $to, true, 302);
    exit;
}
if (I18n::locale() !== I18n::DEFAULT_LOCALE) {
    // Location: /contact → /el/contact (the 301 or 302 status is kept).
    header_register_callback(static function (): void {
        foreach (headers_list() as $h) {
            if (preg_match('#^Location:\s*(/(?!/)\S*)#i', $h, $m)) {
                header('Location: ' . I18n::url($m[1]));
            }
        }
    });
    // href="/shop" → href="/el/shop" (I18n::localizeLinks).
    ob_start(static function (string $out): string {
        foreach (headers_list() as $h) {
            if (stripos($h, 'Content-Type:') === 0 && stripos($h, 'text/html') === false) {
                return $out;   // JSON, XML, text: not a page
            }
        }
        return I18n::localizeLinks($out);
    });
}
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
