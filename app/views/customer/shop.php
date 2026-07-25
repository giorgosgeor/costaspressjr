<?php $title = t('shop.premade.title', false); ?>
<?php require __DIR__ . '/../layouts/customer_header.php'; ?>

<section class="section shop-section">
    <div class="container">
        <nav class="breadcrumb">
            <a href="/"><?= t('header.nav.home') ?></a> &gt;
            <a href="/shop"><?= t('header.nav.shop') ?></a> &gt;
            <span><?= t('shop.premade.breadcrumb') ?></span>
        </nav>

        <div class="shop-header">
            <h1><?= t('shop.premade.title') ?></h1>
            <p class="shop-subtitle"><?= t('shop.premade.subtitle') ?></p>
        </div>

        <!-- Category Sections Grid -->
        <div class="categories-grid shop-categories-grid">
            <!-- Anime Section -->
            <a href="/shop/premade/anime" class="category-card">
                <div class="category-image anime-bg">
                    <div class="category-icon">🎌</div>
                </div>
                <div class="category-info">
                    <h3><?= t('shop.premade.anime_title') ?></h3>
                    <p><?= t('shop.premade.anime_lead') ?></p>
                </div>
            </a>

            <!-- Coming Soon Section -->
            <div class="category-card coming-soon">
                <div class="category-image">
                    <div class="category-icon"></div>
                </div>
                <div class="category-info">
                    <h3><?= t('shop.premade.coming_soon') ?></h3>
                    <p><?= t('shop.premade.coming_soon_lead') ?></p>
                </div>
            </div>
        </div>
    </div>
</section>


<?php require __DIR__ . '/../layouts/customer_footer.php'; ?>
