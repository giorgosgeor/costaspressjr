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
                    $fromPrice = ($design['product_base_price'] ?? 0) + $design['price'];
                ?>
                <div class="flip-card">
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
/* ===== ANIME PAGE BACKGROUND ===== */
.anime-page {
    background: linear-gradient(160deg, #1a3a6e 0%, #1b3560 35%, #1e3d72 60%, #2a2d7a 100%);
    min-height: 100vh;
    padding-top: 40px;
    padding-bottom: 60px;
}

.anime-page .breadcrumb a,
.anime-page .breadcrumb span {
    color: rgba(255,255,255,0.5);
}

.anime-page .breadcrumb a:hover {
    color: #15130E;
}

/* ===== ANIME HERO BANNER ===== */
.anime-hero-banner {
    position: relative;
    border-radius: 20px;
    overflow: hidden;
    height: 280px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 48px;
    box-shadow: 0 8px 40px rgba(0,0,60,0.5);
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

.anime-banner-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        135deg,
        rgba(20, 18, 48, 0.82) 0%,
        rgba(26, 58, 110, 0.72) 50%,
        rgba(20, 18, 48, 0.82) 100%
    );
}

.anime-banner-text {
    position: relative;
    z-index: 2;
    text-align: center;
    padding: 0 40px;
}

.anime-banner-text h1 {
    color: #fff;
    font-size: 2.8rem;
    font-weight: 800;
    letter-spacing: -0.5px;
    text-shadow: 0 0 40px rgba(125,128,218,0.6);
    margin-bottom: 10px;
}

.anime-banner-text p {
    color: rgba(255,255,255,0.65);
    font-size: 1.05rem;
}

@media (max-width: 700px) {
    .anime-hero-banner { height: 200px; }
    .anime-banner-text { padding: 0 20px; }
    .anime-banner-text h1 { font-size: 1.8rem; }
}

.anime-page .card-name { color: #e8e8f4; }

.anime-page .flip-card-inner {
    box-shadow: 0 4px 20px rgba(0,0,60,0.4);
}

.anime-page .flip-card-inner:hover {
    box-shadow: 0 8px 32px rgba(125,128,218,0.35);
}

.anime-page .card-info {
    background: transparent;
}

.anime-page .empty-state {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.7);
}

.anime-page .empty-state h3,
.anime-page .empty-state p { color: rgba(255,255,255,0.7); }

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 30px;
}

/* ===== FLIP CARD ===== */
.flip-card {
    display: flex;
    flex-direction: column;
}

.flip-card-inner {
    display: block;
    position: relative;
    width: 100%;
    aspect-ratio: 1;
    perspective: 800px;
    text-decoration: none;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    transition: box-shadow 0.3s;
    cursor: pointer;
}

.flip-card-inner:hover {
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
}

.flip-face {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    background: #f5f5f5;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 12px;
    overflow: hidden;
}

.flip-front {
    transform: rotateY(0deg);
}

.flip-back {
    transform: rotateY(180deg);
}

.flip-card-inner:hover .flip-front {
    transform: rotateY(-180deg);
}

.flip-card-inner:hover .flip-back {
    transform: rotateY(0deg);
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
    filter: drop-shadow(1px 2px 4px rgba(0,0,0,0.25));
    z-index: 2;
}

.face-no-product {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #aaa;
    font-size: 0.9rem;
}

.front-label {
    position: absolute;
    bottom: 8px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0,0,0,0.5);
    color: white;
    font-size: 11px;
    padding: 3px 10px;
    border-radius: 10px;
    z-index: 3;
    white-space: nowrap;
}

/* Card info below flip area */
.card-info {
    padding: 12px 4px 4px;
    text-align: center;
}

.card-name {
    font-size: 1rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.card-price {
    color: #e74c3c;
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 8px;
}

.card-btn {
    display: inline-block;
    padding: 7px 18px;
    font-size: 0.85rem;
    border-radius: 20px;
    background: var(--ink);
    color: white;
    text-decoration: none;
    transition: transform 0.2s, box-shadow 0.2s;
}

.card-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102,126,234,0.4);
}

.no-products {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
}

.empty-state {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 40px;
    max-width: 400px;
    margin: 0 auto;
}

.empty-state h3 {
    font-size: 1.5rem;
    margin-bottom: 10px;
    color: #333;
}

.empty-state p {
    color: #666;
    line-height: 1.6;
}

@media (max-width: 600px) {
    .shop-header h1 {
        font-size: 2rem;
    }

    .products-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
}
</style>

<?php require __DIR__ . '/../layouts/customer_footer.php'; ?>
