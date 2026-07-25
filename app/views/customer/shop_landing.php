<?php $title = t('shop.landing.title', false); ?>
<?php require __DIR__ . '/../layouts/customer_header.php'; ?>

<section class="section shop-landing-section">
    <div class="container">
        <div class="shop-header">
            <h1><?= t('shop.landing.title') ?></h1>
            <p class="shop-subtitle"><?= t('shop.landing.subtitle') ?></p>
        </div>

        <div class="shop-options-grid">
            <a href="/shop/premade" class="shop-option-card">
                <div class="shop-option-icon"></div>
                <h2><?= t('shop.landing.premade.title') ?></h2>
                <p><?= t('shop.landing.premade.lead') ?></p>
                <ul class="shop-option-features">
                    <li><?= t('shop.landing.premade.f1') ?></li>
                    <li><?= t('shop.landing.premade.f2') ?></li>
                    <li><?= t('shop.landing.premade.f3') ?></li>
                    <li><?= t('shop.landing.premade.f4') ?></li>
                </ul>
                <span class="btn btn-lg"><?= t('shop.landing.premade.button') ?></span>
            </a>

            <a href="/shop/select_product" class="shop-option-card">
                <div class="shop-option-icon"></div>
                <h2><?= t('shop.landing.custom.title') ?></h2>
                <p><?= t('shop.landing.custom.lead') ?></p>
                <ul class="shop-option-features">
                    <li><?= t('shop.landing.custom.f1') ?></li>
                    <li><?= t('shop.landing.custom.f2') ?></li>
                    <li><?= t('shop.landing.custom.f3') ?></li>
                    <li><?= t('shop.landing.custom.f4') ?></li>
                </ul>
                <span class="btn btn-lg btn-success"><?= t('shop.landing.custom.button') ?></span>
            </a>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../layouts/customer_footer.php'; ?>
