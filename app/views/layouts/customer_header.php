<?php
$pageTitle       = $title ?? t('site.brand', false);
$brandFull       = t('site.full_title', false);
$pageFullTitle   = $pageTitle === t('site.brand', false) ? $brandFull : ($pageTitle . ' · ' . t('site.brand', false));
$pageDescription = $metaDescription ?? t('site.meta_description', false);
$canonicalUrl    = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'www.costaspressjr.com') . ($_SERVER['REQUEST_URI'] ?? '/');
$currentLocale   = I18n::locale();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLocale) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= Csrf::metaTag() ?>
    <title><?= htmlspecialchars($pageFullTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta name="theme-color" content="#15130E">
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
    <link rel="icon" type="image/png" href="/images/logo.png">
    <link rel="apple-touch-icon" href="/images/logo.png">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= t('site.brand') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageFullTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
    <meta property="og:image" content="/images/og-card.png">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($pageFullTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta name="twitter:image" content="/images/og-card.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(Asset::url('/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(Asset::url('/css/customer.css')) ?>">
    <?php if (!empty($extraCss)): foreach ($extraCss as $css): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(Asset::url($css)) ?>">
    <?php endforeach; endif; ?>
    <!-- Client-side pricing mirror (previews only; server is authoritative) -->
    <script src="<?= htmlspecialchars(Asset::url('/js/pricing.js')) ?>" defer></script>
</head>
<body>
    <a href="#main-content" class="skip-link"><?= t('header.skip_to_content') ?></a>
    <header class="site-header">
        <div class="container header-inner">
            <a href="/" class="logo">
                <img src="/images/logo.png" alt="<?= t('site.brand') ?>">
            </a>

            <button class="mobile-menu-toggle" type="button" aria-label="<?= t('header.toggle_menu') ?>" aria-expanded="false" aria-controls="primary-nav">
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
            </button>

            <?php
            $currentPath = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
            function navActive(string $path, string $href): string {
                if ($href === '/' && $path === '/') return ' class="active"';
                if ($href !== '/' && strncmp($path, $href, strlen($href)) === 0) return ' class="active"';
                return '';
            }
            ?>
            <nav id="primary-nav" class="main-nav" aria-label="Primary">
                <a href="/"<?= navActive($currentPath, '/') ?>><?= t('header.nav.home') ?></a>
                <a href="/shop"<?= navActive($currentPath, '/shop') ?>><?= t('header.nav.shop') ?></a>
                <a href="/about"<?= navActive($currentPath, '/about') ?>><?= t('header.nav.about') ?></a>
                <a href="/contact"<?= navActive($currentPath, '/contact') ?>><?= t('header.nav.contact') ?></a>
                <div class="nav-mobile-tail">
                    <?php if (Auth::check()): ?>
                        <a href="/account" class="btn btn-sm"><?= t('header.my_account') ?></a>
                        <form method="post" action="/logout" class="logout-form">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-sm btn-danger"><?= t('header.logout') ?></button>
                        </form>
                    <?php else: ?>
                        <a href="/login" class="btn btn-sm"><?= t('header.login') ?></a>
                        <a href="/register" class="btn btn-sm btn-success"><?= t('header.register') ?></a>
                    <?php endif; ?>
                    <div class="lang-switcher" role="group" aria-label="Language">
                        <a href="/lang/en" class="<?= $currentLocale === 'en' ? 'active' : '' ?>" hreflang="en">EN</a>
                        <span class="sep" aria-hidden="true"></span>
                        <a href="/lang/el" class="<?= $currentLocale === 'el' ? 'active' : '' ?>" hreflang="el">EL</a>
                    </div>
                </div>
            </nav>

            <div class="header-actions">
                <?php if (Auth::check()): ?>
                    <?php
                    $cartCount = (int)($_SESSION['cart_count'] ?? 0);
                    if ($cartCount > 0) {
                        $cartAria = $cartCount === 1
                            ? I18n::t('header.cart_aria_count_one', ['count' => $cartCount])
                            : I18n::t('header.cart_aria_count_many', ['count' => $cartCount]);
                    } else {
                        $cartAria = I18n::t('header.cart_aria');
                    }
                    ?>
                    <a href="/cart" class="cart-link" aria-label="<?= htmlspecialchars($cartAria) ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                        <?php if ($cartCount > 0): ?>
                        <span class="cart-count" id="cart-count" aria-hidden="true"><?= $cartCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="/account" class="btn btn-sm"><?= t('header.my_account') ?></a>
                    <form method="post" action="/logout" class="logout-form">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn-sm btn-danger"><?= t('header.logout') ?></button>
                    </form>
                <?php else: ?>
                    <a href="/login" class="btn btn-sm"><?= t('header.login') ?></a>
                    <a href="/register" class="btn btn-sm btn-success"><?= t('header.register') ?></a>
                <?php endif; ?>
                <div class="lang-switcher" role="group" aria-label="Language">
                    <a href="/lang/en" class="<?= $currentLocale === 'en' ? 'active' : '' ?>" title="<?= t('lang.switch_to_english') ?>" hreflang="en">EN</a>
                    <span class="sep" aria-hidden="true"></span>
                    <a href="/lang/el" class="<?= $currentLocale === 'el' ? 'active' : '' ?>" title="<?= t('lang.switch_to_greek') ?>" hreflang="el">EL</a>
                </div>
            </div>
        </div>
    </header>
    <?php
    $showVerifyBanner = false;
    if (Auth::check() && isset($db) && $db instanceof PDO) {
        try {
            $stmt = $db->prepare("SELECT email_verified_at FROM users WHERE id = ?");
            $stmt->execute([Auth::userId()]);
            $row = $stmt->fetch();
            if ($row && $row['email_verified_at'] === null) {
                $showVerifyBanner = true;
            }
        } catch (\PDOException $e) {
            // email_verified_at may not exist yet if the migration hasn't run. Fail quietly.
        }
    }
    ?>
    <?php if ($showVerifyBanner): ?>
    <div class="verify-banner" role="status">
        <div class="container verify-banner-inner">
            <?php if (!empty($_GET['verify']) && $_GET['verify'] === 'sent'): ?>
            <span><?= t('verify.banner.sent') ?></span>
            <?php else: ?>
            <span><?= t('verify.banner.unverified') ?></span>
            <form method="post" action="/account/resend-verification" class="verify-banner-form">
                <?= Csrf::field() ?>
                <button type="submit" class="verify-banner-btn"><?= t('verify.banner.resend') ?></button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <main id="main-content">
