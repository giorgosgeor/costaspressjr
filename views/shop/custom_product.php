<?php $title = $product['name'] ?? 'Customize Product'; ?>
<?php $pageCss[] = '/css/pages/custom-product.css'; require View::path('layouts/customer_header'); ?>



<section class="section product-detail-section">
    <div class="container">
        <?php
            $crumbs = [[t('header.nav.home', false), '/'], [t('header.nav.shop', false), '/shop'], [(string)($product['name'] ?? t('studio.title', false)), null]];
            require View::path('partials/breadcrumb');
        ?>
        <div class="custom-product-grid">
            <div class="custom-product-gallery" id="customProductGallery">
                <div id="productThumbnails"></div>
                <div class="main-image">
                    <img id="mainProductImage" src="<?= isset($product['image_path']) ? '/' . htmlspecialchars($product['image_path']) : '/images/placeholder.svg' ?>" alt="<?= htmlspecialchars($product['name'] ?? '') ?>">
                </div>
            </div>
            <!-- Right: Product Info & Options -->
            <div class="custom-product-info" style="flex:1;min-width:320px;">
                <h1 style="font-size:2em;font-weight:700;"><?= htmlspecialchars($product['name'] ?? '') ?></h1>
                <!-- Removed rating, free delivery, and decoration sections -->
                <div style="margin-bottom:18px;">
                    <b><?= t('custom_product.colors') ?></b>
                    <div id="colorSwatches" style="display:flex;gap:6px;margin-top:6px;margin-bottom:8px;"></div>
                    <a href="#" id="checkSizesLink" style="font-size:0.98em;text-decoration:underline;"><?= t('custom_product.check_sizes') ?></a>
                </div>
                <div style="margin-bottom:18px;">
                    <span style="font-weight:400;font-size:1em; display:inline-flex; align-items:center; gap:12px; width:100%; justify-content:space-between;">
                        <b><?= t('custom_product.sizes') ?></b>
                        <?php if (!empty($product['size_chart_image'])): ?>
                        <a href="#"
                           data-on-click="openSizeGuide" data-prevent-default data-args="<?= e(json_encode([$product['size_chart_image'], $product['name'] ?? 'Product'])) ?>"
                           style="font-size:0.82em; color:#2A4FE0; text-decoration:none; font-weight:500;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="vertical-align:-2px;margin-right:4px;"><path d="M2 12h20"/><path d="M6 9v6M10 7v10M14 9v6M18 7v10"/></svg>Size guide
                        </a>
                        <?php endif; ?>
                    </span>
                    <div id="sizeOptions" style="display:flex;gap:-12px;margin-top:6px;"></div>
                <?php // A stray, unmatched STYLE end tag sat here in the original markup.
                      // The parser discards such a tag, so the elements around it still
                      // balanced and it was inert — it was NOT a mistyped closing div.
                      // Turning it into a real </div> closed .custom-product-info early,
                      // which made every block below it a flex sibling of the gallery and
                      // laid them out in a row across the desktop page. It is simply
                      // removed instead. ?>
                </div>
                <!-- Price. This page previously showed none at all — a customer
                     arriving from a featured card had no idea what it cost. The
                     widget also surfaces the volume ladder, computed with the
                     same engine the server charges with. -->
                <div class="price-qty-row">
                    <label for="tierQty"><?= t('pricing.qty') ?></label>
                    <div class="quantity-controls">
                        <button type="button" class="qty-btn" id="tierQtyMinus" aria-label="-">&minus;</button>
                        <input type="number" id="tierQty" class="qty-input" value="1" min="1" max="1000" inputmode="numeric">
                        <button type="button" class="qty-btn" id="tierQtyPlus" aria-label="+">+</button>
                    </div>
                </div>
                <div class="price-tiers"
                     id="productPriceTiers"
                     data-supplier-cost="<?= htmlspecialchars((string)($product['base_price'] ?? 0)) ?>"
                     data-product-name="<?= htmlspecialchars($product['name'] ?? '') ?>"
                     data-product-slug="<?= htmlspecialchars($product['slug'] ?? '') ?>"
                     data-quantity="1"></div>

                <button id="startDesigningBtn" class="btn btn-primary" style="margin-top:18px;min-width:180px;"><?= t('custom_product.start') ?></button>
				<div style="margin-top:18px;color:#555;font-size:1em;">
					<b><?= t('custom_product.delivery') ?></b> <?= t('custom_product.delivery_expected') ?> <span id="deliveryDate"><?= htmlspecialchars($deliveryEstimate ?? '') ?></span> &bull;
				</div>
			</div>
            <!-- Size Matrix Modal -->
<div id="sizeMatrixModal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:1000;align-items:center;justify-content:center;background:rgba(0,0,0,0.35);">
    <div style="background:#fff;padding:32px 36px 32px 36px;border-radius:12px;max-width:98vw;max-height:92vh;overflow:auto;position:relative;box-shadow:0 8px 32px rgba(0,0,0,0.18);min-width:340px;">
        <button id="closeMatrixModal" style="position:absolute;top:18px;right:18px;font-size:2em;background:none;border:none;cursor:pointer;line-height:1;">&times;</button>
        <h2 style="margin-top:0;font-size:2em;font-weight:700;"><?= htmlspecialchars($product['name'] ?? 'Product') ?></h2>
        <div style="color:#444;font-size:1.1em;margin-bottom:2px;"><?= I18n::t('custom_product.matrix.availability', ['date' => date('F j, Y')]) ?></div>
        <div style="color:#888;font-size:0.98em;margin-bottom:18px;"><?= t('custom_product.matrix.na_note') ?></div>
        <div id="sizeMatrixTable"></div>
    </div>
</div>
        </div>
    </div>
		</div>

<?= View::json('custom-product-data', ['product' => $product ?? [], 'colors' => $colors ?? [], 'sizes' => $sizes ?? [], 'colorSizeMatrix' => $colorSizeMatrix ?? [], 'thumbnails' => $thumbnails ?? []]) ?>
<?= View::script('/js/pages/custom-product.js') ?>
<?php require View::path('partials/size_guide_modal'); ?>
<?php // ---- Sticky action bar (phones only) ----------------------------
      // On a phone the real call to action sits below the colour row, the
      // size run, the quantity stepper and the price panel — far enough
      // down that a shopper scrolling back up to look at the garment loses
      // it entirely. Pinning price and button to the bottom edge keeps the
      // decision one thumb-reach away at all times.
      //
      // It does not duplicate any logic: the button forwards the tap to the
      // existing #startDesigningBtn, so there is exactly one code path for
      // starting a design, and the price mirrors whatever lib/price-tiers.js
      // has rendered rather than recomputing it. ?>
<div class="mobile-action-bar" role="region" aria-label="<?= t('custom_product.start') ?>">
    <span class="mab-price">
        <span class="mab-label"><?= t('home.rate.from') ?></span>
        <span class="mab-amount" id="mabAmount">&euro;<?= number_format((float)($retailPrice ?? 0), 2) ?></span>
    </span>
    <button type="button" class="btn" id="mabStart"><?= t('custom_product.start') ?></button>
</div>
<?= View::script('/js/pages/custom-product-action-bar.js') ?>

<?php require View::path('layouts/customer_footer'); ?>
