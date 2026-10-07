<?php
/**
 * Every URL the shop answers, and the controller method behind it.
 * Required by public/index.php with $router, $db and $wantsJson in scope.
 *
 * A route also matches one extra path segment, passed to the handler as an
 * argument: /product/12 → ShopController::product('12').
 */

$authController = new AuthController($db);
$homeController = new HomeController($db);
$adminController = new AdminController($db);
$customerController = new CustomerController($db);
$customDesignController = new CustomDesignController($db);

// ---- Site ---------------------------------------------------------------
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

// ---- Pages --------------------------------------------------------------
$router->get('/home', [$customerController, 'home']);
$router->get('/about', [$customerController, 'about']);
$router->get('/contact', [$customerController, 'contact']);
$router->post('/contact', [$customerController, 'contactSubmit']);
$router->get('/terms',       function () use ($customerController) { $customerController->infoPage('terms'); });
$router->get('/privacy',     function () use ($customerController) { $customerController->infoPage('privacy'); });
$router->get('/cookies',     function () use ($customerController) { $customerController->infoPage('cookies'); });
$router->get('/faq',         function () use ($customerController) { $customerController->infoPage('faq'); });
$router->get('/shipping',    function () use ($customerController) { $customerController->infoPage('shipping'); });
$router->get('/returns',     function () use ($customerController) { $customerController->infoPage('returns'); });
$router->get('/sizing',      function () use ($customerController) { $customerController->infoPage('sizing'); });
$router->get('/track-order', [$customerController, 'trackOrder']);
$router->post('/assistant/ask', [$customerController, 'assistantAsk']);

// ---- Shop ---------------------------------------------------------------
$router->get('/shop', [$customerController, 'shop']);
$router->get('/product', [$customerController, 'product']);
$router->get('/shop/custom_product', [$customerController, 'customProduct']);
$router->get('/shop/premade', [$customerController, 'shopPremade']);
$router->get('/shop/premade/anime', [$customerController, 'shopAnime']);
$router->get('/shop/design', [$customerController, 'viewDesign']);
$router->get('/shop/custom', [$customerController, 'shopCustom']);
$router->get('/shop/custom_product/shop_custom', [$customerController, 'shopCustom']);
$router->get('/shop/select_product', [$customerController, 'shopSelectProduct']);
$router->post('/shop/set_selected_product', function () use ($customerController) {
    $customerController->setSelectedProduct();
});
$router->get('/api/product-variants', [$customerController, 'getProductVariants']);

// ---- Saved designs ------------------------------------------------------
$router->post('/custom-design/save', [$customDesignController, 'save']);
$router->post('/custom-design/update', [$customDesignController, 'update']);
$router->post('/custom-design/save-previews', [$customDesignController, 'savePreviews']);
$router->post('/custom-design/delete', [$customDesignController, 'delete']);

// ---- Cart & checkout ----------------------------------------------------
$router->get('/cart', [$customerController, 'cart']);
$router->post('/cart/add', [$customerController, 'cartAdd']);
$router->post('/cart/remove', [$customerController, 'cartRemove']);
$router->post('/cart/update-quantity', [$customerController, 'cartUpdateQuantity']);
$router->post('/cart/save-previews', [$customerController, 'cartSavePreviews']);
$router->get('/checkout', [$customerController, 'checkoutPage']);
$router->post('/api/create-payment-intent', [$customerController, 'createPaymentIntent']);
$router->get('/checkout/complete', [$customerController, 'checkoutComplete']);
$router->get('/api/pickup-points', [$customerController, 'pickupPoints']);
$router->post('/stripe/webhook', [$customerController, 'stripeWebhook']);

// ---- Account ------------------------------------------------------------
$router->get('/account', [$customerController, 'account']);
$router->post('/account/favorites/toggle', [$customerController, 'toggleFavorite']);
$router->post('/account/cookie-consent', [$customerController, 'cookieConsent']);
$router->get('/orders', [$customerController, 'orderList']);
$router->get('/orders/view', [$customerController, 'orderDetail']);

// ---- Sign-in ------------------------------------------------------------
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

// ---- Admin --------------------------------------------------------------
$router->get('/admin', [$adminController, 'dashboard']);
$router->get('/admin/users', [$adminController, 'users']);

$router->get('/admin/products', [$adminController, 'products']);
$router->get('/admin/products/add', [$adminController, 'showAddProduct']);
$router->post('/admin/products/add', [$adminController, 'addProduct']);
$router->get('/admin/products/edit', [$adminController, 'showEditProduct']);
$router->post('/admin/products/edit', [$adminController, 'updateProduct']);
$router->post('/admin/products/delete', [$adminController, 'deleteProduct']);
$router->get('/admin/products/design-area', [$adminController, 'designAreaEditor']);
$router->post('/admin/products/design-area/save', [$adminController, 'saveDesignArea']);
$router->get('/admin/api/product', [$adminController, 'apiGetProduct']);

$router->get('/admin/colors', [$adminController, 'colors']);
$router->post('/admin/colors/add', [$adminController, 'addColor']);
$router->post('/admin/colors/delete', [$adminController, 'deleteColor']);
$router->get('/admin/api/colors', [$adminController, 'apiGetColors']);

$router->get('/admin/orders', [$adminController, 'orders']);
$router->get('/admin/orders/view', [$adminController, 'orderDetail']);
$router->post('/admin/orders/status', [$adminController, 'updateOrderStatus']);

$router->get('/admin/pickup-points', [$adminController, 'pickupPoints']);
$router->post('/admin/pickup-points/add', [$adminController, 'addPickupPoint']);
$router->post('/admin/pickup-points/toggle', [$adminController, 'togglePickupPoint']);
$router->post('/admin/pickup-points/delete', [$adminController, 'deletePickupPoint']);
$router->post('/admin/pickup-points/sync', [$adminController, 'syncPickupPoints']);

$router->get('/admin/premade', [$adminController, 'premadeDesigns']);
$router->post('/admin/premade/add', [$adminController, 'addPremadeDesign']);
$router->post('/admin/premade/edit', [$adminController, 'updatePremadeDesign']);
$router->post('/admin/premade/delete', [$adminController, 'deletePremadeDesign']);
$router->get('/admin/premade/position', [$adminController, 'positionEditor']);
$router->post('/admin/premade/position', [$adminController, 'savePosition']);
$router->get('/admin/api/premade', [$adminController, 'apiGetPremadeDesign']);

$router->get('/admin/tools/bg-remover', [$adminController, 'backgroundRemover']);
$router->get('/admin/tools/image-cropper', [$adminController, 'imageCropper']);

// ---- Anything else ------------------------------------------------------
// JSON for API callers, the branded page for people.
$router->setNotFound(function () use ($customerController, $wantsJson) {
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'not_found']);
        return;
    }
    $customerController->notFound();
});
