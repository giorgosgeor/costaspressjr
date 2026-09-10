<?php $title = t('shop.select.title', false); ?>
<?php require __DIR__ . '/../layouts/customer_header.php'; ?>

<section class="section select-product-section">
    <div class="container">
        <nav class="breadcrumb">
            <a href="/"><?= t('header.nav.home') ?></a> &gt;
            <a href="/shop"><?= t('header.nav.shop') ?></a> &gt;
            <span><?= t('shop.select.breadcrumb') ?></span>
        </nav>
        <div class="shop-header">
            <h1><?= t('shop.select.title') ?></h1>

        </div>
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
                     onload="this.style.display=this.getAttribute('src')?'':'none'"
                     onerror="this.style.display='none'">
                <div id="colorSwatches" style="margin:1rem 0;"></div>
            </div>
            <div class="product-options-column">
                <h3 id="productName"></h3>
                <div id="sizeOptions"></div>
               
            </div>
        </div>
        <style>
        .product-list-grid {
          display: flex;
          flex-wrap: wrap;
          gap: 10px;
          margin-bottom: 1.2rem;
        }
        .product-list-card {
          border: 1px solid #eee;
          border-radius: 10px;
          background: #fff;
          position: relative;
          min-height: 320px;
          width: 240px;
          padding: 10px 8px;
          margin: 8px;
          cursor: pointer;
          transition: box-shadow 0.2s, transform 0.2s;
          box-shadow: 0 2px 8px rgba(0,0,0,0.03);
          text-align: center;
        }
        .product-list-card:hover {
          box-shadow: 0 8px 24px rgba(102,126,234,0.18);
          transform: scale(1.04);
          z-index: 2;
        }
        .product-list-card.selected {
          border: 2px solid #15130E;
          box-shadow: 0 4px 16px rgba(102,126,234,0.08);
        }
        .product-list-card img {
          width: 100%;
          max-width: 250px;
          height: auto;
          margin-bottom: 4px;
          margin-top: 0;
          object-fit: contain;
          display: block;
          margin-left: auto;
          margin-right: auto;
        }
        .product-list-card .product-name {
          font-size: 1.05em;
          font-weight: 600;
          margin: 0 0 2px 0;
          line-height: 1.1;
        }
        .product-list-card .product-desc {
          font-size: 0.97em;
          margin: 0 0 2px 0;
          line-height: 1.15;
        }
        .product-list-card .color-preview-row {
          display: flex;
          flex-wrap: wrap;
          justify-content: center;
          align-items: center;
          gap: 4px;
          margin-bottom: 2px;
          margin-top: 2px;
          width: 100%;
          min-height: 22px;
        }
        .product-list-card .color-dot {
          width: 20px;
          height: 20px;
          margin: 0;
          border-radius: 4px;
          border: 1px solid #bbb;
          display: inline-block;
          box-sizing: border-box;
        }
        .product-list-card .color-more {
          font-size: 1em;
          margin-left: 4px;
          color: #888;
          align-self: center;
        }
        .pricing-link {
          color: #15130E;
          text-decoration: underline;
          cursor: pointer;
          font-size: 0.85em;
          padding: 0;
          font-weight: 600;
          margin-top: 2px;
          display: inline-block;
        }
        .pricing-link-wrap {
          position: relative;
          margin-top: 2px;
          text-align: center;
        }
        .pricing-popup {
          display: none;
          position: absolute;
          left: 50%;
          top: 110%;
          transform: translateX(-50%);
          min-width: 220px;
          background: #fff;
          border-radius: 12px;
          box-shadow: 0 8px 32px rgba(0,0,0,0.18);
          padding: 14px 12px 10px 12px;
          z-index: 10;
          font-size: 0.97em;
          text-align: left;
        }
        .pricing-link:focus + .pricing-popup,
        .pricing-link:hover + .pricing-popup,
        .pricing-popup:hover {
          display: block;
        }
        .pricing-popup-title {
          font-weight: 600;
          margin-bottom: 8px;
          font-size: 1em;
        }
        .pricing-table {
          width: 100%;
          border-collapse: collapse;
          margin-bottom: 0;
        }
        .pricing-table th, .pricing-table td {
          padding: 3px 6px;
          text-align: left;
          font-size: 0.95em;
        }
        .pricing-table th {
          font-weight: 700;
          background: #f7f7f7;
        }
        .pricing-table tr:not(:first-child):hover {
          background: #f0eeff;
        }
        .price-label {
          font-size: 1.13em;
          font-weight: 700;
          color: #222;
          margin-top: 4px;
          margin-bottom: 0;
          text-align: center;
        }
        .price-label .price-ea {
          font-size: 0.92em;
          font-weight: 600;
          color: #666;
          margin-left: 2px;
        }
        </style>

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
<script>
const products = <?= json_encode($products) ?>;
let selectedProduct = products[0] || null;
let selectedColor = null;
let selectedSize = null;

function renderProduct(product) {
    document.getElementById('productName').textContent = product.name;
    // Set main image to first color by default
    if (product.colors && product.colors.length > 0) {
        selectedColor = product.colors[0];
        document.getElementById('mainProductImage').src = selectedColor ? '/' + selectedColor.image_path : '';
    } else {
        selectedColor = null;
        document.getElementById('mainProductImage').src = '';
    }
    // Render color swatches
    let swatchHtml = '';
    if (product.colors) {
        product.colors.forEach(color => {
            swatchHtml += `<span class="color-swatch" data-color-id="${color.id}" style="display:inline-block;width:28px;height:28px;border-radius:50%;background:${color.hex};margin:0 4px;cursor:pointer;border:2px solid #ccc;"></span>`;
        });
    }
    document.getElementById('colorSwatches').innerHTML = swatchHtml;
    // Render size options. A product with a single size ("One Size" caps) offers
    // no actual choice, so select it silently instead of showing a dropdown.
    const sizes = product.sizes || [];
    if (sizes.length < 2) {
        selectedSize = sizes[0] || null;
        document.getElementById('sizeOptions').innerHTML = '';
        return;
    }
    selectedSize = null;
    let sizeHtml = '<label>' + (window.I18N ? window.I18N.t('shop.select.size_label') : 'Size:') + '</label><select id="sizeSelect"><option value="">' + (window.I18N ? window.I18N.t('shop.select.select_size') : 'Select size') + '</option>';
    sizes.forEach(size => {
        sizeHtml += `<option value="${size.id}">${size.size_name}</option>`;
    });
    sizeHtml += '</select>';
    document.getElementById('sizeOptions').innerHTML = sizeHtml;
}

// Initial render
if (selectedProduct) renderProduct(selectedProduct);

document.getElementById('colorSwatches').addEventListener('click', function(e) {
    if (e.target.classList.contains('color-swatch')) {
        const colorId = e.target.getAttribute('data-color-id');
        selectedColor = selectedProduct.colors.find(c => c.id == colorId);
        document.getElementById('mainProductImage').src = selectedColor ? '/' + selectedColor.image_path : '';
    }
});

document.getElementById('sizeOptions').addEventListener('change', function(e) {
    if (e.target.id === 'sizeSelect') {
        selectedSize = selectedProduct.sizes.find(s => s.id == e.target.value);
    }
});

// Product card selection logic
const productCards = document.querySelectorAll('.product-list-card');
document.querySelector('.product-list-grid').addEventListener('click', function(e) {
  // The favourite heart lives inside the card but must not select the product.
  // favorites.js already stops this in the capture phase; this keeps the card's
  // own handler honest if that script ever fails to load.
  if (e.target.closest('.fav-btn')) return;
  const card = e.target.closest('.product-list-card');
  if (card) {
    const prodId = card.getAttribute('data-product-id');
    console.log('Card clicked:', card, 'Product ID:', prodId);
    if (prodId) {
      // Send product ID to backend via POST, then redirect
      fetch('/shop/set_selected_product', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: prodId })
      }).then(res => {
        if (res.ok) {
          window.location.href = '/shop/custom_product';
        } else {
          alert(window.I18N ? window.I18N.t('shop.select.failed_select') : 'Failed to select product');
        }
      });
    } else {
      console.warn('No product ID found on card:', card);
    }
  } else {
    console.log('Click not on a product card:', e.target);
  }
});
</script>
<script src="<?= htmlspecialchars(Asset::url('/js/favorites.js')) ?>" defer></script>
<?php require __DIR__ . '/../layouts/customer_footer.php'; ?>

<script>
// ── Product picker: filter by garment family + sort ──────────────────────
// Entirely client-side: 19 products is far too few to justify a round trip,
// and instant feedback is the point. Cards carry data-family / data-price /
// data-name so no lookup table has to be kept in sync.
(function () {
    var grid = document.getElementById('productListGrid');
    if (!grid) return;
    var chips   = Array.prototype.slice.call(document.querySelectorAll('.picker-chip'));
    var sortSel = document.getElementById('pickerSort');
    var countEl = document.getElementById('pickerCount');
    var cards   = Array.prototype.slice.call(grid.querySelectorAll('.product-list-card'));
    var order   = cards.slice();            // original ("featured") order
    var family  = 'all';

    function apply() {
        var list = order.slice();
        var mode = sortSel ? sortSel.value : 'featured';
        if (mode === 'price-asc')  list.sort(function (a, b) { return pf(a) - pf(b); });
        if (mode === 'price-desc') list.sort(function (a, b) { return pf(b) - pf(a); });
        if (mode === 'name')       list.sort(function (a, b) {
            return (a.dataset.name || '').localeCompare(b.dataset.name || '');
        });

        var shown = 0;
        list.forEach(function (card) {
            var match = (family === 'all') || card.dataset.family === family;
            card.style.display = match ? '' : 'none';
            if (match) shown++;
            grid.appendChild(card);          // re-order in place
        });
        if (countEl) {
            var tpl = (window.I18N && window.I18N.t('shop.select.showing')) || '{n} products';
            countEl.textContent = tpl.replace('{n}', shown);
        }
    }
    function pf(c) { return parseFloat(c.dataset.price) || 0; }

    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            chips.forEach(function (c) { c.classList.remove('is-active'); });
            chip.classList.add('is-active');
            family = chip.dataset.family;
            apply();
        });
    });
    if (sortSel) sortSel.addEventListener('change', apply);
    apply();
})();
</script>
