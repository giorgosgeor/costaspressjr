<?php
/**
 * Every URL the shop answers, and the controller method behind it.
 * Required by public/index.php with $router, $db and $wantsJson in scope.
 *
 * A route answers its own path only, unless it ends in /{id}: then it also
 * takes one more path segment, passed to the handler as an argument:
 * /product/12-t-shirt → ShopController::product('12-t-shirt'). Admin
 * controllers read that id themselves (AdminController::getIdFromUrl()).
 */

/** @var Router $router */
/** @var PDO $db */
/** @var bool $wantsJson */

$site     = new SiteController($db);
$pages    = new PageController($db);
$shop     = new ShopController($db);
$designs  = new CustomDesignController($db);
$cart     = new CartController($db);
$checkout = new CheckoutController($db);
$stripe   = new StripeWebhookController($db);
$account  = new AccountController($db);
$auth     = new AuthController($db);
$twoFactor = new TwoFactorController($db);
$assistant = new AssistantController($db);

$adminDashboard = new AdminDashboardController($db);
$adminProducts  = new AdminProductController($db);
$adminColors    = new AdminColorController($db);
$adminOrders    = new AdminOrderController($db);
$adminPickup    = new AdminPickupPointController($db);
$adminPremade   = new AdminPremadeDesignController($db);
$adminTools     = new AdminToolController($db);

// ---- Site ---------------------------------------------------------------
$router->get('/sitemap.xml', [$site, 'sitemap']);
$router->get('/robots.txt', [$site, 'robots']);
$router->get('/health', [$site, 'health']);

// Language switcher: /lang/el?to=/about remembers the choice (the lang
// cookie) and opens the same page in that language, /el/about. ?to is the
// page's own path, without a language prefix; anything that isn't a plain
// path on this site (//evil.example, /\evil.example) goes to the home page.
foreach (I18n::SUPPORTED as $locale) {
    $router->get("/lang/$locale", function () use ($locale) {
        I18n::setLocale($locale);
        $to = (string)($_GET['to'] ?? '/');
        if (!preg_match('#^/(?!/)[^\\\\:\x00-\x20\x7F-\xFF]*$#', $to)) {
            $to = '/';
        }
        header('Location: ' . I18n::url($to, $locale));
    });
}

// ---- Pages --------------------------------------------------------------
$router->get('/', [$pages, 'index']);
// The home page has one address, /.
$router->get('/home', function () {
    header('Location: /', true, 301);
});
$router->get('/about', [$pages, 'about']);
$router->get('/contact', [$pages, 'contact']);
$router->post('/contact', [$pages, 'sendContact']);
foreach (['terms', 'privacy', 'cookies', 'faq', 'shipping', 'returns', 'sizing'] as $slug) {
    $router->get("/$slug", function () use ($pages, $slug) { $pages->info($slug); });
}
$router->get('/track-order', [$pages, 'trackOrder']);
$router->post('/assistant/ask', [$assistant, 'ask']);

// ---- Shop ---------------------------------------------------------------
$router->get('/shop/{id}', [$shop, 'index']);   // /shop, and /shop/hoodies (ProductFamily)
$router->get('/shop/premade', [$shop, 'premade']);
$router->get('/shop/premade/anime', [$shop, 'anime']);
$router->get('/shop/design/{id}', [$shop, 'premadeDesign']);
$router->get('/shop/select_product', [$shop, 'selectProduct']);
$router->get('/shop/custom', [$shop, 'designer']);
$router->get('/shop/custom_product/shop_custom', [$shop, 'designer']);
// Each product has its own page now (/product/{id}-{slug}); this address
// showed whichever product the session held.
$router->get('/shop/custom_product', function () {
    header('Location: /shop/select_product', true, 301);
});
$router->get('/product/{id}', [$shop, 'product']);
$router->get('/api/product-variants/{id}', [$shop, 'productVariants']);

// ---- Saved designs ------------------------------------------------------
$router->post('/custom-design/save', [$designs, 'save']);
$router->post('/custom-design/update', [$designs, 'update']);
$router->post('/custom-design/save-previews', [$designs, 'savePreviews']);
$router->post('/custom-design/delete', [$designs, 'delete']);

// ---- Cart & checkout ----------------------------------------------------
$router->get('/cart', [$cart, 'show']);
$router->post('/cart/add', [$cart, 'add']);
$router->post('/cart/update-quantity', [$cart, 'updateQuantity']);
$router->post('/cart/remove', [$cart, 'remove']);
$router->post('/cart/save-previews', [$cart, 'savePreviews']);
$router->get('/checkout', [$checkout, 'show']);
$router->post('/api/create-checkout-session', [$checkout, 'createCheckoutSession']);
$router->get('/checkout/complete', [$checkout, 'complete']);
$router->get('/api/pickup-points', [$checkout, 'pickupPoints']);
$router->post('/stripe/webhook', [$stripe, 'handle']);

// ---- Account ------------------------------------------------------------
$router->get('/account', [$account, 'index']);
$router->get('/orders', [$account, 'orders']);
$router->get('/orders/view', [$account, 'order']);
$router->post('/orders/cancel', [$account, 'cancelOrder']);
$router->post('/account/favorites/toggle', [$account, 'toggleFavorite']);
$router->post('/account/cookie-consent', [$account, 'cookieConsent']);

// ---- Sign-in ------------------------------------------------------------
$router->get('/login', [$auth, 'showLogin']);
$router->post('/login', [$auth, 'login']);
// An admin's second step: the authenticator code (TwoFactorController).
$router->get('/login/two-factor', [$twoFactor, 'show']);
$router->post('/login/two-factor', [$twoFactor, 'verify']);
$router->get('/login/two-factor/setup', [$twoFactor, 'showSetup']);
$router->post('/login/two-factor/setup', [$twoFactor, 'completeSetup']);
$router->get('/register', [$auth, 'showRegister']);
$router->post('/register', [$auth, 'register']);
$router->get('/verify-email', [$auth, 'verifyEmail']);
$router->post('/account/resend-verification', [$auth, 'resendVerification']);
$router->get('/forgot-password', [$auth, 'showForgotPassword']);
$router->post('/forgot-password', [$auth, 'forgotPassword']);
$router->get('/reset-password', [$auth, 'showResetPassword']);
$router->post('/reset-password', [$auth, 'resetPassword']);
$router->post('/logout', [$auth, 'logout']);

// ---- Admin --------------------------------------------------------------
$router->get('/admin', [$adminDashboard, 'index']);
$router->get('/admin/users', [$adminDashboard, 'users']);

$router->get('/admin/products', [$adminProducts, 'index']);
$router->get('/admin/products/add', [$adminProducts, 'create']);
$router->post('/admin/products/add', [$adminProducts, 'store']);
$router->get('/admin/products/edit/{id}', [$adminProducts, 'edit']);
$router->post('/admin/products/edit/{id}', [$adminProducts, 'update']);
$router->post('/admin/products/delete/{id}', [$adminProducts, 'destroy']);
$router->get('/admin/products/design-area', [$adminProducts, 'designArea']);
$router->post('/admin/products/design-area/save', [$adminProducts, 'saveDesignArea']);
$router->get('/admin/api/product/{id}', [$adminProducts, 'apiShow']);

$router->get('/admin/colors', [$adminColors, 'index']);
$router->post('/admin/colors/add', [$adminColors, 'store']);
$router->post('/admin/colors/delete/{id}', [$adminColors, 'destroy']);
$router->get('/admin/api/colors', [$adminColors, 'apiIndex']);

$router->get('/admin/orders', [$adminOrders, 'index']);
$router->get('/admin/orders/view/{id}', [$adminOrders, 'show']);
$router->post('/admin/orders/status/{id}', [$adminOrders, 'updateStatus']);

$router->get('/admin/pickup-points', [$adminPickup, 'index']);
$router->post('/admin/pickup-points/add', [$adminPickup, 'store']);
$router->post('/admin/pickup-points/toggle', [$adminPickup, 'toggle']);
$router->post('/admin/pickup-points/delete', [$adminPickup, 'destroy']);
$router->post('/admin/pickup-points/sync', [$adminPickup, 'sync']);

$router->get('/admin/premade', [$adminPremade, 'index']);
$router->post('/admin/premade/add', [$adminPremade, 'store']);
$router->post('/admin/premade/edit/{id}', [$adminPremade, 'update']);
$router->post('/admin/premade/delete/{id}', [$adminPremade, 'destroy']);
$router->get('/admin/premade/position/{id}', [$adminPremade, 'position']);
$router->post('/admin/premade/position/{id}', [$adminPremade, 'savePosition']);
$router->get('/admin/api/premade/{id}', [$adminPremade, 'apiShow']);

$router->get('/admin/tools/bg-remover', [$adminTools, 'backgroundRemover']);
$router->get('/admin/tools/image-cropper', [$adminTools, 'imageCropper']);

// ---- Anything else ------------------------------------------------------
// JSON for API callers, the branded page for people.
$router->setNotFound(function () use ($pages, $wantsJson) {
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'not_found']);
        return;
    }
    $pages->notFound();
});
