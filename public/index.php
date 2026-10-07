<?php
require __DIR__ . '/../app/core/Env.php';
Env::load(__DIR__ . '/../.env');

$appEnv = Env::get('APP_ENV', 'production');
$isProd = $appEnv === 'production';

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
    set_exception_handler(function (Throwable $e): void {
        if (class_exists('Log')) {
            Log::exception($e, ['url' => $_SERVER['REQUEST_URI'] ?? null]);
        } else {
            error_log('Unhandled exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Something went wrong</title></head><body style="font-family:system-ui,sans-serif;max-width:560px;margin:80px auto;padding:0 20px;color:#1e293b;"><h1>Something went wrong.</h1><p>We\'re having trouble loading this page. Please try again in a moment.</p><p><a href="/">Back to homepage</a></p></body></html>';
        exit;
    });

    // A half-configured production site fails in ways nobody sees until a
    // customer does: emails silently logged instead of sent, reset links
    // built from the request's Host header, or — worst — Stripe test keys,
    // which let anyone "pay" with card 4242 for real goods. Refuse to serve
    // instead; the log names what is missing, /health returns 500.
    $problems = [];
    // The shop targets PHP 8.5 (security fixes until the end of 2029).
    // Shared hosts pick the PHP version per site and often default to an
    // older one, so a forgotten switch shows up here, not as odd bugs.
    if (PHP_VERSION_ID < 80500) {
        $problems[] = 'PHP 8.5 or newer is required (this server runs ' . PHP_VERSION . ')';
    }
    if (!str_starts_with((string)Env::get('APP_URL', ''), 'https://')) {
        $problems[] = 'APP_URL must be the https:// address of the site';
    }
    foreach (['STRIPE_SECRET_KEY', 'STRIPE_PUBLISHABLE_KEY', 'STRIPE_WEBHOOK_SECRET'] as $key) {
        if ((string)Env::get($key, '') === '') {
            $problems[] = "$key is empty";
        }
    }
    $secretIsTest = str_contains((string)Env::get('STRIPE_SECRET_KEY', ''), '_test_');
    $publicIsTest = str_contains((string)Env::get('STRIPE_PUBLISHABLE_KEY', ''), '_test_');
    if ($secretIsTest !== $publicIsTest) {
        $problems[] = 'STRIPE_SECRET_KEY and STRIPE_PUBLISHABLE_KEY are from different modes (test/live)';
    } elseif ($secretIsTest && Env::get('STRIPE_ALLOW_TEST_KEYS', '') !== '1') {
        $problems[] = 'Stripe keys are TEST keys (set STRIPE_ALLOW_TEST_KEYS=1 only for a dry run)';
    }
    $mailTransport = Env::get('MAIL_TRANSPORT', 'log');
    if (!in_array($mailTransport, ['smtp', 'mail'], true)) {
        $problems[] = 'MAIL_TRANSPORT must be smtp (or mail), not "' . $mailTransport . '"';
    } elseif ($mailTransport === 'smtp' && (string)Env::get('SMTP_HOST', '') === '') {
        $problems[] = 'SMTP_HOST is empty';
    }
    if ($problems) {
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
session_start();

require __DIR__ . '/../app/core/Csrf.php';
require __DIR__ . '/../app/core/Upload.php';
require __DIR__ . '/../app/core/Asset.php';
require __DIR__ . '/../app/core/Mailer.php';
require __DIR__ . '/../app/core/Log.php';
require __DIR__ . '/../app/core/I18n.php';
require __DIR__ . '/../app/core/Pricing.php';
require __DIR__ . '/../app/core/Tint.php';
require __DIR__ . '/../app/core/LlmClient.php';
require __DIR__ . '/../app/core/ShopAssistant.php';
require __DIR__ . '/../app/core/AcsClient.php';
require __DIR__ . '/../app/core/Pickup.php';
require __DIR__ . '/../app/core/OrderPlacement.php';
require __DIR__ . '/../app/core/PaymentAlert.php';
Asset::setPublicRoot(__DIR__);
I18n::init();
// Stripe's webhook can't carry our CSRF token; it is authenticated by its
// signature instead (Stripe::verifyWebhook).
if (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) !== '/stripe/webhook') {
    Csrf::validateRequest();
}

$db = (require __DIR__ . '/../app/config/database.php');

require __DIR__ . '/../app/core/Router.php';
require __DIR__ . '/../app/core/Auth.php';
require __DIR__ . '/../app/controllers/AuthController.php';
require __DIR__ . '/../app/controllers/HomeController.php';
require __DIR__ . '/../app/controllers/AdminController.php';
require __DIR__ . '/../app/controllers/CustomerController.php';
require __DIR__ . '/../app/controllers/CustomDesignController.php';
require __DIR__ . '/../app/core/Stripe.php';

$router = new Router();
$authController = new AuthController($db);
$homeController = new HomeController($db);
$adminController = new AdminController($db);
$customerController = new CustomerController($db);
$customDesignController = new CustomDesignController($db);

// Public Routes (no auth required)
$router->get('/', [$homeController, 'index']);
$router->get('/sitemap.xml', [$homeController, 'sitemap']);
$router->get('/health', [$homeController, 'health']);

// Language switcher — sets a cookie and redirects back to the referring page.
$router->get('/lang/en', function () {
    I18n::setLocale('en');
    $back = $_SERVER['HTTP_REFERER'] ?? '/';
    if (!preg_match('#^https?://#i', $back) && $back !== '' && $back[0] !== '/') {
        $back = '/';
    }
    header('Location: ' . $back);
});
$router->get('/lang/el', function () {
    I18n::setLocale('el');
    $back = $_SERVER['HTTP_REFERER'] ?? '/';
    if (!preg_match('#^https?://#i', $back) && $back !== '' && $back[0] !== '/') {
        $back = '/';
    }
    header('Location: ' . $back);
});

// Customer Routes (require login)
$router->get('/home', [$customerController, 'home']);
$router->get('/account', [$customerController, 'account']);
$router->post('/account/favorites/toggle', [$customerController, 'toggleFavorite']);
$router->get('/orders', [$customerController, 'orderList']);
$router->get('/orders/view', [$customerController, 'orderDetail']);
$router->get('/shop', [$customerController, 'shop']);
$router->get('/product', [$customerController, 'product']);
$router->post('/assistant/ask', [$customerController, 'assistantAsk']);
$router->get('/shop/custom_product', [$customerController, 'customProduct']);
$router->get('/shop/premade', [$customerController, 'shopPremade']);
$router->get('/shop/premade/anime', [$customerController, 'shopAnime']);
$router->get('/shop/design', [$customerController, 'viewDesign']);
$router->get('/shop/custom', [$customerController, 'shopCustom']);
$router->get('/shop/select_product', [$customerController, 'shopSelectProduct']);
$router->get('/about', [$customerController, 'about']);
$router->get('/contact', [$customerController, 'contact']);
$router->post('/contact', [$customerController, 'contactSubmit']);

// Static / legal pages
$router->get('/terms',       function () use ($customerController) { $customerController->infoPage('terms'); });
$router->get('/privacy',     function () use ($customerController) { $customerController->infoPage('privacy'); });
$router->get('/cookies',     function () use ($customerController) { $customerController->infoPage('cookies'); });
$router->get('/faq',         function () use ($customerController) { $customerController->infoPage('faq'); });
$router->get('/shipping',    function () use ($customerController) { $customerController->infoPage('shipping'); });
$router->get('/returns',     function () use ($customerController) { $customerController->infoPage('returns'); });
$router->get('/sizing',      function () use ($customerController) { $customerController->infoPage('sizing'); });
$router->get('/track-order', [$customerController, 'trackOrder']);
$router->post('/shop/set_selected_product', function() use ($customerController) {
	$customerController->setSelectedProduct();
});
$router->get('/shop/custom_product/shop_custom', [$customerController, 'shopCustom']);

// Auth Routes
$router->get('/login', [$authController, 'showLogin']);
$router->post('/login', [$authController, 'login']);

$router->get('/register', [$authController, 'showRegister']);
$router->post('/register', [$authController, 'register']);

$router->get('/verify-email', [$authController, 'verifyEmail']);
$router->post('/account/resend-verification', [$authController, 'resendVerification']);

$router->get('/forgot-password', [$authController, 'showForgotPassword']);
$router->post('/forgot-password', [$authController, 'forgotPassword']);
$router->get('/reset-password', [$authController, 'showResetPassword']);
$router->post('/reset-password', [$authController, 'resetPassword']);

$router->post('/logout', [$authController, 'logout']);

// Admin Routes
$router->get('/admin', [$adminController, 'dashboard']);
$router->get('/admin/users', [$adminController, 'users']);
$router->get('/admin/products', [$adminController, 'products']);
$router->get('/admin/products/add', [$adminController, 'showAddProduct']);
$router->post('/admin/products/add', [$adminController, 'addProduct']);
$router->get('/admin/products/edit', [$adminController, 'showEditProduct']);
$router->post('/admin/products/edit', [$adminController, 'updateProduct']);
$router->post('/admin/products/delete', [$adminController, 'deleteProduct']);
$router->get('/admin/colors', [$adminController, 'colors']);
$router->post('/admin/colors/add', [$adminController, 'addColor']);
$router->post('/admin/colors/delete', [$adminController, 'deleteColor']);
$router->get('/admin/orders', [$adminController, 'orders']);
$router->get('/admin/orders/view', [$adminController, 'orderDetail']);
$router->post('/admin/orders/status', [$adminController, 'updateOrderStatus']);
$router->get('/admin/pickup-points', [$adminController, 'pickupPoints']);
$router->post('/admin/pickup-points/add', [$adminController, 'addPickupPoint']);
$router->post('/admin/pickup-points/toggle', [$adminController, 'togglePickupPoint']);
$router->post('/admin/pickup-points/delete', [$adminController, 'deletePickupPoint']);
$router->post('/admin/pickup-points/sync', [$adminController, 'syncPickupPoints']);

// Admin Premade Designs Routes
$router->get('/admin/premade', [$adminController, 'premadeDesigns']);
$router->post('/admin/premade/add', [$adminController, 'addPremadeDesign']);
$router->post('/admin/premade/edit', [$adminController, 'updatePremadeDesign']);
$router->post('/admin/premade/delete', [$adminController, 'deletePremadeDesign']);
$router->get('/admin/premade/position', [$adminController, 'positionEditor']);
$router->post('/admin/premade/position', [$adminController, 'savePosition']);

// Admin Tools Routes
$router->get('/admin/tools/bg-remover', [$adminController, 'backgroundRemover']);
$router->get('/admin/tools/image-cropper', [$adminController, 'imageCropper']);
$router->get('/admin/products/design-area', [$adminController, 'designAreaEditor']);
$router->post('/admin/products/design-area/save', [$adminController, 'saveDesignArea']);

// Admin API Routes
$router->get('/admin/api/colors', [$adminController, 'apiGetColors']);
$router->get('/admin/api/product', [$adminController, 'apiGetProduct']);
$router->get('/admin/api/premade', [$adminController, 'apiGetPremadeDesign']);

$router->get('/cart', [$customerController, 'cart']);
$router->post('/cart/add', [$customerController, 'cartAdd']);
$router->post('/cart/remove', [$customerController, 'cartRemove']);
$router->post('/cart/update-quantity', [$customerController, 'cartUpdateQuantity']);
$router->post('/cart/save-previews', [$customerController, 'cartSavePreviews']);
$router->get('/checkout', [$customerController, 'checkoutPage']);
$router->post('/api/create-payment-intent', [$customerController, 'createPaymentIntent']);
$router->get('/checkout/complete', [$customerController, 'checkoutComplete']);
$router->post('/stripe/webhook', [$customerController, 'stripeWebhook']);
$router->get('/api/pickup-points', [$customerController, 'pickupPoints']);
$router->post('/account/cookie-consent', [$customerController, 'cookieConsent']);
$router->post('/custom-design/save', [$customDesignController, 'save']);
$router->post('/custom-design/update', [$customDesignController, 'update']);
$router->post('/custom-design/save-previews', [$customDesignController, 'savePreviews']);
$router->post('/custom-design/delete', [$customDesignController, 'delete']);

// API Routes for customer
$router->get('/api/product-variants', [$customerController, 'getProductVariants']);

// Unknown URLs: JSON for API callers, the branded page for people.
$router->setNotFound(function () use ($customerController, $wantsJson) {
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'not_found']);
        return;
    }
    $customerController->notFound();
});

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
