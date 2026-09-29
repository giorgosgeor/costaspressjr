<?php $title = t('shop.anime.title', false); ?>
<?php require __DIR__ . '/../layouts/customer_header.php'; ?>

<section class="section shop-section anime-page">
    <div class="container">
        <nav class="breadcrumb">
            <a href="/"><?= t('header.nav.home') ?></a> &gt;
            <a href="/shop"><?= t('header.nav.shop') ?></a> &gt;
            <a href="/shop/premade"><?= t('shop.premade.breadcrumb') ?></a> &gt;
            <span><?= t('shop.anime.title') ?></span>
        </nav>

        <!-- Hero Banner -->
        <div class="anime-hero-banner">
            <img src="/images/anime/clean/anime4.webp" class="anime-bg-left"  alt="" aria-hidden="true">
            <img src="/images/anime/clean/anime6.webp" class="anime-bg-right" alt="" aria-hidden="true">
            <div class="anime-banner-overlay"></div>
            <div class="anime-banner-text">
                <h1><?= t('shop.anime.title') ?></h1>
                <p><?= t('shop.anime.subtitle') ?></p>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="products-grid" id="products-grid">
            <?php if (!empty($designs)): ?>
                <?php foreach ($designs as $design):
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
                ?>
                <div class="flip-card">
                    <?php $isFav = in_array((int)$design['id'], $favoriteDesignIds ?? [], true); ?>
                    <button type="button" class="fav-btn<?= $isFav ? ' is-favorited' : '' ?>"
                            data-kind="design" data-id="<?= (int)$design['id'] ?>"
                            aria-pressed="<?= $isFav ? 'true' : 'false' ?>"
                            data-label-on="<?= t('favorites.remove') ?>"
                            data-label-off="<?= t('favorites.add') ?>"
                            aria-label="<?= $isFav ? t('favorites.remove') : t('favorites.add') ?>"
                            title="<?= $isFav ? t('favorites.remove') : t('favorites.add') ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 5.6a5 5 0 0 0-7.1 0L12 7.3l-1.7-1.7a5 5 0 1 0-7.1 7.1l8.8 8.8 8.8-8.8a5 5 0 0 0 0-7.1z"/></svg>
                    </button>
                    <a href="/shop/design/<?= $design['id'] ?>" class="flip-card-inner" title="<?= htmlspecialchars($design['name']) ?>">
                        <!-- Front face -->
                        <div class="flip-face flip-front">
                            <?php if (!empty($design['product_image_path'])): ?>
                                <img src="/<?= htmlspecialchars($design['product_image_path']) ?>"
                                     alt="<?= htmlspecialchars($design['name']) ?>"
                                     class="face-product-img" loading="lazy">
                            <?php else: ?>
                                <div class="face-no-product"><?= t('shop.anime.no_product_image') ?></div>
                            <?php endif; ?>
                            <?php if (!empty($design['image_path'])): ?>
                                <img src="/<?= htmlspecialchars($design['image_path']) ?>"
                                     alt=""
                                     class="face-design-overlay"
                                     style="left:<?= $frontLeft ?>%;top:<?= $frontTop ?>%;width:<?= $frontW ?>%;">
                            <?php endif; ?>
                        </div>
                        <!-- Back face (only if both back images exist) -->
                        <?php if ($hasBack): ?>
                        <div class="flip-face flip-back">
                            <img src="/<?= htmlspecialchars($design['product_back_image_path']) ?>"
                                 alt="<?= htmlspecialchars($design['name']) ?> back"
                                 class="face-product-img" loading="lazy">
                            <img src="/<?= htmlspecialchars($design['back_image_path']) ?>"
                                 alt=""
                                 class="face-design-overlay"
                                 style="left:<?= $backLeft ?>%;top:<?= $backTop ?>%;width:<?= $backW ?>%;">
                        </div>
                        <?php else: ?>
                        <!-- Mirror front on back if no back design -->
                        <div class="flip-face flip-back flip-back-mirror">
                            <?php if (!empty($design['product_image_path'])): ?>
                                <img src="/<?= htmlspecialchars($design['product_image_path']) ?>"
                                     alt="" class="face-product-img" loading="lazy">
                            <?php endif; ?>
                            <?php if (!empty($design['image_path'])): ?>
                                <img src="/<?= htmlspecialchars($design['image_path']) ?>"
                                     alt=""
                                     class="face-design-overlay"
                                     style="left:<?= $frontLeft ?>%;top:<?= $frontTop ?>%;width:<?= $frontW ?>%;">
                            <?php endif; ?>
                            <div class="front-label"><?= t('shop.anime.front_view') ?></div>
                        </div>
                        <?php endif; ?>
                    </a>
                    <div class="card-info">
                        <h3 class="card-name"><?= htmlspecialchars($design['name']) ?></h3>
                        <p class="card-price"><?= I18n::t('shop.anime.from_price', ['price' => number_format($fromPrice, 2)]) ?></p>
                        <a href="/shop/design/<?= $design['id'] ?>" class="btn btn-sm card-btn"><?= t('shop.anime.view_design') ?></a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-products">
                    <div class="empty-state">
                        <h3><?= t('shop.anime.coming_title') ?></h3>
                        <p><?= nl2br(t('shop.anime.coming_text')) ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<style>
/* ============================================================
   ANIME COLLECTION — printed on blue.

   The blue ground is the shop owner's requirement, so the whole
   page is treated as a second press run: one ink (--ink-blue)
   with paper and ochre laid over it, instead of the four-stop
   navy/purple gradients, 20px radii and glowing box-shadows this
   block used to carry — none of which appear anywhere else on
   the site.

   Contrast rule for this surface, measured: paper 12.8:1 and
   ochre 6.6:1 are fine, but VERMILLION is 2.7:1. The site's spot
   ink must never be used for text or hairlines here; ochre is
   the accent on blue.
   ============================================================ */
.anime-page {
    background: var(--ink-blue);
    min-height: 100vh;
    padding-top: var(--space-6);
    padding-bottom: var(--space-8);
    position: relative;
    isolation: isolate;
}

/* Halftone screen in paper, so the blue reads as printed rather
   than as a flat CSS background colour. */
.anime-page::before {
    content: "";
    position: absolute;
    inset: 0;
    z-index: -1;
    pointer-events: none;
    background-image: radial-gradient(circle at center, var(--paper) 1.1px, transparent 1.2px);
    background-size: var(--halftone-size);
    opacity: 0.10;
}

.anime-page .breadcrumb,
.anime-page .breadcrumb a,
.anime-page .breadcrumb span { color: rgba(244, 240, 230, 0.62); }
.anime-page .breadcrumb a:hover { color: var(--ochre); }

/* ===== BANNER ===== */
.anime-hero-banner {
    position: relative;
    border-radius: var(--radius);
    overflow: hidden;
    min-height: 240px;
    display: flex;
    align-items: flex-end;
    margin-bottom: var(--space-7);
    border: 2px solid var(--paper);
    box-shadow: none;
}

.anime-bg-left,
.anime-bg-right {
    position: absolute;
    top: 0;
    width: 50%;
    height: 100%;
    object-fit: cover;
    object-position: center;
}

.anime-bg-left  { left: 0; }
.anime-bg-right { right: 0; }

/* A flat duotone wash rather than a three-stop diagonal gradient: the
   artwork stays readable underneath, and the banner belongs to the same
   blue as the page instead of introducing two more colours. */
/* Weighted to the bottom, where the type sits: the artwork stays legible
   across the top of the banner while the headline gets a solid enough
   ground to hold 12:1 contrast over a very busy collage. */
.anime-banner-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        to top,
        rgba(20, 41, 74, 0.96) 0%,
        rgba(20, 41, 74, 0.88) 42%,
        rgba(20, 41, 74, 0.55) 100%
    );
}

.anime-banner-text {
    position: relative;
    z-index: 2;
    text-align: left;
    padding: var(--space-6);
    width: 100%;
}

.anime-banner-text h1 {
    color: var(--paper);
    font-size: clamp(2rem, 7vw, 4rem);
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: var(--tracking-tight);
    line-height: 0.9;
    text-shadow: none;
    margin-bottom: var(--space-2);
}

.anime-banner-text p {
    color: rgba(244, 240, 230, 0.78);
    font-size: clamp(0.92rem, 2.2vw, 1.05rem);
    max-width: 52ch;
}

/* ===== GRID ===== */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: var(--space-5);
}

/* ===== FLIP CARD =====
   The flip is kept — it shows front and back artwork without a click and
   is genuinely useful here. What changed is the card it flips: a white
   printed sheet with an ink rule and square corners, which also keeps the
   garment mockups on the white they were shot on. */
.flip-card {
    display: flex;
    flex-direction: column;
    position: relative;
}

.flip-card-inner {
    display: block;
    position: relative;
    width: 100%;
    aspect-ratio: 1;
    perspective: 900px;
    text-decoration: none;
    border-radius: var(--radius);
    overflow: hidden;
    border: 1.5px solid var(--ink);
    box-shadow: none;
    transition: transform var(--dur) var(--ease);
    cursor: pointer;
}

.anime-page .flip-card-inner,
.anime-page .flip-card-inner:hover { box-shadow: none; }

.flip-card-inner:hover { transform: translate(-2px, -2px); }

.flip-face {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    background: var(--stock);
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    transition: transform 0.55s var(--ease);
    border-radius: 0;
    overflow: hidden;
}

.flip-front { transform: rotateY(0deg); }
.flip-back  { transform: rotateY(180deg); }
.flip-card-inner:hover .flip-front { transform: rotateY(-180deg); }
.flip-card-inner:hover .flip-back  { transform: rotateY(0deg); }

/* The flip is decorative motion: honour a reduced-motion preference by
   showing the front face only, rather than spinning the card. */
@media (prefers-reduced-motion: reduce) {
    .flip-face { transition: none; }
    .flip-card-inner:hover { transform: none; }
    .flip-card-inner:hover .flip-front { transform: rotateY(0deg); }
    .flip-card-inner:hover .flip-back  { transform: rotateY(180deg); }
}

.face-product-img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.face-design-overlay {
    position: absolute;
    transform: translate(-50%, -50%);
    height: auto;
    pointer-events: none;
    filter: drop-shadow(1px 2px 3px rgba(0,0,0,0.22));
    z-index: 2;
}

.face-no-product {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--ink-muted);
    font-family: var(--font-mono);
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: var(--tracking-caps);
}

.front-label {
    position: absolute;
    bottom: 8px;
    left: 50%;
    transform: translateX(-50%);
    background: var(--ink);
    color: var(--paper);
    font-family: var(--font-mono);
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: var(--tracking-caps);
    padding: 3px 9px;
    border-radius: 0;
    z-index: 3;
    white-space: nowrap;
}

/* ===== CARD INFO ===== */
.card-info {
    padding: var(--space-3) 0 0;
    text-align: left;
}

.anime-page .card-info { background: transparent; }

.card-name {
    font-family: var(--font-display);
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--ink);
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.anime-page .card-name { color: var(--paper); }

/* Ochre, not vermillion — see the contrast note at the top of this block. */
.card-price {
    font-family: var(--font-mono);
    color: var(--ink-soft);
    font-weight: 500;
    font-size: 0.8rem;
    margin-bottom: var(--space-3);
}

.anime-page .card-price { color: var(--ochre); }

/* The button already carries .btn .btn-sm, so it inherits the site's flat
   ink block. This used to override it back into a rounded pill with a
   coloured glow, which is why the collection looked like a different site. */
.card-btn { display: inline-flex; }

.anime-page .card-btn {
    background: var(--paper);
    color: var(--ink);
    border-color: var(--paper);
}
.anime-page .card-btn:hover {
    background: var(--ochre);
    color: var(--ink);
    border-color: var(--ochre);
}
.anime-page .card-btn:focus-visible { outline-color: var(--paper); }

/* ===== EMPTY STATE ===== */
.no-products {
    grid-column: 1 / -1;
    text-align: center;
    padding: var(--space-8) var(--space-4);
}

.empty-state {
    background: transparent;
    border: 2px dashed rgba(244, 240, 230, 0.35);
    border-radius: var(--radius);
    padding: var(--space-7);
    max-width: 460px;
    margin: 0 auto;
}

.anime-page .empty-state h3 {
    color: var(--paper);
    text-transform: uppercase;
    margin-bottom: var(--space-2);
}
.anime-page .empty-state p { color: rgba(244, 240, 230, 0.72); }

/* The favourite control is a stamped square on this page, not the soft
   white circle it inherits — a lone rounded pill on a page built from
   ruled squares is exactly the kind of leftover that makes a redesign
   look half-applied. */
.anime-page .fav-btn {
    background: var(--paper);
    border: 1.5px solid var(--ink);
    border-radius: var(--radius);
    color: var(--ink);
}
.anime-page .fav-btn.is-favorited {
    background: var(--ochre);
    color: var(--ink);
}

@media (max-width: 600px) {
    .anime-hero-banner { min-height: 180px; }
    .anime-banner-text { padding: var(--space-4); }
    .products-grid { grid-template-columns: repeat(2, 1fr); gap: var(--space-3); }
    .card-name { font-size: 1rem; }
}
</style>

<script src="<?= htmlspecialchars(Asset::url('/js/favorites.js')) ?>" defer></script>
<?php require __DIR__ . '/../layouts/customer_footer.php'; ?>
