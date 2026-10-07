<?php $title = t('shop.premade.title', false); ?>
<?php require View::path('layouts/customer_header'); ?>

<section class="section shop-section">
    <div class="container">
        <?php
            $crumbs  = [[t('header.nav.home', false), '/'], [t('header.nav.shop', false), '/shop'], [t('shop.premade.breadcrumb', false), null]];
            $heading = t('shop.premade.title', false);
            $lead    = t('shop.premade.subtitle', false);
            require View::path('partials/page_head');
        ?>

        <!-- Category Sections Grid -->
        <div class="categories-grid shop-categories-grid">
            <!-- Anime Section -->
            <a href="/shop/premade/anime" class="category-card">
                <?php
                    // Artwork cover if it has been added, otherwise the patterned
                    // tile below shows through. onerror hides a missing file so a
                    // broken-image glyph never reaches the shop front.
                    $animeCover = 'images/anime/section-cover.jpg';
                    $animeCoverExists = is_file(public_path($animeCover));
                ?>
                <div class="category-image anime-bg<?= $animeCoverExists ? ' has-cover' : '' ?>">
                    <?php if ($animeCoverExists): ?>
                        <img src="/<?= htmlspecialchars($animeCover) ?>" alt="" class="category-cover"
                             loading="lazy" onerror="this.remove()">
                    <?php else: ?>
                        <div class="category-icon"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg></div>
                    <?php endif; ?>
                </div>
                <div class="category-info">
                    <h3><?= t('shop.premade.anime_title') ?></h3>
                    <p><?= t('shop.premade.anime_lead') ?></p>
                </div>
            </a>

            <!-- Coming Soon Section -->
            <div class="category-card coming-soon">
                <div class="category-image">
                    <?php // Was an empty div, which rendered as a blank panel. ?>
                    <div class="category-icon"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15.5 14"/></svg></div>
                </div>
                <div class="category-info">
                    <h3><?= t('shop.premade.coming_soon') ?></h3>
                    <p><?= t('shop.premade.coming_soon_lead') ?></p>
                </div>
            </div>
        </div>
    </div>
</section>


<?php require View::path('layouts/customer_footer'); ?>
