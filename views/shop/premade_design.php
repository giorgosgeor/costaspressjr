<?php $title = htmlspecialchars($design['name']) . ' - ' . htmlspecialchars($design['section_name']); ?>
<?php $pageCss[] = '/css/pages/premade-design.css'; require View::path('layouts/customer_header'); ?>

<!-- Interact.js for drag & resize (only loaded for non-fixed designs) -->
<?php if (empty($design['is_fixed'])): ?>
<script src="/js/vendor/interact.min.js"></script>
<?php endif; ?>

<section class="section design-section">
    <div class="container">
        <?php
            $crumbs = [
                [t('header.nav.home', false), '/'],
                [t('header.nav.shop', false), '/shop'],
                [t('view_design.breadcrumb.premade', false), '/shop/premade'],
                [(string)$design['section_name'], '/shop/premade/' . $design['section_slug']],
                [(string)$design['name'], null],
            ];
            require View::path('partials/breadcrumb');
        ?>

        <div class="design-page">
            <!-- Product Mockup Preview Column -->
            <div class="design-preview-column">
                <!-- Placement switcher: dots (left) + current view name (right).
                     The dots are the original side buttons restyled — same ids,
                     data-side and .active handling, so existing JS is unchanged. -->
                <div class="side-toggle" id="sideToggle">
                    <div class="side-dots" id="sideDots" role="tablist" aria-label="<?= t('view_design.placement') ?>">
                        <button type="button" class="side-btn active" data-side="front" id="chooseFrontBtn" data-label="<?= t('studio.view.front') ?>" aria-label="<?= t('studio.view.front') ?>" title="<?= t('studio.view.front') ?>"></button>
                        <button type="button" class="side-btn" data-side="back" id="chooseBackBtn" style="display:none;" data-label="<?= t('studio.view.back') ?>" aria-label="<?= t('studio.view.back') ?>" title="<?= t('studio.view.back') ?>"></button>
                        <button type="button" class="side-btn" data-side="left-sleeve" id="chooseLeftSleeveBtn" style="display:none;" data-label="<?= t('studio.view.left_sleeve') ?>" aria-label="<?= t('studio.view.left_sleeve') ?>" title="<?= t('studio.view.left_sleeve') ?>"></button>
                        <button type="button" class="side-btn" data-side="right-sleeve" id="chooseRightSleeveBtn" style="display:none;" data-label="<?= t('studio.view.right_sleeve') ?>" aria-label="<?= t('studio.view.right_sleeve') ?>" title="<?= t('studio.view.right_sleeve') ?>"></button>
                    </div>
                    <span class="side-current-label" id="sideCurrentLabel" aria-live="polite"><?= t('studio.view.front') ?></span>
                </div>
                <?php if (empty($design['is_fixed'])): ?>
                <div class="second-design-option">
                    <input type="checkbox" id="addSecondDesign" />
                    <label for="addSecondDesign"><?= t('view_design.second_design', false, ['side' => '<span id="oppositeSideLabel">' . t('view_design.side.back', false) . '</span>', 'price' => '<span id="secondDesignPrice">' . number_format($design['price'], 2) . '</span>']) ?></label>
                </div>
                <div id="secondDesignUpload" class="second-design-upload" style="display:none;">
                    <label for="secondDesignFile"><?= t('view_design.second_upload', false, ['side' => '<span id="secondSideUploadLabel">' . t('view_design.side.back_cap', false) . '</span>']) ?></label>
                    <input type="file" id="secondDesignFile" accept="image/*">
                    <div id="secondDesignPreview" class="second-design-preview"></div>
                </div>
                <?php else: ?>
                <!-- Hidden placeholders so JS doesn't error on fixed designs -->
                <input type="checkbox" id="addSecondDesign" style="display:none;" disabled />
                <input type="file" id="secondDesignFile" style="display:none;" disabled>
                <span id="oppositeSideLabel" style="display:none;"><?= t('view_design.side.back', false) ?></span>
                <span id="secondDesignPrice" style="display:none;"><?= number_format($design['price'], 2) ?></span>
                <span id="secondSideUploadLabel" style="display:none;"><?= t('view_design.side.back_cap', false) ?></span>
                <div id="secondDesignUpload" style="display:none;"></div>
                <?php endif; ?>
                
                <div class="mockup-container" id="mockupContainer">
                    <!-- Color overlay layer -->
                    <div class="color-overlay" id="colorOverlay"></div>
                    
                    <!-- Product image -->
                    <img src="/<?= htmlspecialchars($availableProducts[0]['image_path'] ?? '') ?>" 
                         alt="Product" 
                         class="mockup-product" 
                         id="mockupProduct"
                         data-front="<?= htmlspecialchars($availableProducts[0]['image_path'] ?? '') ?>"
                         data-back="<?= htmlspecialchars($availableProducts[0]['back_image_path'] ?? '') ?>"
                         style="<?= empty($availableProducts[0]['image_path']) ? 'display:none;' : '' ?>">
                    
                    <?php if (empty($availableProducts[0]['image_path'])): ?>
                        <div class="mockup-placeholder"><?= t('view_design.product_image') ?></div>
                    <?php endif; ?>
                    
                    <!-- Design placement area (fixed box - design moves inside) -->
                    <div class="design-area <?= !empty($design['is_fixed']) ? 'design-area-fixed' : '' ?>" id="designArea">
                        <?php if (empty($design['is_fixed'])): ?>
                        <div class="design-area-label"><?= t('studio.design_area') ?></div>
                        <?php endif; ?>
                        <?php if (!empty($design['image_path'])): ?>
                            <div class="design-element <?= !empty($design['is_fixed']) ? 'design-element-fixed' : '' ?>" id="designElement">
                                <img src="/<?= htmlspecialchars($design['image_path']) ?>"
                                     alt="<?= htmlspecialchars($design['name']) ?>"
                                     class="mockup-design"
                                     id="mockupDesign">
                                <?php if (empty($design['is_fixed'])): ?>
                                <div class="resize-handle"></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (empty($design['is_fixed'])): ?>
                <!-- Design controls (only for non-fixed designs) -->
                <div class="design-controls">
                    <button type="button" class="control-btn" data-on-click="resetDesignPosition" title="<?= t('view_design.reset') ?>">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="vertical-align:-2px;margin-right:5px;"><path d="M3 2v6h6"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L3 8"/></svg><?= t('view_design.reset') ?>
                    </button>
                    <button type="button" class="control-btn" data-on-click="centerDesign" title="<?= t('view_design.center') ?>">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="vertical-align:-2px;margin-right:5px;"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="2.5"/></svg><?= t('view_design.center') ?>
                    </button>
                </div>
                <?php endif; ?>
                
                <!-- Design only preview (small) -->
                <div class="design-only-preview">
                    <h4><?= t('view_design.preview') ?></h4>
                    <div class="design-thumbnail">
                        <?php if (!empty($design['image_path'])): ?>
                            <img src="/<?= htmlspecialchars($design['image_path']) ?>" alt="<?= htmlspecialchars($design['name']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="no-image-placeholder"><?= t('view_design.no_image') ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="design-info">
                    <h1><?= htmlspecialchars($design['name']) ?></h1>
                    <span class="section-tag"><?= $design['section_icon'] ?> <?= htmlspecialchars($design['section_name']) ?></span>
                    <?php if ($design['description']): ?>
                        <p class="design-description"><?= nl2br(htmlspecialchars($design['description'])) ?></p>
                    <?php endif; ?>
                    <p class="design-price-note"><?= t('view_design.price_note') ?> <strong>+€<?= number_format($design['price'], 2) ?></strong></p>
                </div>
            </div>

            <!-- Product Selection Column -->
            <div class="product-selection-column">
                <h2><?= t('view_design.choose_product') ?></h2>
                <p class="selection-subtitle"><?= t('view_design.choose_subtitle') ?></p>

                <?php if (empty($availableProducts)): ?>
                    <div class="no-products-message">
                        <p><?= t('view_design.no_products') ?></p>
                    </div>
                <?php else: ?>
                    <div class="product-options">
                        <?php foreach ($availableProducts as $index => $product): ?>
                        <div class="product-option <?= $index === 0 ? 'selected' : '' ?>"
                             data-product-id="<?= $product['id'] ?>"
                             data-base-price="<?= $product['base_price'] ?>"
                             data-product-name="<?= htmlspecialchars($product['name']) ?>"
                             data-product-image="<?= htmlspecialchars($product['image_path'] ?? '') ?>"
                             data-product-back-image="<?= htmlspecialchars($product['back_image_path'] ?? '') ?>"
                             data-left-sleeve-image="<?= htmlspecialchars($product['left_sleeve_image_path'] ?? '') ?>"
                             data-right-sleeve-image="<?= htmlspecialchars($product['right_sleeve_image_path'] ?? '') ?>"
                             data-size-chart="<?= htmlspecialchars($product['size_chart_image'] ?? '') ?>"
                             data-pos-x="<?= (float)($product['design_pos_x'] ?? 0) ?>"
                             data-pos-y="<?= (float)($product['design_pos_y'] ?? 0) ?>"
                             data-pos-size="<?= (float)($product['design_pos_size'] ?? 55) ?>"
                             data-pos-back-x="<?= (float)($product['design_pos_back_x'] ?? 0) ?>"
                             data-pos-back-y="<?= (float)($product['design_pos_back_y'] ?? 0) ?>"
                             data-pos-back-size="<?= (float)($product['design_pos_back_size'] ?? 55) ?>">
                            <div class="product-option-image">
                                <?php if (!empty($product['image_path'])): ?>
                                    <img src="/<?= htmlspecialchars($product['image_path']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="no-image-small"><?= t('view_design.no_image') ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="product-option-info">
                                <h3><?= htmlspecialchars($product['name']) ?></h3>
                                <p class="product-base-price"><?= I18n::t('view_design.from_price', ['price' => number_format(($product['retail_price'] ?? $product['base_price']) + $design['price'], 2)]) ?></p>
                            </div>
                            <div class="product-option-check">
                                <span class="checkmark">✓</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php
// Collect unique colors and sizes per product for preview
$previewColors = [];
$previewAllSizes = [];   // prodId => [{id, name}, ...]
$previewColorSizes = []; // prodId => {colorId => [sizeId, ...]}
foreach ($availableProducts as $prod):
    $pid = $prod['id'];
    $prodColors = [];
    $allSizesMap = []; // sizeId => size_name (ordered)
    $colorSizeMap = []; // colorId => [sizeId, ...]
    foreach ($prod['sizes'] as $sz):
        $sid   = $sz['id'];
        $sname = $sz['size_name'];
        $allSizesMap[$sid] = $sname;
        $cIds   = $sz['color_ids']   ? explode(',', $sz['color_ids'])   : [];
        $cNames = $sz['color_names'] ? explode(',', $sz['color_names']) : [];
        $cHexes = $sz['color_hexes'] ? explode(',', $sz['color_hexes']) : [];
        foreach ($cIds as $ci => $cid):
            $cid = trim($cid);
            if (!$cid) continue;
            if (!isset($prodColors[$cid])) {
                $prodColors[$cid] = [
                    'hex'  => trim($cHexes[$ci] ?? ''),
                    'name' => trim($cNames[$ci] ?? '')
                ];
            }
            if (!isset($colorSizeMap[$cid])) $colorSizeMap[$cid] = [];
            if (!in_array($sid, $colorSizeMap[$cid])) $colorSizeMap[$cid][] = $sid;
        endforeach;
    endforeach;
    $previewColors[$pid]     = $prodColors;
    $previewAllSizes[$pid]   = $allSizesMap;
    $previewColorSizes[$pid] = $colorSizeMap;
endforeach;
?>

<!-- Preview Color Swatches (for tinting preview only — size/color for order selected in Add to Cart modal) -->
<div class="customization-section" id="customizationSection">
    <?php foreach ($availableProducts as $index => $product): ?>
    <div class="product-customization"
         id="customization-<?= $product['id'] ?>"
         style="<?= $index === 0 ? '' : 'display: none;' ?>">

        <?php if (!empty($previewColors[$product['id']])): ?>
        <div class="preview-color-selection">
            <h4><?= t('view_design.preview_color') ?> <span class="preview-hint"><?= t('view_design.preview_hint_color') ?></span></h4>
            <div class="fixed-color-swatches" id="preview-colors-<?= $product['id'] ?>">
                <?php $first = true; foreach ($previewColors[$product['id']] as $cid => $color):
                    $hex   = htmlspecialchars($color['hex']);
                    $cname = htmlspecialchars($color['name']);
                    $isWhite = strtolower($color['hex']) === '#ffffff' || strtolower($color['name']) === 'white';
                ?>
                <label class="fixed-color-label" title="<?= $cname ?>">
                    <input type="radio" name="preview_color_<?= $product['id'] ?>" value="<?= $cid ?>"
                           data-hex="<?= $hex ?>" data-name="<?= $cname ?>"
                           <?= $first ? 'checked' : '' ?>>
                    <span class="fixed-swatch <?= $isWhite ? 'is-white' : '' ?>" style="background-color:<?= $hex ?>"></span>
                    <span class="fixed-color-name"><?= $cname ?></span>
                </label>
                <?php $first = false; endforeach; ?>
            </div>
        </div>

        <?php if (!empty($previewAllSizes[$product['id']])): ?>
        <div class="preview-size-display" id="preview-sizes-<?= $product['id'] ?>">
            <h4>
                <?= t('view_design.preview_sizes') ?>
                <span class="preview-hint"><?= t('view_design.preview_hint_sizes') ?></span>
                <?php if (!empty($product['size_chart_image'])): ?>
                <a href="#" class="size-guide-link"
                   data-on-click="openSizeGuide" data-prevent-default data-args="<?= e(json_encode([$product['size_chart_image'], $product['name']])) ?>">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 12h20"/><path d="M6 9v6M10 7v10M14 9v6M18 7v10"/></svg><?= t('footer.size_guide') ?>
                </a>
                <?php endif; ?>
            </h4>
            <div class="preview-size-chips">
                <?php
                // Get the first selected color's available sizes
                $firstColorId = array_key_first($previewColors[$product['id']] ?? []);
                $firstColorSizes = $previewColorSizes[$product['id']][$firstColorId] ?? [];
                foreach ($previewAllSizes[$product['id']] as $sid => $sname):
                    $available = in_array($sid, $firstColorSizes);
                ?>
                <span class="preview-size-chip <?= $available ? 'available' : 'unavailable' ?>"
                      data-size-id="<?= $sid ?>"><?= htmlspecialchars($sname) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <p class="no-variants"><?= t('view_design.no_variants') ?></p>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

                    <!-- Price Summary & Add to Cart -->
                    <div class="order-summary">
                        <div class="price-breakdown">
                            <div class="price-row">
                                <span><?= t('view_design.price.base') ?></span>
                                <span id="basePrice">€<?= number_format($availableProducts[0]['retail_price'] ?? $availableProducts[0]['base_price'] ?? 0, 2) ?></span>
                            </div>
                            <div class="price-row">
                                <span><?= t('view_design.price.design') ?></span>
                                <span id="designPriceValue">+€<?= number_format($design['price'], 2) ?></span>
                            </div>
<div class="price-row" id="secondDesignRow" style="display:none;">
                                <span><?= t('view_design.price.second_side') ?></span>
                                <span id="secondDesignCost">+€<?= number_format($design['price'], 2) ?></span>
                            </div>
                            <div class="price-row total">
                                <span id="totalLabel"><?= t('view_design.price.total') ?></span>
                                <span id="totalPrice">€<?= number_format(($availableProducts[0]['retail_price'] ?? $availableProducts[0]['base_price'] ?? 0) + $design['price'], 2) ?></span>
                            </div>
                        </div>

                        <div class="quantity-row">
                            <label for="quantity"><?= t('studio.cart.quantity') ?></label>
                            <div class="quantity-control">
                                <button type="button" class="qty-btn" data-on-click="changeQuantity" data-args='[-1]'>−</button>
                                <input type="number" id="quantity" name="quantity" value="1" min="1" max="99">
                                <button type="button" class="qty-btn" data-on-click="changeQuantity" data-args='[1]'>+</button>
                            </div>
                        </div>

                        <button type="button" class="btn btn-lg btn-block btn-add-cart" data-on-click="addToCart">
                            <?= t('studio.cart.add') ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php // Add-to-cart confirmation: printed by the footer after </main> (see $overlays).
ob_start(); ?>
<!-- Confirm Add to Cart Modal -->
<div id="confirmCartModal" class="confirm-cart-overlay popup-overlay" data-on-click="closeConfirmCart" data-click-self>
    <div class="confirm-cart-box popup" role="dialog" aria-modal="true" aria-labelledby="confirmCartTitle">
        <button type="button" class="popup-close" data-on-click="closeConfirmCart" aria-label="<?= t('common.close') ?>">&times;</button>
        <h2 class="popup-title" id="confirmCartTitle"><?= t('view_design.modal.title') ?></h2>

        <!-- Preview -->
        <div class="confirm-preview-row">
            <div class="confirm-mockup-wrap">
                <img id="confirmProductImg" src="" alt="" class="confirm-product-img">
                <img id="confirmDesignImg" src="" alt="" class="confirm-design-img" style="display:none">
            </div>
            <div class="confirm-item-meta">
                <p class="confirm-product-name" id="confirmProductName"></p>
                <p class="confirm-meta-line" id="confirmDesignLine"></p>
                <p class="confirm-meta-line" id="confirmColorLine"></p>
                <p class="confirm-meta-line" id="confirmSizeLine"></p>
            </div>
        </div>

        <div class="popup-field">
            <p class="popup-label"><?= t('view_design.modal.color') ?></p>
            <div id="cartColorOptions" class="popup-options"></div>
        </div>

        <div class="popup-field">
            <p class="popup-label"><?= t('view_design.modal.size') ?></p>
            <div id="cartSizeOptions" class="popup-options"></div>
        </div>

        <div class="popup-row">
            <label class="popup-label" for="confirmQty"><?= t('studio.cart.quantity') ?></label>
            <div class="confirm-qty-ctrl">
                <button type="button" data-on-click="adjustConfirmQty" data-args='[-1]' aria-label="&minus;">&minus;</button>
                <input type="number" id="confirmQty" value="1" min="1" max="99">
                <button type="button" data-on-click="adjustConfirmQty" data-args='[1]' aria-label="+">+</button>
            </div>
        </div>

        <div class="popup-prices">
            <div class="popup-price-row"><span><?= t('view_design.modal.base') ?></span><span id="confirmBase">-</span></div>
            <div class="popup-price-row"><span><?= t('view_design.modal.design') ?></span><span id="confirmDesignFee">-</span></div>
            <div class="popup-price-row popup-price-total"><span id="confirmTotalLabel"><?= t('view_design.modal.total') ?></span><span id="confirmTotal">-</span></div>
        </div>

        <div id="confirmError" class="popup-error" style="display:none;"></div>

        <div class="popup-actions">
            <button type="button" id="doAddToCartBtn" class="btn btn-lg" data-on-click="doAddToCart">
                <?= t('view_design.modal.title') ?>
            </button>
            <?php // Going to the cart is the secondary way out, so it is an outline —
                  // it was a green button, a third colour for the same kind of action. ?>
            <button type="button" class="btn btn-lg btn-secondary" data-href="/cart">
                <?= t('view_design.modal.go_cart') ?>
            </button>
        </div>
    </div>
</div>
<?php $overlays = ($overlays ?? '') . ob_get_clean(); ?>


<?= View::json('premade-design-data', [
    'designId'               => (int)$design['id'],
    'designName'             => $design['name'] ?? '',
    'designPrice'            => (float)$design['price'],
    'isFixed'                => !empty($design['is_fixed']),
    'posFront'               => [
        'x'    => (float)($availableProducts[0]['design_pos_x'] ?? $design['design_pos_x'] ?? 0),
        'y'    => (float)($availableProducts[0]['design_pos_y'] ?? $design['design_pos_y'] ?? 0),
        'size' => (float)($availableProducts[0]['design_pos_size'] ?? $design['design_pos_size'] ?? 55),
    ],
    'posBack'                => [
        'x'    => (float)($availableProducts[0]['design_pos_back_x'] ?? $design['design_pos_back_x'] ?? 0),
        'y'    => (float)($availableProducts[0]['design_pos_back_y'] ?? $design['design_pos_back_y'] ?? 0),
        'size' => (float)($availableProducts[0]['design_pos_back_size'] ?? $design['design_pos_back_size'] ?? 55),
    ],
    'frontDesignImage'       => $design['image_path'] ?? '',
    'backDesignImage'        => $design['back_image_path'] ?? '',
    'productId'              => (int)($availableProducts[0]['id'] ?? 0),
    'basePrice'              => (float)($availableProducts[0]['base_price'] ?? 0),
    'productFrontImage'      => $availableProducts[0]['image_path'] ?? '',
    'productBackImage'       => $availableProducts[0]['back_image_path'] ?? '',
    'productLeftSleeveImage' => $availableProducts[0]['left_sleeve_image_path'] ?? '',
    'productRightSleeveImage'=> $availableProducts[0]['right_sleeve_image_path'] ?? '',
    'previewColorSizes'      => $previewColorSizes,
]) ?>
<?= View::script('/js/pages/premade-design.js') ?>

<?php require View::path('partials/size_guide_modal'); ?>
<?php require View::path('layouts/customer_footer'); ?>
