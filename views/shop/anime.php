<?php
$title    = t('shop.anime.title', false);
$extraCss = ['/css/shop_anime.css'];
require View::path('layouts/customer_header');

$designCount = count($designs ?? []);
?>

<div class="anime-page">

    <!-- ============================================================
         SPLASH — a manga splash page: speed lines, screentone, the
         collection's own character over a red slab, and panels of
         the artwork behind her. Everything here is decorative except
         the copy on the left.
         ============================================================ -->
    <section class="an-splash" aria-labelledby="animeTitle">
        <div class="an-speedlines" aria-hidden="true"></div>
        <div class="an-screentone" aria-hidden="true"></div>
        <span class="an-vertical" lang="ja" aria-hidden="true">アニメ</span>

        <div class="container an-splash-inner">
            <nav class="breadcrumb an-breadcrumb">
                <a href="/"><?= t('header.nav.home') ?></a> /
                <a href="/shop"><?= t('header.nav.shop') ?></a> /
                <a href="/shop/premade"><?= t('shop.premade.breadcrumb') ?></a> /
                <span><?= t('shop.anime.title') ?></span>
            </nav>

            <div class="an-splash-grid">
                <div class="an-copy">
                    <p class="an-kicker">
                        <span class="an-kicker-tag" lang="ja" aria-hidden="true">コレクション</span>
                        <span><?= t('shop.premade.breadcrumb') ?></span>
                    </p>
                    <h1 id="animeTitle" class="an-title"><?= t('shop.anime.title') ?></h1>
                    <p class="an-lead"><?= t('shop.anime.subtitle') ?></p>
                    <?php if ($designCount): ?>
                    <a href="#collection" class="an-cta">
                        <span><?= t('shop.anime.browse') ?></span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                    </a>
                    <?php endif; ?>
                </div>

                <div class="an-stage" aria-hidden="true">
                    <div class="an-slab"></div>
                    <figure class="an-panel an-panel-1"><img src="/images/anime/clean/anime4.webp" alt=""></figure>
                    <figure class="an-panel an-panel-2"><img src="/images/anime/clean/anime6.webp" alt=""></figure>
                    <?php // The collection's own character, cut out like a sticker:
                          // a trimmed copy of designs/design_1.png (its sheet had a
                          // border line down the right edge), kept for this page so
                          // it stays if the design itself is ever removed. ?>
                    <img class="an-hero-char" src="/images/anime/hero-character.webp" alt="" width="829" height="1146" onerror="this.remove()">
                    <span class="an-sfx" lang="ja">ドン!</span>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================
         COLLECTION — every design as a collectible card: a number,
         the print on its garment, a name plate. Holographic foil and
         a tilt follow the pointer; the button flips to the back print.
         ============================================================ -->
    <section class="an-collection" id="collection" aria-labelledby="animeCollectionTitle">
        <div class="container an-collection-grid">
        <div class="an-collection-main">
            <div class="an-collection-head">
                <span class="an-collection-jp" lang="ja" aria-hidden="true">カード</span>
                <h2 id="animeCollectionTitle" class="an-collection-title">
                    <?= $designCount === 1 ? t('shop.anime.count_one') : I18n::t('shop.anime.count', ['count' => $designCount]) ?>
                </h2>
            </div>

            <?php if ($designCount): ?>
            <ol class="an-cards">
                <?php foreach ($designs as $i => $design):
                    // Design overlay position formula:
                    // Design area: left=50%, top=55%, width=50%, height=75% of square container
                    // x/y are offsets as % of half-area; size is % of area width
                    $posX = (float)($design['design_pos_x'] ?? 0);
                    $posY = (float)($design['design_pos_y'] ?? 0);
                    $posSize = (float)($design['design_pos_size'] ?? 55);
                    $frontLeft = 50 + $posX * 0.25;
                    $frontTop  = 55 + $posY * 0.375;
                    $frontW    = $posSize * 0.5;

                    $bposX = (float)($design['design_pos_back_x'] ?? 0);
                    $bposY = (float)($design['design_pos_back_y'] ?? 0);
                    $bposSize = (float)($design['design_pos_back_size'] ?? 55);
                    $backLeft = 50 + $bposX * 0.25;
                    $backTop  = 55 + $bposY * 0.375;
                    $backW    = $bposSize * 0.5;

                    $hasBack = !empty($design['back_image_path']) && !empty($design['product_back_image_path']);
                    // product_base_price is the SUPPLIER cost — convert to the
                    // qty-1 retail price before adding the design fee, matching
                    // what add-to-cart will actually charge.
                    $fromPrice = Pricing::unitPrice(
                        (float)($design['product_base_price'] ?? 0),
                        Pricing::categoryFor('', (string)($design['product_name'] ?? '')),
                        1
                    ) + $design['price'];
                    $isFav  = in_array((int)$design['id'], $favoriteDesignIds ?? [], true);
                    $name   = htmlspecialchars($design['name']);
                    $href   = '/shop/design/' . (int)$design['id'];
                    $number = sprintf('%03d', $i + 1);
                ?>
                <li class="an-card-wrap">
                    <article class="an-card" data-card>
                        <div class="an-card-flip">
                            <a href="<?= $href ?>" class="an-card-face an-card-front" aria-label="<?= $name ?>">
                                <span class="an-card-top" aria-hidden="true">
                                    <span class="an-card-no">No.<?= $number ?></span>
                                    <span class="an-card-jp" lang="ja">アニメ</span>
                                </span>
                                <span class="an-card-art">
                                    <?php if (!empty($design['product_image_path'])): ?>
                                    <img src="/<?= htmlspecialchars($design['product_image_path']) ?>" alt="" class="an-card-garment" loading="lazy">
                                    <?php else: ?>
                                    <span class="an-card-missing"><?= t('shop.anime.no_product_image') ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($design['image_path'])): ?>
                                    <img src="/<?= htmlspecialchars($design['image_path']) ?>" alt="" class="an-card-print"
                                         style="left:<?= $frontLeft ?>%;top:<?= $frontTop ?>%;width:<?= $frontW ?>%;">
                                    <?php endif; ?>
                                </span>
                                <span class="an-card-plate">
                                    <span class="an-card-name"><?= $name ?></span>
                                    <span class="an-card-price"><?= I18n::t('shop.anime.from_price', ['price' => number_format($fromPrice, 2)]) ?></span>
                                </span>
                                <span class="an-card-foil" aria-hidden="true"></span>
                                <span class="an-card-glare" aria-hidden="true"></span>
                            </a>
                            <?php if ($hasBack): ?>
                            <a href="<?= $href ?>" class="an-card-face an-card-back" tabindex="-1" aria-hidden="true" aria-label="<?= $name ?>">
                                <span class="an-card-top">
                                    <span class="an-card-no">No.<?= $number ?></span>
                                    <span class="an-card-jp" lang="ja">うら</span>
                                </span>
                                <span class="an-card-art">
                                    <img src="/<?= htmlspecialchars($design['product_back_image_path']) ?>" alt="" class="an-card-garment" loading="lazy">
                                    <img src="/<?= htmlspecialchars($design['back_image_path']) ?>" alt="" class="an-card-print"
                                         style="left:<?= $backLeft ?>%;top:<?= $backTop ?>%;width:<?= $backW ?>%;">
                                </span>
                                <span class="an-card-plate">
                                    <span class="an-card-name"><?= $name ?></span>
                                    <span class="an-card-price"><?= I18n::t('shop.anime.from_price', ['price' => number_format($fromPrice, 2)]) ?></span>
                                </span>
                                <span class="an-card-foil"></span>
                                <span class="an-card-glare"></span>
                            </a>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="fav-btn<?= $isFav ? ' is-favorited' : '' ?>"
                                data-kind="design" data-id="<?= (int)$design['id'] ?>"
                                aria-pressed="<?= $isFav ? 'true' : 'false' ?>"
                                data-label-on="<?= t('favorites.remove') ?>"
                                data-label-off="<?= t('favorites.add') ?>"
                                aria-label="<?= $isFav ? t('favorites.remove') : t('favorites.add') ?>"
                                title="<?= $isFav ? t('favorites.remove') : t('favorites.add') ?>">
                            <svg width="18" height="18" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 5.6a5 5 0 0 0-7.1 0L12 7.3l-1.7-1.7a5 5 0 1 0-7.1 7.1l8.8 8.8 8.8-8.8a5 5 0 0 0 0-7.1z"/></svg>
                        </button>
                    </article>
                    <div class="an-card-actions">
                        <a href="<?= $href ?>" class="an-view"><?= t('shop.anime.view_design') ?></a>
                        <?php if ($hasBack): ?>
                        <button type="button" class="an-flip" aria-pressed="false"
                                title="<?= t('shop.anime.flip_back') ?>"
                                data-label-back="<?= t('shop.anime.flip_back') ?>"
                                data-label-front="<?= t('shop.anime.flip_front') ?>">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 0 1 15.5-6.2L21 8M21 3v5h-5M21 12a9 9 0 0 1-15.5 6.2L3 16M3 21v-5h5"/></svg>
                            <span class="visually-hidden"><?= t('shop.anime.flip_back') ?></span>
                        </button>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ol>
            <?php else: ?>
            <div class="an-empty">
                <span class="an-empty-jp" lang="ja" aria-hidden="true">近日</span>
                <h3><?= t('shop.anime.coming_title') ?></h3>
                <p><?= nl2br(t('shop.anime.coming_text')) ?></p>
            </div>
            <?php endif; ?>
        </div>

        <?php // Art beside the list while the collection is small: more of the
              // shop's artwork popping off the page like the splash, pinned in
              // view as the cards scroll. Decorative only; hidden on phones,
              // where the splash already carries the art. ?>
        <div class="an-collection-art" aria-hidden="true">
            <div class="an-art-stage">
                <figure class="an-panel an-art-panel-1"><img src="/images/anime/section-cover.jpg" alt="" loading="lazy"></figure>
                <figure class="an-panel an-art-panel-2"><img src="/images/anime/clean/anime5.webp" alt="" loading="lazy"></figure>
                <img class="an-art-char" src="/images/anime/sticker-flowers.webp" alt="" width="534" height="707" loading="lazy" onerror="this.remove()">
                <span class="an-art-sfx" lang="ja">ゴゴゴ</span>
            </div>
        </div>
        </div>
    </section>
</div>

<script>
// Cards: a tilt and a moving foil highlight that follow the pointer, and a
// button that flips the card to its back print. The tilt is skipped for
// touch screens and for a reduced-motion preference; the flip always works.
(function () {
    var cards = document.querySelectorAll('[data-card]');
    var calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var hover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    // Scroll entrance: the art and each card play their entrance when they
    // come into view (see shop_anime.css). Cards in a row go one after another.
    var page = document.querySelector('.anime-page');
    if (page && !calm && 'IntersectionObserver' in window) {
        page.classList.add('js-reveal');
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-in');
                io.unobserve(entry.target);
            });
        }, { threshold: 0.2 });
        page.querySelectorAll('.an-art-stage, .an-card-wrap').forEach(function (el) {
            if (el.classList.contains('an-card-wrap')) {
                var siblings = Array.prototype.indexOf.call(el.parentNode.children, el);
                el.style.setProperty('--i', siblings % 4);
            }
            io.observe(el);
        });
    }

    cards.forEach(function (card) {
        if (!calm && hover) {
            card.addEventListener('pointermove', function (e) {
                var r = card.getBoundingClientRect();
                var x = (e.clientX - r.left) / r.width;
                var y = (e.clientY - r.top) / r.height;
                card.style.setProperty('--mx', (x * 100).toFixed(1) + '%');
                card.style.setProperty('--my', (y * 100).toFixed(1) + '%');
                card.style.setProperty('--rx', ((0.5 - y) * 14).toFixed(2) + 'deg');
                card.style.setProperty('--ry', ((x - 0.5) * 18).toFixed(2) + 'deg');
                card.classList.add('is-live');
            });
            card.addEventListener('pointerleave', function () {
                card.classList.remove('is-live');
                card.style.setProperty('--rx', '0deg');
                card.style.setProperty('--ry', '0deg');
            });
        }
    });

    document.querySelectorAll('.an-flip').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var card = btn.closest('.an-card-wrap').querySelector('[data-card]');
            var flipped = !card.classList.contains('is-flipped');
            card.classList.toggle('is-flipped', flipped);
            btn.setAttribute('aria-pressed', flipped ? 'true' : 'false');
            var label = flipped ? btn.dataset.labelFront : btn.dataset.labelBack;
            btn.querySelector('span').textContent = label;
            btn.title = label;
            // Keep keyboard focus and the link on the side that is showing.
            var front = card.querySelector('.an-card-front');
            var back = card.querySelector('.an-card-back');
            if (front && back) {
                front.setAttribute('tabindex', flipped ? '-1' : '0');
                front.setAttribute('aria-hidden', flipped ? 'true' : 'false');
                back.setAttribute('tabindex', flipped ? '0' : '-1');
                back.setAttribute('aria-hidden', flipped ? 'false' : 'true');
            }
        });
    });
})();
</script>
<script src="<?= htmlspecialchars(Asset::url('/js/favorites.js')) ?>" defer></script>
<?php require View::path('layouts/customer_footer'); ?>
