<?php $title = t('site.brand', false); ?>
<?php require __DIR__ . '/../layouts/customer_header.php'; ?>

<?php
// One home page for everyone.
//
// There used to be two: signed-out visitors got a bare text band and a grid
// of products behind a "Sign Up to Shop" button, while signed-in customers
// got the hero, the categories and the real calls to action. That is exactly
// backwards — designing does not require an account (see the handshake
// below, which works for guests), so the page was gating the one thing that
// sells the shop. The CTA wording still adapts; the page no longer does.
$isSignedIn = Auth::check();

// How many products the grid shows before the shopper expands it. Two full
// rows on a desktop four-column grid, four rows on a phone's two-column one.
$visibleCount = 8;

?>

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
    // Expand/collapse the grid. Progressive enhancement: with no JS the eight
    // cards still render and the site's nav still reaches /shop, so nothing is
    // stranded behind a button that cannot work.
    var toggle = document.getElementById('productsToggle');
    if (toggle) {
        toggle.addEventListener('click', function () {
            var open = toggle.getAttribute('aria-expanded') === 'true';
            document.querySelectorAll('#productsGrid .is-extra').forEach(function (el) {
                el.hidden = open;
            });
            toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
            toggle.classList.toggle('is-open', !open);
            var label = toggle.querySelector('[data-toggle-label]');
            if (label) {
                label.textContent = open
                    ? toggle.getAttribute('data-label-more')
                    : toggle.getAttribute('data-label-less');
            }
        });
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

<?php // The strip a press shop letters across the top of a job sheet.
      // Duplicated once and the copy marked aria-hidden, so the marquee can
      // loop seamlessly without a screen reader reading every item twice. ?>
<div class="ticker" role="region" aria-label="<?= t('site.tagline') ?>">
    <div class="ticker-track">
        <?php for ($pass = 0; $pass < 2; $pass++): ?>
        <ul class="ticker-list"<?= $pass ? ' aria-hidden="true"' : '' ?>>
            <?php for ($i = 1; $i <= 4; $i++): ?>
            <li><?= t('home.ticker.' . $i) ?></li>
            <?php endfor; ?>
        </ul>
        <?php endfor; ?>
    </div>
</div>

<!-- ============================================================
     HERO — single column, carried by the type.
     ============================================================ -->
<section class="hero">
    <div class="container hero-split">
        <div class="hero-content">
            <p class="hero-badge"><?= t('home.hero.slug') ?></p>
            <h1>
                <?= t('home.hero.line1') ?><br>
                <span class="ink-2"><?= t('home.hero.line2') ?></span>
            </h1>
            <p><?= t('home.hero.lead') ?></p>
            <div class="hero-buttons">
                <a href="/shop/select_product" class="btn btn-lg"><?= t('home.hero.cta_design') ?></a>
                <a href="/shop/premade" class="btn btn-lg btn-secondary"><?= t('home.hero.cta_browse') ?></a>
            </div>
            <?php if (!$isSignedIn): ?>
            <p class="hero-note spec"><?= t('home.hero.no_account') ?></p>
            <?php endif; ?>
        </div>

        <?php // The bulk-pricing pitch. Deliberately no per-unit figures: a
              // tee is ~EUR 13 and a hoodie ~EUR 37, so any single ladder
              // shown here would misread as the price list for everything.
              // The percentage is computed from the margin bands in
              // CustomerController::home() and rounded down, so the "up to"
              // claim stays true if the tier table is ever edited. ?>
        <aside class="bulk-card">
            <span class="section-tag"><?= t('home.bulk.tag') ?></span>
            <h2 class="bulk-title"><?= t('home.bulk.title') ?></h2>
            <?php if (!empty($bulkSavingPct)): ?>
            <p class="bulk-save"><?= I18n::t('home.bulk.save', ['percent' => (int)$bulkSavingPct]) ?></p>
            <?php endif; ?>
            <a href="/shop/select_product" class="btn btn-block"><?= t('home.bulk.cta') ?></a>
        </aside>
    </div>
</section>

<?php // The "Shop by category" section that sat here offered exactly the two
      // destinations the hero buttons already offer — /shop/premade and
      // /shop/select_product — with longer labels. A whole section and a
      // screen of scrolling for no new information, so the hero keeps the
      // job and both routes remain reachable from /shop in the nav. ?>

<!-- ============================================================
     MOST ORDERED
     ============================================================ -->
<section class="section products-section">
    <div class="container">
        <div class="section-head">
            <div class="section-head-text">
                <span class="section-tag"><?= t('home.featured.tag') ?></span>
                <h2 class="section-title"><?= t('home.featured.title') ?></h2>
            </div>
            <?php // Expands the grid in place rather than navigating to /shop.
                  // Rendered only when there is actually something hidden, so
                  // it never invites a click that would do nothing. ?>
            <?php if (count($featuredProducts ?? []) > $visibleCount): ?>
            <button type="button" class="btn btn-secondary section-head-action"
                    id="productsToggle" aria-expanded="false" aria-controls="productsGrid"
                    data-label-more="<?= t('home.featured.view_all') ?>"
                    data-label-less="<?= t('home.featured.view_less') ?>">
                <span data-toggle-label><?= t('home.featured.view_all') ?></span>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="toggle-chevron"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <?php endif; ?>
        </div>
        <div class="products-grid" id="productsGrid">
            <?php if (!empty($featuredProducts)): ?>
                <?php foreach ($featuredProducts as $i => $product): ?>
                <?php // `hidden` rather than a CSS class: an off-screen card
                      // should be out of the tab order and out of the
                      // accessibility tree too, not merely invisible. ?>
                <div class="product-card featured-product<?= $i >= $visibleCount ? ' is-extra' : '' ?>"
                     <?= $i >= $visibleCount ? 'hidden' : '' ?>
                     data-product-id="<?= (int)$product['id'] ?>" role="link" tabindex="0" aria-label="<?= htmlspecialchars($product['name']) ?>">
                    <div class="product-image">
                        <img src="/<?= htmlspecialchars($product['image_path'] ?? '') ?>" alt="<?= htmlspecialchars($product['name']) ?>" loading="lazy" onerror="this.src='/images/placeholder.svg'">
                        <?php if (!$product['active']): ?>
                            <span class="product-badge badge-soldout"><?= t('home.featured.sold_out') ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="product-info">
                        <h3 class="product-name"><?= htmlspecialchars($product['name']) ?></h3>
                        <p class="product-price">
                            <span class="spec"><?= t('home.rate.from') ?></span>
                            €<?= number_format($product['retail_price'] ?? $product['base_price'], 2) ?>
                        </p>
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

<!-- ============================================================
     HOW IT WORKS — numbered like the steps on a job ticket.
     ============================================================ -->
<section class="section how-section">
    <div class="container">
        <div class="section-head">
            <div class="section-head-text">
                <span class="section-tag"><?= t('home.how.tag') ?></span>
                <h2 class="section-title"><?= t('home.how.title') ?></h2>
            </div>
        </div>
        <ol class="how-grid">
            <?php for ($i = 1; $i <= 3; $i++): ?>
            <?php // Headings only. The three descriptions that used to sit here
                  // explained a process the studio itself demonstrates two
                  // clicks away — and nobody reads a how-it-works explainer
                  // before deciding to try something. The headings alone carry
                  // the sequence; the detail lives in the FAQ and the
                  // assistant, where it is read by people who want it. ?>
            <li class="how-step">
                <span class="how-num"><?= sprintf('%02d', $i) ?></span>
                <h3><?= t('home.how.s' . $i . '_title') ?></h3>
            </li>
            <?php endfor; ?>
        </ol>
        <div class="how-cta">
            <a href="/shop/select_product" class="btn btn-lg"><?= t('home.hero.cta_design') ?></a>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../layouts/customer_footer.php'; ?>
