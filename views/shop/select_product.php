<?php
// Every garment, or with $family (a ProductFamily key) that family's own
// landing page, /shop/hoodies: its title, description, heading and intro.
$title           = t($family !== null ? "seo.family.$family.title" : 'seo.select.title', false);
$metaDescription = t($family !== null ? "seo.family.$family.description" : 'seo.select.description', false);
$pageCss[] = '/css/pages/select-product.css';
require View::path('layouts/customer_header');
?>

<section class="section select-product-section">
    <div class="container">
        <?php
            $crumbs  = [[t('header.nav.home', false), '/'], [t('header.nav.shop', false), '/shop'], [t('shop.select.breadcrumb', false), '/shop/select_product']];
            if ($family !== null) {
                $crumbs[] = [t("seo.family.$family.h1", false), null];
            }
            $heading = t($family !== null ? "seo.family.$family.h1" : 'shop.select.title', false);
            $lead    = t('shop.select.subtitle', false);
            require View::path('partials/page_head');
        ?>
        <?php if ($family !== null): ?>
        <p class="picker-intro"><?= t("seo.family.$family.intro") ?></p>
        <?php endif; ?>
        <?php if (empty($products)): ?>
            <div class="no-products-message">
                <p><?= t('shop.select.no_products') ?></p>
            </div>
        <?php else: ?>
        <!-- Vestigial preview column. renderProduct() fills it with the FIRST
             product on load, so before the customer picks anything the page
             showed a stray product name and an empty "Select size" dropdown
             above the grid. Each card links to the product's own page, so
             this is never actually used — hidden rather than deleted
             because the render/JS below still references these nodes. -->
        <div class="product-select-layout" style="display:none;">
            <div class="product-preview-column">
                <img id="mainProductImage" src="" alt=""
                     style="max-width:350px;max-height:350px;display:none;"
                     data-show-on-load
                     data-hide-on-error>
                <div id="colorSwatches" style="margin:1rem 0;"></div>
            </div>
            <div class="product-options-column">
                <h3 id="productName"></h3>
                <div id="sizeOptions"></div>
               
            </div>
        </div>

        <div class="picker-toolbar">
          <?php // Each garment family has its own page (ProductFamily), so the
                // filters are links: shareable, and how search engines find them.
                // $families lists those with something in them. ?>
          <nav class="picker-filters" aria-label="<?= t('shop.select.filter_label') ?>">
            <a href="/shop/select_product" class="picker-chip<?= $family === null ? ' is-active' : '' ?>"<?= $family === null ? ' aria-current="page"' : '' ?>><?= t('shop.select.family.all') ?></a>
            <?php foreach ($families as $key): ?>
            <a href="<?= e(ProductFamily::path($key)) ?>" class="picker-chip<?= $family === $key ? ' is-active' : '' ?>"<?= $family === $key ? ' aria-current="page"' : '' ?>><?= t("shop.select.family.$key") ?></a>
            <?php endforeach; ?>
          </nav>
          <label class="picker-sort">
            <span><?= t('shop.select.sort_label') ?></span>
            <select id="pickerSort">
              <option value="featured"><?= t('shop.select.sort_featured') ?></option>
              <option value="price-asc"><?= t('shop.select.sort_price_asc') ?></option>
              <option value="price-desc"><?= t('shop.select.sort_price_desc') ?></option>
              <option value="name"><?= t('shop.select.sort_name') ?></option>
            </select>
          </label>
        </div>
        <p class="picker-count" id="pickerCount" aria-live="polite"></p>

        <div class="product-list-grid" id="productListGrid">
        <?php foreach ($products as $product):
          // Real quantity tiers from the pricing engine — the same maths
          // add-to-cart charges. Computed here so the qty-1 price can drive
          // client-side sorting via data-price.
          $cost      = (float)$product['base_price'];
          $category  = Pricing::categoryFor($product['slug'] ?? '', $product['name'] ?? '');
          $tiers     = [];
          foreach (Pricing::priceBreaks($category) as $tq) {
              $tiers[] = [
                  'qty'   => $tq === 1 ? t('shop.select.qty_min') : $tq . '+',
                  'price' => Pricing::unitPrice($cost, $category, $tq),
              ];
          }
          $retailOne  = $tiers[0]['price'];
          $retailBulk = $tiers[count($tiers) - 1]['price'];
          $href       = product_path((int)$product['id'], $product['slug']);
        ?>
          <div class="product-list-card"
               data-product-id="<?= $product['id'] ?>"
               data-name="<?= htmlspecialchars($product['name'] ?? '') ?>"
               data-price="<?= number_format($retailOne, 2, '.', '') ?>">
            <?php $isFav = in_array((int)$product['id'], $favoriteProductIds ?? [], true); ?>
            <button type="button" class="fav-btn<?= $isFav ? ' is-favorited' : '' ?>"
                    data-kind="product" data-id="<?= (int)$product['id'] ?>"
                    aria-pressed="<?= $isFav ? 'true' : 'false' ?>"
                    data-label-on="<?= t('favorites.remove') ?>"
                    data-label-off="<?= t('favorites.add') ?>"
                    aria-label="<?= $isFav ? t('favorites.remove') : t('favorites.add') ?>"
                    title="<?= $isFav ? t('favorites.remove') : t('favorites.add') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 5.6a5 5 0 0 0-7.1 0L12 7.3l-1.7-1.7a5 5 0 1 0-7.1 7.1l8.8 8.8 8.8-8.8a5 5 0 0 0 0-7.1z"/></svg>
            </button>
            <span class="product-list-media garment-stage"><img src="/<?= htmlspecialchars($product['image_path']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" width="1024" height="1024" loading="lazy"></span>
            <div class="color-preview-row">
              <?php
                // Collect all unique color hexes for this product across all sizes
                $allHexes = [];
                if (!empty($product['sizes'])) {
                  foreach ($product['sizes'] as $size) {
                    if (!empty($size['color_hexes'])) {
                      foreach (explode(',', $size['color_hexes']) as $hex) {
                        $hex = trim($hex);
                        if ($hex && !in_array($hex, $allHexes)) {
                          $allHexes[] = $hex;
                        }
                      }
                    }
                  }
                }
                $maxDots = 8;
                $shown = 0;
                foreach ($allHexes as $hex) {
                  if ($shown >= $maxDots) break;
                  echo '<span class="color-dot" style="background:' . htmlspecialchars($hex) . '"></span>';
                  $shown++;
                }
                $remaining = count($allHexes) - $shown;
                if ($remaining > 0) {
                  echo '<span class="color-more">+' . $remaining . '</span>';
                }
              ?>
            </div>
            <?php // The card holds buttons, so it can't be one big <a>: the name is
                  // the link, stretched over the card in select-product.css. ?>
            <a href="<?= e($href) ?>" class="product-name product-link"><?= htmlspecialchars($product['name']) ?></a>
            <div class="product-desc">
              <?= htmlspecialchars($product['description'] ?? '') ?>
            </div>
            <div class="price-label">&euro;<?= number_format($retailOne, 2) ?><span class="price-ea"><?= t('shop.select.price_per_unit') ?></span></div>
            <?php if ($retailBulk < $retailOne - 0.005): ?>
            <!-- The volume discount is the strongest selling point here, so show
                 it on the card rather than only inside the hover popup. -->
            <div class="price-bulk"><?= I18n::t('shop.select.bulk_from', [
                'price' => '<strong>&euro;' . number_format($retailBulk, 2) . '</strong>',
                'qty'   => 100,
            ]) ?></div>
            <?php endif; ?>
            <div class="pricing-link-wrap">
              <a href="<?= e($href) ?>" class="pricing-link"><?= t('shop.select.pricing_details') ?></a>
              <div class="pricing-popup">
                <div class="pricing-popup-title"><?= t('shop.select.pricing_title') ?></div>
                <table class="pricing-table">
                  <tr><th><?= t('shop.select.pricing_qty') ?></th><th><?= t('shop.select.pricing_per_item') ?></th></tr>
                  <?php foreach ($tiers as $tier): ?>
                  <tr><td><?= htmlspecialchars((string)$tier['qty']) ?></td><td>&euro;<?= number_format($tier['price'], 2) ?></td></tr>
                  <?php endforeach; ?>
                </table>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
<?= View::json('select-product-data', ['products' => $products]) ?>
<?= View::script('/js/pages/select-product.js') ?>
<script src="<?= htmlspecialchars(Asset::url('/js/lib/favorites.js')) ?>" defer></script>
<?php require View::path('layouts/customer_footer'); ?>

<?= View::script('/js/pages/select-product-sort.js') ?>
