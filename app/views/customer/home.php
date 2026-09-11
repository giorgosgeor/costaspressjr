<?php $title = t('home.landing.welcome', false); ?>
<?php require __DIR__ . '/../layouts/customer_header.php'; ?>

<script>
// Featured product cards open the customiser with that product preselected —
// the same handshake the product picker uses (/shop/set_selected_product
// stores the id in the session, /shop/custom_product reads it back). Works
// for guests too: designing no longer requires an account.
document.addEventListener('DOMContentLoaded', function () {
    function openProduct(card) {
        var productId = card.getAttribute('data-product-id');
        if (!productId) return;
        var meta = document.querySelector('meta[name="csrf-token"]');
        fetch('/shop/set_selected_product', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': meta ? meta.getAttribute('content') : ''
            },
            body: JSON.stringify({ product_id: productId })
        }).then(function (res) {
            if (res.ok) window.location.href = '/shop/custom_product';
        }).catch(function () {});
    }
    document.querySelectorAll('.product-card.featured-product').forEach(function (card) {
        card.style.cursor = 'pointer';
        card.addEventListener('click', function () { openProduct(card); });
        card.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openProduct(card); }
        });
    });
});
</script>

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
        <?php // Heading on the left, "view all" on the right of the same row —
              // it reads as part of the section header instead of a footer the
              // shopper only meets after scrolling past every card. ?>
        <div class="section-head">
            <div class="section-head-text">
                <span class="section-tag"><?= t('home.featured.tag') ?></span>
                <h2 class="section-title"><?= t('home.featured.title') ?></h2>
                <p class="section-subtitle"><?= t('home.featured.subtitle') ?></p>
            </div>
            <a href="/shop" class="btn btn-outline section-head-action">
                <?= t('home.featured.view_all') ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </a>
        </div>
        <div class="products-grid">
            <?php if (!empty($featuredProducts)): ?>
                <?php foreach ($featuredProducts as $product): ?>
                <div class="product-card featured-product" data-product-id="<?= (int)$product['id'] ?>" role="link" tabindex="0" aria-label="<?= htmlspecialchars($product['name']) ?>">
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
    </div>
</section>



<?php require __DIR__ . '/../layouts/customer_footer.php'; ?>
<?php endif; ?>

<?php if (!Auth::check()): ?>
<!-- Featured Products (visible to all visitors) -->
<section class="section products-section" style="background:#fff;">
    <div class="container">
        <?php // Same header treatment as the signed-in section — the call to
              // action sits beside the heading rather than below the grid. ?>
        <div class="section-head">
            <div class="section-head-text">
                <h2 class="section-title"><?= t('home.featured.title') ?></h2>
            </div>
            <a href="/register" class="btn section-head-action">
                <?= t('home.featured.sign_up') ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </a>
        </div>
        <div class="products-grid">
            <?php if (!empty($featuredProducts)): ?>
                <?php foreach ($featuredProducts as $product): ?>
                <div class="product-card featured-product" data-product-id="<?= (int)$product['id'] ?>" role="link" tabindex="0" aria-label="<?= htmlspecialchars($product['name']) ?>">
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
    </div>
</section>

<?php require __DIR__ . '/../layouts/customer_footer.php'; ?>
<?php endif; ?>
