<?php $title = t('shop.select.title', false); ?>
<?php $pageCss[] = '/css/pages/select-product.css'; require View::path('layouts/customer_header'); ?>

<section class="section select-product-section">
    <div class="container">
        <?php
            $crumbs  = [[t('header.nav.home', false), '/'], [t('header.nav.shop', false), '/shop'], [t('shop.select.breadcrumb', false), null]];
            $heading = t('shop.select.title', false);
            $lead    = t('shop.select.subtitle', false);
            require View::path('partials/page_head');
        ?>
        <?php if (empty($products)): ?>
            <div class="no-products-message">
                <p><?= t('shop.select.no_products') ?></p>
            </div>
        <?php else: ?>
        <!-- Vestigial preview column. renderProduct() fills it with the FIRST
             product on load, so before the customer picks anything the page
             showed a stray product name and an empty "Select size" dropdown
             above the grid. Clicking a card posts the choice and navigates
             away, so this is never actually used — hidden rather than deleted
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

        <?php
          // Garment family, derived from the product name. There is no category
          // column, and adding one would mean a migration plus admin UI for a
          // list of 19 — this keeps the filter honest and self-maintaining.
          $familyOf = function (string $name): string {
              $n = strtolower($name);
              if (str_contains($n, 'hood') || str_contains($n, 'sweat')) return 'hoodie';
              if (str_contains($n, 'polo'))                              return 'polo';
              if (str_contains($n, 'tank'))                              return 'tank';
              if (str_contains($n, 'v-neck'))                            return 'vneck';
              if (str_contains($n, 'long sleeve'))                       return 'longsleeve';
              if (str_contains($n, 'cap'))                               return 'cap';
              if (str_contains($n, 'shirt'))                             return 'tshirt';
              return 'other';
          };
          $familyLabels = [
              'tshirt'     => t('shop.select.family.tshirt'),
              'longsleeve' => t('shop.select.family.longsleeve'),
              'tank'       => t('shop.select.family.tank'),
              'vneck'      => t('shop.select.family.vneck'),
              'polo'       => t('shop.select.family.polo'),
              'hoodie'     => t('shop.select.family.hoodie'),
              'cap'        => t('shop.select.family.cap'),
              'other'      => t('shop.select.family.other'),
          ];
          // Only offer filters for families that actually have products.
          $present = [];
          foreach ($products as $p) { $present[$familyOf($p['name'] ?? '')] = true; }
        ?>
        <div class="picker-toolbar">
          <div class="picker-filters" role="group" aria-label="<?= t('shop.select.filter_label') ?>">
            <button type="button" class="picker-chip is-active" data-family="all"><?= t('shop.select.family.all') ?></button>
            <?php foreach ($familyLabels as $key => $label): if (empty($present[$key])) continue; ?>
            <button type="button" class="picker-chip" data-family="<?= $key ?>"><?= $label ?></button>
            <?php endforeach; ?>
          </div>
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
          foreach ([1, 5, 15, 30, 50, 100] as $tq) {
              $tiers[] = [
                  'qty'   => $tq === 1 ? t('shop.select.qty_min') : $tq . '+',
                  'price' => Pricing::unitPrice($cost, $category, $tq),
              ];
          }
          $retailOne  = $tiers[0]['price'];
          $retailBulk = $tiers[count($tiers) - 1]['price'];
        ?>
          <div class="product-list-card"
               data-product-id="<?= $product['id'] ?>"
               data-family="<?= $familyOf($product['name'] ?? '') ?>"
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
            <img src="/<?= htmlspecialchars($product['image_path']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" loading="lazy">
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
            <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
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
              <a href="#" class="pricing-link" tabindex="0"><?= t('shop.select.pricing_details') ?></a>
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
