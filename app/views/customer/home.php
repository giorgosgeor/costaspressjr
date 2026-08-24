<?php $title = t('home.landing.welcome', false); ?>
<?php require __DIR__ . '/../layouts/customer_header.php'; ?>

<?php if (!Auth::check()): ?>
<!-- Landing Section for Unauthenticated Users -->
<section class="landing-section">
    <div class="container">
        <div class="landing-content">
            <h1><?= t('home.landing.welcome') ?></h1>
            <p><?= t('home.landing.lead') ?></p>
        </div>
    </div>
</section>
<?php else: ?>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <div class="hero-content">
            <h1><?= t('home.hero.title') ?></h1>
            <p><?= t('home.hero.lead') ?></p>
            <div class="hero-buttons">
                <a href="/shop" class="btn btn-lg"><?= ($hasOrders ?? false) ? t('home.hero.shop_now') : t('home.hero.first_order') ?></a>
            </div>
        </div>
    </div>
</section>

<!-- Featured Categories -->
<section class="section categories-section">
    <div class="container">
        <h2 class="section-title"><?= t('home.categories.title') ?></h2>
        <p class="section-subtitle"><?= t('home.categories.subtitle') ?></p>
        <div class="categories-grid">
            <a href="/shop/premade" class="category-card category-large">
                <div class="category-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z"/></svg>
                </div>
                <h3><?= t('home.categories.premade.title') ?></h3>
                <p><?= t('home.categories.premade.lead') ?></p>
            </a>
            <a href="/shop/select_product" class="category-card category-large">
                <div class="category-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><path d="M2 2l7.586 7.586"/><circle cx="11" cy="11" r="2"/></svg>
                </div>
                <h3><?= t('home.categories.custom.title') ?></h3>
                <p><?= t('home.categories.custom.lead') ?></p>
            </a>
        </div>
    </div>
</section>

<!-- Featured Products -->
<section class="section products-section">
    <div class="container">
        <span class="section-tag"><?= t('home.featured.tag') ?></span>
        <h2 class="section-title"><?= t('home.featured.title') ?></h2>
        <p class="section-subtitle"><?= t('home.featured.subtitle') ?></p>
        <div class="products-grid">
            <?php if (!empty($featuredProducts)): ?>
                <?php foreach ($featuredProducts as $product): ?>
                <div class="product-card is-preview">
                    <div class="product-image">
                        <img src="/<?= htmlspecialchars($product['image_path'] ?? '') ?>" alt="<?= htmlspecialchars($product['name']) ?>" loading="lazy" onerror="this.src='/images/placeholder.svg'">
                        <?php if (!$product['active']): ?>
                            <span class="product-badge badge-soldout"><?= t('home.featured.sold_out') ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="product-info">
                        <h3 class="product-name"><?= htmlspecialchars($product['name']) ?></h3>
                        <p class="product-price">€<?= number_format($product['retail_price'] ?? $product['base_price'], 2) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-products">
                    <p><?= t('home.featured.no_products') ?></p>
                </div>
            <?php endif; ?>
        </div>
        <div class="section-footer">
            <a href="/shop" class="btn btn-lg"><?= t('home.featured.view_all') ?></a>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="section features-section">
    <div class="container">
        <span class="section-tag"><?= t('home.features.tag') ?></span>
        <h2 class="section-title"><?= t('home.features.title') ?></h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                </div>
                <h3><?= t('home.features.shipping_title') ?></h3>
                <p><?= t('home.features.shipping_lead') ?></p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                </div>
                <h3><?= t('home.features.returns_title') ?></h3>
                <p><?= t('home.features.returns_lead') ?></p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <h3><?= t('home.features.payment_title') ?></h3>
                <p><?= t('home.features.payment_lead') ?></p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                </div>
                <h3><?= t('home.features.support_title') ?></h3>
                <p><?= t('home.features.support_lead') ?></p>
            </div>
        </div>
    </div>
</section>


<?php require __DIR__ . '/../layouts/customer_footer.php'; ?>
<?php endif; ?>

<?php if (!Auth::check()): ?>
<!-- Featured Products (visible to all visitors) -->
<section class="section products-section" style="background:#fff;">
    <div class="container">
        <h2 class="section-title"><?= t('home.featured.title') ?></h2>
        <div class="products-grid">
            <?php if (!empty($featuredProducts)): ?>
                <?php foreach ($featuredProducts as $product): ?>
                <div class="product-card is-preview">
                    <div class="product-image">
                        <img src="/<?= htmlspecialchars($product['image_path'] ?? '') ?>" alt="<?= htmlspecialchars($product['name']) ?>" loading="lazy" onerror="this.src='/images/placeholder.svg'">
                        <?php if (!$product['active']): ?>
                            <span class="product-badge badge-soldout"><?= t('home.featured.sold_out') ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="product-info">
                        <h3 class="product-name"><?= htmlspecialchars($product['name']) ?></h3>
                        <p class="product-price">€<?= number_format($product['retail_price'] ?? $product['base_price'], 2) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-products">
                    <p><?= t('home.featured.no_products') ?></p>
                </div>
            <?php endif; ?>
        </div>
        <div class="section-footer">
            <a href="/register" class="btn btn-lg"><?= t('home.featured.sign_up') ?></a>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../layouts/customer_footer.php'; ?>
<?php endif; ?>
