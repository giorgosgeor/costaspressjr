<?php $title = t('shop.landing.title', false); ?>
<?php require View::path('layouts/customer_header'); ?>

<section class="section shop-landing-section">
    <div class="container">
        <?php
            $crumbs  = [[t('header.nav.home', false), '/'], [t('shop.landing.title', false), null]];
            $heading = t('shop.landing.title', false);
            $lead    = t('shop.landing.subtitle', false);
            require View::path('partials/page_head');
        ?>

        <div class="shop-options-grid">
            <a href="/shop/premade" class="shop-option-card">
                <span class="shop-option-num" aria-hidden="true">01</span>
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
                <span class="shop-option-num" aria-hidden="true">02</span>
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

<?php require View::path('layouts/customer_footer'); ?>
