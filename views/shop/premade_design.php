<?php $title = htmlspecialchars($design['name']) . ' - ' . htmlspecialchars($design['section_name']); ?>
<?php $extraCss[] = '/css/pages/premade-design.css'; require View::path('layouts/customer_header'); ?>

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
                <div class="second-design-option" style="margin: 10px 0 20px 0;">
                    <input type="checkbox" id="addSecondDesign" />
                    <label for="addSecondDesign" style="font-weight:500;cursor:pointer;"><?= t('view_design.second_design', false, ['side' => '<span id="oppositeSideLabel">' . t('view_design.side.back', false) . '</span>', 'price' => '<span id="secondDesignPrice">' . number_format($design['price'], 2) . '</span>']) ?></label>
                </div>
                <div id="secondDesignUpload" style="display:none;margin-bottom:10px;">
                    <label for="secondDesignFile" style="font-weight:500;"><?= t('view_design.second_upload', false, ['side' => '<span id="secondSideUploadLabel">' . t('view_design.side.back_cap', false) . '</span>']) ?></label>
                    <input type="file" id="secondDesignFile" accept="image/*">
                    <div id="secondDesignPreview" style="margin-top:8px;"></div>
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
                    <button type="button" class="control-btn" onclick="resetDesignPosition()" title="<?= t('view_design.reset') ?>">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="vertical-align:-2px;margin-right:5px;"><path d="M3 2v6h6"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L3 8"/></svg><?= t('view_design.reset') ?>
                    </button>
                    <button type="button" class="control-btn" onclick="centerDesign()" title="<?= t('view_design.center') ?>">
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
                   onclick="event.preventDefault(); openSizeGuide('<?= htmlspecialchars($product['size_chart_image']) ?>', '<?= htmlspecialchars($product['name']) ?>');"
                   style="margin-left:10px; font-size:0.82rem; color:var(--spot, #2A4FE0); text-decoration:none; font-weight:500;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="vertical-align:-2px;margin-right:4px;"><path d="M2 12h20"/><path d="M6 9v6M10 7v10M14 9v6M18 7v10"/></svg>Size guide
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
                                <span>+€<?= number_format($design['price'], 2) ?></span>
                            </div>
<div class="price-row" id="secondDesignRow" style="display:none;">
                                <span><?= t('view_design.price.second_side') ?></span>
                                <span id="secondDesignCost">+€<?= number_format($design['price'], 2) ?></span>
                            </div>
                            <div class="price-row total">
                                <span><?= t('view_design.price.total') ?></span>
                                <span id="totalPrice">€<?= number_format(($availableProducts[0]['retail_price'] ?? $availableProducts[0]['base_price'] ?? 0) + $design['price'], 2) ?></span>
                            </div>
                        </div>

                        <div class="quantity-row">
                            <label for="quantity"><?= t('studio.cart.quantity') ?></label>
                            <div class="quantity-control">
                                <button type="button" class="qty-btn" onclick="changeQuantity(-1)">−</button>
                                <input type="number" id="quantity" name="quantity" value="1" min="1" max="99">
                                <button type="button" class="qty-btn" onclick="changeQuantity(1)">+</button>
                            </div>
                        </div>

                        <button type="button" class="btn btn-large btn-add-cart" onclick="addToCart()">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="vertical-align:-3px;margin-right:7px;"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg><?= t('studio.cart.add') ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Confirm Add to Cart Modal -->
<div id="confirmCartModal" class="confirm-cart-overlay" onclick="if(event.target===this)closeConfirmCart()">
    <div class="confirm-cart-box">
        <button class="confirm-cart-close" onclick="closeConfirmCart()">&times;</button>
        <h2 class="confirm-cart-title"><?= t('view_design.modal.title') ?></h2>

        <!-- Preview -->
        <div class="confirm-preview-row">
            <div class="confirm-mockup-wrap">
                <img id="confirmProductImg" src="" alt="" class="confirm-product-img">
                <img id="confirmDesignImg" src="" alt="" class="confirm-design-img" style="display:none">
            </div>
            <div class="confirm-item-meta">
                <p class="confirm-product-name" id="confirmProductName"></p>
                <p class="confirm-meta-line" id="confirmDesignLine"></p>
                <p class="confirm-meta-line" id="confirmColorLine" style="color:#15130E;font-weight:600;"></p>
                <p class="confirm-meta-line" id="confirmSizeLine" style="color:#15130E;font-weight:600;"></p>
            </div>
        </div>

        <!-- Color Selection -->
        <div style="margin-bottom:16px;">
            <label style="font-weight:600;display:block;margin-bottom:10px;"><?= t('view_design.modal.color') ?></label>
            <div id="cartColorOptions" style="display:flex;flex-wrap:wrap;gap:10px;padding-bottom:8px;"></div>
        </div>

        <!-- Size Selection -->
        <div style="margin-bottom:16px;">
            <label style="font-weight:600;display:block;margin-bottom:10px;"><?= t('view_design.modal.size') ?></label>
            <div id="cartSizeOptions" style="display:flex;flex-wrap:wrap;gap:8px;"></div>
        </div>

        <!-- Quantity -->
        <div class="confirm-qty-row">
            <label><?= t('studio.cart.quantity') ?></label>
            <div class="confirm-qty-ctrl">
                <button type="button" onclick="adjustConfirmQty(-1)">−</button>
                <input type="number" id="confirmQty" value="1" min="1" max="99">
                <button type="button" onclick="adjustConfirmQty(1)">+</button>
            </div>
        </div>

        <!-- Price -->
        <div class="confirm-price-box">
            <div class="confirm-price-row"><span><?= t('view_design.modal.base') ?></span><span id="confirmBase">-</span></div>
            <div class="confirm-price-row"><span><?= t('view_design.modal.design') ?></span><span id="confirmDesignFee">-</span></div>
            <div class="confirm-price-row confirm-price-total"><span><?= t('view_design.modal.total') ?></span><span id="confirmTotal">-</span></div>
        </div>

        <div id="confirmError" style="display:none;color:#dc3545;text-align:center;margin-bottom:10px;font-size:0.9rem;"></div>

        <button id="doAddToCartBtn" class="btn btn-large btn-add-cart" onclick="doAddToCart()" style="margin-bottom:10px;">
            <?= t('view_design.modal.title') ?>
        </button>
        <button type="button" class="btn btn-large" onclick="window.location.href='/cart'" style="background:#28a745;color:white;border:none;">
            <?= t('view_design.modal.go_cart') ?>
        </button>
    </div>
</div>


<script>
const designPrice = <?= $design['price'] ?>;
const isFixedDesign = <?= !empty($design['is_fixed']) ? 'true' : 'false' ?>;
// Placement is per garment — these track the SELECTED product and are rewritten
// when the shopper picks a different one (see the .product-option handler).
// $availableProducts already carries the link-row position, or the design's own
// as a fallback.
let savedDesignPos = {
    x: <?= (float)($availableProducts[0]['design_pos_x'] ?? $design['design_pos_x'] ?? 0) ?>,
    y: <?= (float)($availableProducts[0]['design_pos_y'] ?? $design['design_pos_y'] ?? 0) ?>,
    size: <?= (float)($availableProducts[0]['design_pos_size'] ?? $design['design_pos_size'] ?? 55) ?>
};
let savedDesignPosBack = {
    x: <?= (float)($availableProducts[0]['design_pos_back_x'] ?? $design['design_pos_back_x'] ?? 0) ?>,
    y: <?= (float)($availableProducts[0]['design_pos_back_y'] ?? $design['design_pos_back_y'] ?? 0) ?>,
    size: <?= (float)($availableProducts[0]['design_pos_back_size'] ?? $design['design_pos_back_size'] ?? 55) ?>
};
const frontDesignImage = '<?= addslashes($design['image_path'] ?? '') ?>';
const backDesignImage = '<?= addslashes($design['back_image_path'] ?? '') ?>';
let selectedProductId = <?= $availableProducts[0]['id'] ?? 0 ?>;
let selectedBasePrice = <?= $availableProducts[0]['base_price'] ?? 0 ?>;
let currentSide = 'front';
let currentProductFrontImage = '<?= addslashes($availableProducts[0]['image_path'] ?? '') ?>';
let currentProductBackImage = '<?= addslashes($availableProducts[0]['back_image_path'] ?? '') ?>';
let currentProductLeftSleeveImage = '<?= addslashes($availableProducts[0]['left_sleeve_image_path'] ?? '') ?>';
let currentProductRightSleeveImage = '<?= addslashes($availableProducts[0]['right_sleeve_image_path'] ?? '') ?>';

// Design position tracking (for front and back)
let designPositions = {
    front: { x: 0, y: 0, width: 160, height: 160 },
    back: { x: 0, y: 0, width: 160, height: 160 }
};

// ==================== PRODUCT SELECTION ====================
document.querySelectorAll('.product-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.product-option').forEach(o => o.classList.remove('selected'));
        this.classList.add('selected');
        
        selectedProductId = parseInt(this.dataset.productId);
        selectedBasePrice = parseFloat(this.dataset.basePrice);
        currentProductFrontImage = this.dataset.productImage || '';
        currentProductBackImage = this.dataset.productBackImage || '';
        currentProductLeftSleeveImage = this.dataset.leftSleeveImage || '';
        currentProductRightSleeveImage = this.dataset.rightSleeveImage || '';

        // The design's placement belongs to the design/product pair, so it moves
        // with the product the shopper just picked.
        const num = (v, fb) => { const n = parseFloat(v); return isNaN(n) ? fb : n; };
        savedDesignPos = {
            x:    num(this.dataset.posX, 0),
            y:    num(this.dataset.posY, 0),
            size: num(this.dataset.posSize, 55)
        };
        savedDesignPosBack = {
            x:    num(this.dataset.posBackX, 0),
            y:    num(this.dataset.posBackY, 0),
            size: num(this.dataset.posBackSize, 55)
        };

        // Update mockup
        updateMockupProduct();
        updateViewButtons();
        if (isFixedDesign) applyFixedDesignForSide(currentSide);
        
        // Show correct customization panel
        document.querySelectorAll('.product-customization').forEach(panel => {
            panel.style.display = 'none';
        });
        const customPanel = document.getElementById('customization-' + selectedProductId);
        if (customPanel) {
            customPanel.style.display = 'block';
            const firstPreviewColorRadio = customPanel.querySelector('input[name="preview_color_' + selectedProductId + '"]:checked');
            if (firstPreviewColorRadio && firstPreviewColorRadio.dataset.hex) {
                applyColorTint(firstPreviewColorRadio.dataset.hex);
                updatePreviewSizeChips(selectedProductId, firstPreviewColorRadio.value);
            }
        }

        // Reset product color filter
        document.getElementById('mockupProduct').style.filter = 'none';
        document.getElementById('mockupContainer').classList.remove('dark-bg');
        
        updatePrice();
    });
});

// ==================== FRONT/BACK TOGGLE ====================
document.querySelectorAll('.side-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        if (this.classList.contains('disabled')) return;

        document.querySelectorAll('.side-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        currentSide = this.dataset.side;
        updateMockupProduct();

        if (isFixedDesign) {
            applyFixedDesignForSide(currentSide);
        } else {
            restoreDesignPosition();
        }
    });
});

// Dots + swipe. The label follows .active on its own, so the handler above
// needed no changes. Swipes starting on the design element are ignored so
// dragging the artwork still works.
// Deferred inside DOMContentLoaded: view-switcher.js is loaded with `defer`,
// so it has not executed yet while this inline script is being parsed.
document.addEventListener('DOMContentLoaded', function () {
    if (!window.ViewSwitcher) return;
    window.ViewSwitcher.init({
        dots: '#sideDots',
        dotSelector: '.side-btn',
        label: '#sideCurrentLabel',
        surface: '#mockupContainer',
        ignore: '.design-element'
    });
});

function updateViewButtons() {
    const backBtn = document.getElementById('chooseBackBtn');
    const leftSleeveBtn = document.getElementById('chooseLeftSleeveBtn');
    const rightSleeveBtn = document.getElementById('chooseRightSleeveBtn');

    backBtn.style.display = currentProductBackImage ? '' : 'none';
    leftSleeveBtn.style.display = currentProductLeftSleeveImage ? '' : 'none';
    rightSleeveBtn.style.display = currentProductRightSleeveImage ? '' : 'none';

    // If currently on a view that's no longer available, switch to front
    if (currentSide === 'back' && !currentProductBackImage) {
        document.querySelector('.side-btn[data-side="front"]').click();
    } else if (currentSide === 'left-sleeve' && !currentProductLeftSleeveImage) {
        document.querySelector('.side-btn[data-side="front"]').click();
    } else if (currentSide === 'right-sleeve' && !currentProductRightSleeveImage) {
        document.querySelector('.side-btn[data-side="front"]').click();
    }
}

function updateMockupProduct() {
    const mockupProduct = document.getElementById('mockupProduct');
    const designArea = document.getElementById('designArea');
    let imagePath = '';
    if (currentSide === 'front') imagePath = currentProductFrontImage;
    else if (currentSide === 'back') imagePath = currentProductBackImage;
    else if (currentSide === 'left-sleeve') imagePath = currentProductLeftSleeveImage;
    else if (currentSide === 'right-sleeve') imagePath = currentProductRightSleeveImage;

    // Toggle sleeve design area size
    if (currentSide === 'left-sleeve' || currentSide === 'right-sleeve') {
        designArea.classList.add('sleeve-view');
    } else {
        designArea.classList.remove('sleeve-view');
    }

    if (mockupProduct && imagePath) {
        mockupProduct.src = '/' + imagePath;
        mockupProduct.style.display = 'block';

        // Re-apply current color tint to the new image
        const selectedColor = document.querySelector('input[name^="color_"]:checked');
        if (selectedColor && selectedColor.dataset.colorHex) {
            setTimeout(() => {
                applyColorTint(selectedColor.dataset.colorHex);
            }, 50);
        }
    } else if (mockupProduct) {
        mockupProduct.style.display = 'none';
    }
}

// ==================== COLOR SELECTION & TINTING ====================
document.querySelectorAll('.size-option input').forEach(radio => {
    radio.addEventListener('change', function() {
        updateColors(this);
        updatePrice();
    });
});

function updateColors(sizeInput) {
    const productId = sizeInput.name.replace('size_', '');
    const colorContainer = document.getElementById('colors-' + productId);
    
    const colorIds = sizeInput.dataset.colors ? sizeInput.dataset.colors.split(',') : [];
    const colorNames = sizeInput.dataset.colorNames ? sizeInput.dataset.colorNames.split(',') : [];
    const colorHexes = sizeInput.dataset.colorHexes ? sizeInput.dataset.colorHexes.split(',') : [];
    
    if (colorIds.length === 0 || !colorIds[0]) {
        colorContainer.innerHTML = '<p class="no-variants">No colors available for this size</p>';
        document.getElementById('mockupProduct').style.filter = 'none';
        document.getElementById('mockupContainer').classList.remove('dark-bg');
        return;
    }
    
    let html = '';
    for (let i = 0; i < colorIds.length; i++) {
        const checked = i === 0 ? 'checked' : '';
        const hex = (colorHexes[i] || '').toLowerCase();
        const name = (colorNames[i] || '').toLowerCase();
        const isWhite = hex === '#ffffff' || hex === '#fff' || name === 'white';
        html += `
            <label class="color-option">
                <input type="radio" name="color_${productId}" value="${colorIds[i]}" 
                       data-color-hex="${colorHexes[i]}" data-color-name="${colorNames[i]}" ${checked}
                       onchange="applyColorTint('${colorHexes[i]}')">
                <span class="color-swatch${isWhite ? ' is-white' : ''}" style="background-color: ${colorHexes[i]}"></span>
                <span class="color-name">${colorNames[i]}</span>
            </label>
        `;
    }
    colorContainer.innerHTML = html;
    
    // Apply first color tint
    if (colorHexes[0]) {
        applyColorTint(colorHexes[0]);
    }
}

function buildColorFilter(hexColor) {
    if (!hexColor) return 'none';
    hexColor = hexColor.trim();
    if (!hexColor.startsWith('#')) hexColor = '#' + hexColor;
    const hsl = hexToHSL(hexColor);
    const isWhite = hexColor.toLowerCase() === '#ffffff' || hsl.l > 95;
    const isBlack = hsl.l < 10;
    const isGray  = hsl.s < 10;
    const tintOverride = window.CostasTint && window.CostasTint.getOverride(hexColor);
    if (tintOverride) return tintOverride;

    if (isWhite) return 'grayscale(1) brightness(2.2) contrast(0.85)';
    if (isBlack) return 'grayscale(1) brightness(0.45) contrast(1.2)';
    if (isGray)  return `grayscale(1) brightness(${0.2 + (hsl.l / 100) * 1.5})`;
    const hueRotate = hsl.h - 38; // sepia base hue ≈ 38°
    const isReddish  = hsl.h <= 20 || hsl.h >= 340;
    const isYellowish = hsl.h >= 45 && hsl.h <= 80;
    let saturate = (hsl.s / 100) * 3 + 0.8;
    if (isReddish)   saturate = (hsl.s / 100) * 6 + 2.0;
    if (isYellowish) saturate = (hsl.s / 100) * 4 + 1.0;
    let brightness;
    if (hsl.l < 30)      brightness = 0.3 + (hsl.l / 100) * 0.7;
    else if (hsl.l < 50) brightness = 0.5 + (hsl.l / 100) * 0.6;
    else                 brightness = 0.6 + (hsl.l / 100) * 0.5;
    if (isYellowish && hsl.l >= 45) brightness = Math.min(brightness * 1.25, 1.5);
    return `grayscale(1) sepia(1) saturate(${saturate}) hue-rotate(${hueRotate}deg) brightness(${brightness})`;
}

function applyColorTint(hexColor) {
    if (!hexColor) return;
    
    // Ensure hex has # prefix
    hexColor = hexColor.trim();
    if (!hexColor.startsWith('#')) {
        hexColor = '#' + hexColor;
    }
    
    const mockupProduct = document.getElementById('mockupProduct');
    const mockupContainer = document.getElementById('mockupContainer');
    if (!mockupProduct || !mockupContainer) return;
    
    // Convert hex to HSL for the hue-rotate filter
    const hsl = hexToHSL(hexColor);
    
    // Check if it's white or very light
    const isWhite = hexColor.toLowerCase() === '#ffffff' || hexColor.toLowerCase() === '#fff' || hsl.l > 95;
    const isVeryLight = hsl.l > 85;
    const isBlack = hexColor.toLowerCase() === '#000000' || hexColor.toLowerCase() === '#000' || hsl.l < 10;
    const isGray = hsl.s < 10; // Low saturation = gray
    
    // Set background: white by default, dark for white/light products
    if (isWhite || isVeryLight) {
        mockupContainer.classList.add('dark-bg');
    } else {
        mockupContainer.classList.remove('dark-bg');
    }
    
    // Apply color filter to product image
    // Base image is ORANGE (~30deg hue) with transparent background
    const tintOverride = window.CostasTint && window.CostasTint.getOverride(hexColor);
    if (tintOverride) {
        mockupProduct.style.filter = tintOverride;
    } else if (isWhite) {
        // White product - desaturate completely and brighten significantly
        mockupProduct.style.filter = 'grayscale(1) brightness(2.2) contrast(0.85)';
    } else if (isBlack) {
        // Black product - desaturate and darken significantly
        mockupProduct.style.filter = 'grayscale(1) brightness(0.45) contrast(1.2)';
    } else if (isGray) {
        // Gray - desaturate and adjust brightness based on lightness
        const brightness = 0.2 + (hsl.l / 100) * 1.5;
        mockupProduct.style.filter = `grayscale(1) brightness(${brightness})`;
    } else {
        // Colorize using sepia base then hue-rotate to target
        // This works better than direct hue-rotate from orange
        const hueRotate = hsl.h - 38; // sepia base hue ≈ 38°

        const isReddish   = hsl.h <= 20 || hsl.h >= 340;
        const isYellowish = hsl.h >= 45 && hsl.h <= 80;
        let saturate = (hsl.s / 100) * 3 + 0.8;
        if (isReddish)   saturate = (hsl.s / 100) * 6 + 2.0;
        if (isYellowish) saturate = (hsl.s / 100) * 4 + 1.0;

        let brightness;
        if (hsl.l < 30) {
            brightness = 0.3 + (hsl.l / 100) * 0.7;
        } else if (hsl.l < 50) {
            brightness = 0.5 + (hsl.l / 100) * 0.6;
        } else {
            brightness = 0.6 + (hsl.l / 100) * 0.5;
        }
        if (isYellowish && hsl.l >= 45) brightness = Math.min(brightness * 1.25, 1.5);

        mockupProduct.style.filter = `grayscale(1) sepia(1) saturate(${saturate}) hue-rotate(${hueRotate}deg) brightness(${brightness})`;
    }
}

function hexToHSL(hex) {
    // Remove # if present
    hex = hex.replace('#', '');
    
    // Parse RGB
    const r = parseInt(hex.substring(0, 2), 16) / 255;
    const g = parseInt(hex.substring(2, 4), 16) / 255;
    const b = parseInt(hex.substring(4, 6), 16) / 255;
    
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    let h, s, l = (max + min) / 2;
    
    if (max === min) {
        h = s = 0;
    } else {
        const d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        switch (max) {
            case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
            case g: h = ((b - r) / d + 2) / 6; break;
            case b: h = ((r - g) / d + 4) / 6; break;
        }
    }
    
    return {
        h: Math.round(h * 360),
        s: Math.round(s * 100),
        l: Math.round(l * 100)
    };
}

// ==================== DRAG & RESIZE DESIGN ====================
function initDesignInteraction() {
    const designElement = document.getElementById('designElement');
    const designArea = document.getElementById('designArea');
    if (!designElement || !designArea || typeof interact === 'undefined') return;
    
    interact(designElement)
        .draggable({
            inertia: false,
            modifiers: [
                interact.modifiers.restrict({
                    restriction: designArea,
                    elementRect: { top: 0, left: 0, bottom: 1, right: 1 }
                })
            ],
            listeners: {
                move: dragMoveListener
            }
        })
        .resizable({
            edges: { right: '.resize-handle', bottom: '.resize-handle' },
            modifiers: [
                interact.modifiers.restrictSize({
                    min: { width: 40, height: 40 },
                    max: { width: 450, height: 450 }
                }),
                interact.modifiers.restrict({
                    restriction: designArea
                })
            ],
            listeners: {
                move: resizeMoveListener
            }
        });
}

function dragMoveListener(event) {
    const target = event.target;
    const x = (parseFloat(target.getAttribute('data-x')) || 0) + event.dx;
    const y = (parseFloat(target.getAttribute('data-y')) || 0) + event.dy;
    
    target.style.transform = `translate(calc(-50% + ${x}px), calc(-50% + ${y}px))`;
    target.setAttribute('data-x', x);
    target.setAttribute('data-y', y);
    
    // Save position for current side
    designPositions[currentSide].x = x;
    designPositions[currentSide].y = y;
}

function resizeMoveListener(event) {
    const target = event.target;
    
    // Keep it square for consistent sizing
    const size = Math.max(event.rect.width, event.rect.height);
    
    target.style.width = size + 'px';
    target.style.height = size + 'px';
    
    // Save size for current side
    designPositions[currentSide].width = size;
    designPositions[currentSide].height = size;
}

function restoreDesignPosition() {
    const designElement = document.getElementById('designElement');
    if (!designElement) return;

    // No design overlay for sleeve views
    if (currentSide === 'left-sleeve' || currentSide === 'right-sleeve') {
        designElement.style.display = 'none';
        return;
    }
    designElement.style.display = 'flex';

    const pos = designPositions[currentSide] || { x: 0, y: 0, width: 160, height: 160 };
    const x = pos.x || 0;
    const y = pos.y || 0;
    const width = pos.width || 160;
    const height = pos.height || 160;

    designElement.style.transform = `translate(calc(-50% + ${x}px), calc(-50% + ${y}px))`;
    designElement.setAttribute('data-x', x);
    designElement.setAttribute('data-y', y);
    designElement.style.width = width + 'px';
    designElement.style.height = height + 'px';
}

function resetDesignPosition() {
    const designElement = document.getElementById('designElement');
    if (!designElement) return;
    
    designElement.style.transform = 'translate(-50%, -50%)';
    designElement.style.width = '160px';
    designElement.style.height = '160px';
    designElement.setAttribute('data-x', 0);
    designElement.setAttribute('data-y', 0);
    
    designPositions[currentSide] = { x: 0, y: 0, width: 160, height: 160 };
    
    // Reset slider
    document.getElementById('designSizeSlider').value = 160;
}

function centerDesign() {
    const designElement = document.getElementById('designElement');
    if (!designElement) return;
    
    designElement.style.transform = 'translate(-50%, -50%)';
    designElement.setAttribute('data-x', 0);
    designElement.setAttribute('data-y', 0);
    
    designPositions[currentSide].x = 0;
    designPositions[currentSide].y = 0;
}

function updateDesignSize(value) {
    const designElement = document.getElementById('designElement');
    if (!designElement) return;
    
    // Value is slider value (30-200), use as pixel size
    const size = parseInt(value);
    designElement.style.width = size + 'px';
    designElement.style.height = size + 'px';
    
    designPositions[currentSide].width = size;
    designPositions[currentSide].height = size;
}

function applyFixedDesignForSide(side) {
    const designElement = document.getElementById('designElement');
    const designArea = document.getElementById('designArea');
    const mockupDesign = document.getElementById('mockupDesign');
    if (!designElement || !designArea || !mockupDesign) return;

    // No design overlay for sleeve views
    if (side === 'left-sleeve' || side === 'right-sleeve') {
        designElement.style.display = 'none';
        return;
    }

    const pos = side === 'front' ? savedDesignPos : savedDesignPosBack;
    const imgSrc = side === 'front' ? frontDesignImage : backDesignImage;

    if (!imgSrc) {
        designElement.style.display = 'none';
        return;
    }
    mockupDesign.src = '/' + imgSrc;
    designElement.style.display = 'flex';

    setTimeout(() => {
        const areaW = designArea.offsetWidth;
        const areaH = designArea.offsetHeight;
        if (areaW === 0) return;
        const px = (pos.x / 100) * (areaW / 2);
        const py = (pos.y / 100) * (areaH / 2);
        const ps = (pos.size / 100) * areaW;
        designElement.style.width = ps + 'px';
        designElement.style.height = ps + 'px';
        designElement.style.transform = `translate(calc(-50% + ${px}px), calc(-50% + ${py}px))`;
        designElement.setAttribute('data-x', px);
        designElement.setAttribute('data-y', py);
    }, 20);
}

// ==================== PRICE CALCULATION ====================
function updatePrice() {
    // selectedBasePrice is the SUPPLIER cost; apply the quantity-tiered margin so
    // the preview matches what the server charges.
    const selectedOpt = document.querySelector('.product-option.selected');
    const productName = selectedOpt ? (selectedOpt.dataset.productName || '') : '';
    const qtyInput = document.getElementById('quantity');
    const qty = qtyInput ? (parseInt(qtyInput.value) || 1) : 1;
    const category = window.Pricing ? Pricing.categoryFor('', productName) : 'tshirt';
    const unitRetail = window.Pricing
        ? Pricing.unitPrice(selectedBasePrice, category, qty)
        : selectedBasePrice;

    document.getElementById('basePrice').textContent = '€' + unitRetail.toFixed(2);
    // Second design cost (front + back print)
    let secondDesignCost = 0;
    if (document.getElementById('addSecondDesign').checked) {
        secondDesignCost = designPrice;
        document.getElementById('secondDesignRow').style.display = 'flex';
    } else {
        document.getElementById('secondDesignRow').style.display = 'none';
    }
    const total = unitRetail + designPrice + secondDesignCost;
    document.getElementById('totalPrice').textContent = '€' + total.toFixed(2);
}

// ==================== CART ====================
function changeQuantity(delta) {
    const input = document.getElementById('quantity');
    let value = parseInt(input.value) + delta;
    if (value < 1) value = 1;
    if (value > 99) value = 99;
    input.value = value;
    // Quantity drives the pricing tier — refresh the displayed price.
    if (typeof updatePrice === 'function') updatePrice();
}

// ==================== CONFIRM CART MODAL (shop_custom style) ====================
let confirmModalState = {
    productId: null,
    premadeDesignId: <?= $design['id'] ?>,
    designName: <?= json_encode($design['name'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
    designFee: <?= $design['price'] ?>,
    basePrice: 0,
    selectedColorId: null,
    selectedColorHex: null,
    selectedSizeId: null,
    quantity: 1,
    variants: [],
    sizes: [],
    colors: []
};

function addToCart() {
    if (!selectedProductId) { UI.error('Please select a product.'); return; }

    // Get the currently previewed color
    const previewColorRadio = document.querySelector('input[name="preview_color_' + selectedProductId + '"]:checked');
    const previewColorHex   = previewColorRadio ? previewColorRadio.dataset.hex  : null;
    const previewColorId    = previewColorRadio ? previewColorRadio.value         : null;

    confirmModalState.productId      = selectedProductId;
    confirmModalState.basePrice      = selectedBasePrice;
    confirmModalState.selectedColorId  = null;
    confirmModalState.selectedSizeId   = null;
    confirmModalState.selectedColorHex = null;
    confirmModalState.quantity         = parseInt(document.getElementById('quantity').value) || 1;

    // Set preview in modal
    const productImg = document.getElementById('mockupProduct');
    document.getElementById('confirmProductImg').src = productImg ? productImg.src : '';
    document.getElementById('confirmProductImg').style.filter = productImg ? productImg.style.filter : '';

    // Set design overlay in modal preview
    const designEl  = document.getElementById('designElement');
    const designImg = document.getElementById('mockupDesign');
    const mockup    = document.getElementById('mockupContainer');
    const cDesignImg = document.getElementById('confirmDesignImg');
    if (designEl && designImg && designImg.src && mockup) {
        // Express the design's placement as a RATIO of the mockup, then apply
        // those ratios to the modal's own box. The previous version scaled by a
        // hardcoded 130px while .confirm-mockup-wrap is 110px, so the design
        // came out ~18% too big and pushed down-right; percentages can't drift
        // out of sync with the CSS that way. Measuring the live element (rather
        // than the saved position) also keeps this correct for designs the
        // shopper is allowed to drag.
        const mRect  = mockup.getBoundingClientRect();
        const elRect = designEl.getBoundingClientRect();
        if (mRect.width > 0 && mRect.height > 0) {
            const leftPct = ((elRect.left - mRect.left) + elRect.width  / 2) / mRect.width  * 100;
            const topPct  = ((elRect.top  - mRect.top)  + elRect.height / 2) / mRect.height * 100;
            const widthPct = (elRect.width / mRect.width) * 100;

            cDesignImg.src = designImg.src;
            cDesignImg.style.display = 'block';
            cDesignImg.style.width = widthPct + '%';
            cDesignImg.style.left  = leftPct + '%';
            cDesignImg.style.top   = topPct + '%';
        } else {
            cDesignImg.style.display = 'none';
        }
    } else {
        cDesignImg.style.display = 'none';
    }

    const productOption = document.querySelector('.product-option.selected');
    document.getElementById('confirmProductName').textContent = productOption ? productOption.dataset.productName : '';
    document.getElementById('confirmDesignLine').textContent  = 'Design: ' + confirmModalState.designName;
    document.getElementById('confirmQty').value = confirmModalState.quantity;
    document.getElementById('confirmError').style.display = 'none';
    document.getElementById('confirmSizeLine').textContent  = '';
    document.getElementById('confirmColorLine').textContent = '';

    updateConfirmPrices();

    // Fetch variants for this product
    fetchConfirmVariants(selectedProductId, previewColorId);

    document.getElementById('confirmCartModal').classList.add('active');
}

function fetchConfirmVariants(productId, preSelectColorId) {
    fetch('/api/product-variants/' + productId)
        .then(r => r.json())
        .then(data => {
            confirmModalState.variants = data.variants || [];
            confirmModalState.sizes    = data.sizes    || [];
            confirmModalState.colors   = data.colors   || [];
            renderConfirmColors(preSelectColorId);
            renderConfirmSizes();
        })
        .catch(() => {
            document.getElementById('confirmError').textContent = 'Failed to load product options.';
            document.getElementById('confirmError').style.display = 'block';
        });
}

function renderConfirmColors(preSelectColorId) {
    const container = document.getElementById('cartColorOptions');
    container.innerHTML = '';
    confirmModalState.colors.forEach(color => {
        const hasVariant = confirmModalState.variants.some(v => v.color_id == color.id && v.is_available);
        const hex = color.hex || '#ccc';
        const isWhite = hex.toLowerCase() === '#ffffff' || hex.toLowerCase() === '#fff';
        const btn = document.createElement('button');
        btn.className = 'cart-color-btn';
        btn.dataset.colorId = color.id;
        btn.dataset.colorHex = hex;
        btn.title = color.name;
        btn.style.cssText = `width:36px;height:36px;border-radius:50%;border:3px solid #ddd;cursor:pointer;background:${hex};transition:all 0.2s;box-shadow:${isWhite ? 'inset 0 0 0 1px #ccc' : 'none'};`;
        if (!hasVariant) { btn.style.opacity = '0.35'; btn.disabled = true; btn.style.cursor = 'not-allowed'; }
        btn.onclick = function() { if (!this.disabled) selectConfirmColor(color.id, hex, color.name); };
        container.appendChild(btn);
    });
    // Pre-select preview color if available
    if (preSelectColorId) {
        const match = confirmModalState.colors.find(c => c.id == preSelectColorId);
        if (match) {
            const hasVariant = confirmModalState.variants.some(v => v.color_id == match.id && v.is_available);
            if (hasVariant) {
                selectConfirmColor(match.id, match.hex, match.name);
                return;
            }
        }
    }
    // Pre-select first available color
    const first = confirmModalState.colors.find(c => confirmModalState.variants.some(v => v.color_id == c.id && v.is_available));
    if (first) selectConfirmColor(first.id, first.hex, first.name);
}

function renderConfirmSizes() {
    const container = document.getElementById('cartSizeOptions');
    container.innerHTML = '';
    confirmModalState.sizes.forEach(size => {
        const hasVariant = confirmModalState.variants.some(v => v.size_id == size.id &&
            (!confirmModalState.selectedColorId || v.color_id == confirmModalState.selectedColorId) && v.is_available);
        const btn = document.createElement('button');
        btn.className = 'cart-size-btn';
        btn.textContent = size.name + (size.modifier > 0 ? ' (+$' + parseFloat(size.modifier).toFixed(2) + ')' : '');
        btn.dataset.sizeId = size.id;
        btn.dataset.modifier = size.modifier || 0;
        btn.style.cssText = 'padding:8px 16px;border:2px solid #ddd;border-radius:20px;background:#fff;cursor:pointer;font-weight:500;font-size:0.85rem;transition:all 0.2s;';
        if (!hasVariant) { btn.style.opacity = '0.35'; btn.disabled = true; btn.style.cursor = 'not-allowed'; }
        btn.onclick = function() { if (!this.disabled) selectConfirmSize(size.id, parseFloat(this.dataset.modifier)); };
        container.appendChild(btn);
    });
}

function selectConfirmColor(colorId, colorHex, colorName) {
    confirmModalState.selectedColorId  = colorId;
    confirmModalState.selectedColorHex = colorHex;

    // Update swatch borders
    document.querySelectorAll('.cart-color-btn').forEach(btn => {
        btn.style.borderColor  = btn.dataset.colorId == colorId ? '#333' : '#ddd';
        btn.style.boxShadow    = btn.dataset.colorId == colorId ? '0 0 0 2px #15130E' : (btn.dataset.colorHex?.toLowerCase() === '#ffffff' ? 'inset 0 0 0 1px #ccc' : 'none');
        btn.style.transform    = btn.dataset.colorId == colorId ? 'scale(1.15)' : 'scale(1)';
    });

    // Apply tint to modal preview
    document.getElementById('confirmProductImg').style.filter = buildColorFilter(colorHex);

    // Apply tint to main mockup too
    applyColorTint(colorHex);

    // Update color line
    document.getElementById('confirmColorLine').textContent = 'Color: ' + colorName;

    // Update size availability
    updateConfirmSizeAvailability();
}

function selectConfirmSize(sizeId, modifier) {
    confirmModalState.selectedSizeId = sizeId;

    document.querySelectorAll('.cart-size-btn').forEach(btn => {
        const sel = btn.dataset.sizeId == sizeId;
        btn.style.borderColor = sel ? '#15130E' : '#ddd';
        btn.style.background  = sel ? 'var(--ink)' : '#fff';
        btn.style.color       = sel ? '#fff' : '#333';
    });

    const sizeBtn = document.querySelector('.cart-size-btn[data-size-id="' + sizeId + '"]');
    document.getElementById('confirmSizeLine').textContent = 'Size: ' + (sizeBtn ? sizeBtn.textContent : '');

    updateConfirmPrices(modifier);
    updateConfirmColorAvailability();
}

function updateConfirmSizeAvailability() {
    document.querySelectorAll('.cart-size-btn').forEach(btn => {
        const available = confirmModalState.variants.some(v =>
            v.size_id == btn.dataset.sizeId &&
            (!confirmModalState.selectedColorId || v.color_id == confirmModalState.selectedColorId) &&
            v.is_available
        );
        btn.style.opacity = available ? '1' : '0.35';
        btn.disabled      = !available;
        btn.style.cursor  = available ? 'pointer' : 'not-allowed';
        // Deselect if current size no longer available
        if (!available && confirmModalState.selectedSizeId == btn.dataset.sizeId) {
            confirmModalState.selectedSizeId = null;
            document.getElementById('confirmSizeLine').textContent = '';
        }
    });
    // Auto-select first available size
    if (!confirmModalState.selectedSizeId) {
        const firstAvail = document.querySelector('.cart-size-btn:not([disabled])');
        if (firstAvail) selectConfirmSize(firstAvail.dataset.sizeId, parseFloat(firstAvail.dataset.modifier) || 0);
    }
}

function updateConfirmColorAvailability() {
    document.querySelectorAll('.cart-color-btn').forEach(btn => {
        const available = confirmModalState.variants.some(v =>
            v.color_id == btn.dataset.colorId &&
            (!confirmModalState.selectedSizeId || v.size_id == confirmModalState.selectedSizeId) &&
            v.is_available
        );
        btn.style.opacity = available ? '1' : '0.35';
        btn.disabled      = !available;
        btn.style.cursor  = available ? 'pointer' : 'not-allowed';
    });
}

function closeConfirmCart() {
    document.getElementById('confirmCartModal').classList.remove('active');
}

function adjustConfirmQty(delta) {
    const input = document.getElementById('confirmQty');
    let v = parseInt(input.value) + delta;
    if (v < 1) v = 1;
    if (v > 99) v = 99;
    input.value = v;
    confirmModalState.quantity = v;
    updateConfirmPrices();
}

function updateConfirmPrices(sizeModifier) {
    const mod       = sizeModifier !== undefined ? sizeModifier : 0;
    const qty       = parseInt(document.getElementById('confirmQty').value) || 1;
    // basePrice + size modifier is the SUPPLIER cost; apply the quantity-tiered
    // margin to match the server. The premade design fee is added per unit.
    const selectedOpt = document.querySelector('.product-option.selected');
    const productName = selectedOpt ? (selectedOpt.dataset.productName || '') : '';
    const category  = window.Pricing ? Pricing.categoryFor('', productName) : 'tshirt';
    const supplier  = (confirmModalState.basePrice || 0) + mod;
    const unitPrice = window.Pricing ? Pricing.unitPrice(supplier, category, qty) : supplier;
    const designFee = confirmModalState.designFee || 0;
    const total     = (unitPrice + designFee) * qty;
    document.getElementById('confirmBase').textContent      = '€' + (unitPrice * qty).toFixed(2);
    document.getElementById('confirmDesignFee').textContent = '+€' + designFee.toFixed(2);
    document.getElementById('confirmTotal').textContent     = '€' + total.toFixed(2);
}

function doAddToCart() {
    const errorEl = document.getElementById('confirmError');
    errorEl.style.display = 'none';

    if (!confirmModalState.selectedColorId) {
        errorEl.textContent = 'Please select a color.';
        errorEl.style.display = 'block';
        return;
    }
    if (!confirmModalState.selectedSizeId) {
        errorEl.textContent = 'Please select a size.';
        errorEl.style.display = 'block';
        return;
    }

    // Spinner rather than swapping the label to "Adding…": the text change
    // resized the button mid-click and shifted the dialog under the cursor.
    const btn = document.getElementById('doAddToCartBtn');
    UI.loading(btn, true);

    const cartData = {
        premade_design_id: confirmModalState.premadeDesignId,
        product_id:  confirmModalState.productId,
        size_id:     confirmModalState.selectedSizeId,
        color_id:    confirmModalState.selectedColorId,
        quantity:    parseInt(document.getElementById('confirmQty').value) || 1,
        design_positions: designPositions
    };

    fetch('/cart/add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(cartData)
    })
    .then(r => r.json())
    .then(data => {
        if (data.requireLogin) { redirectToLoginWithPendingCart(cartData); return; }
        UI.loading(btn, false);
        if (data.success || data.cart_item_id) {
            closeConfirmCart();
            showCartSuccessNotification();
        } else {
            throw new Error(data.error || window.I18N.t('studio.cart.error_generic'));
        }
    })
    .catch(err => {
        UI.loading(btn, false);
        errorEl.textContent = err.message;
        errorEl.style.display = 'block';
    });
}

// Was a hand-rolled div with inline styles and its own timer; the shared toast
// keeps the "go to cart" follow-up and matches every other message on the site.
function showCartSuccessNotification() {
    UI.success(window.I18N.t('studio.cart.added') || 'Added to cart', {
        action: { label: window.I18N.t('studio.cart.go_to_cart') || 'Go to cart', href: '/cart' }
    });
}

// ==================== INITIALIZATION ====================
document.addEventListener('DOMContentLoaded', function() {
    // Initialize design element
    const designElement = document.getElementById('designElement');
    const designAreaEl = document.getElementById('designArea');
    if (designElement) {
        if (isFixedDesign && designAreaEl) {
            setTimeout(() => {
                const areaW = designAreaEl.offsetWidth;
                const areaH = designAreaEl.offsetHeight;
                if (areaW > 0) {
                    const px = (savedDesignPos.x / 100) * (areaW / 2);
                    const py = (savedDesignPos.y / 100) * (areaH / 2);
                    const ps = (savedDesignPos.size / 100) * areaW;
                    designElement.style.width = ps + 'px';
                    designElement.style.height = ps + 'px';
                    designElement.style.transform = `translate(calc(-50% + ${px}px), calc(-50% + ${py}px))`;
                    designElement.setAttribute('data-x', px);
                    designElement.setAttribute('data-y', py);
                }
            }, 50);
        } else {
            designElement.style.width = '160px';
            designElement.style.height = '160px';
        }
    }
    // Color → available size IDs per product
    const previewColorSizes = <?= json_encode($previewColorSizes) ?>;

    function updatePreviewSizeChips(productId, colorId) {
        const container = document.getElementById('preview-sizes-' + productId);
        if (!container) return;
        const available = (previewColorSizes[productId] && previewColorSizes[productId][colorId]) || [];
        container.querySelectorAll('.preview-size-chip').forEach(chip => {
            const sid = chip.dataset.sizeId;
            if (available.includes(parseInt(sid)) || available.includes(sid)) {
                chip.classList.remove('unavailable');
                chip.classList.add('available');
            } else {
                chip.classList.remove('available');
                chip.classList.add('unavailable');
            }
        });
    }

    // Initialize preview color tint for first product
    const firstProductId = <?= (int)($availableProducts[0]['id'] ?? 0) ?>;
    const firstPreviewColor = document.querySelector('input[name="preview_color_' + firstProductId + '"]:checked');
    if (firstPreviewColor && firstPreviewColor.dataset.hex) {
        applyColorTint(firstPreviewColor.dataset.hex);
        updatePreviewSizeChips(firstProductId, firstPreviewColor.value);
    }
    // Preview color swatch change handler
    document.querySelectorAll('input[name^="preview_color_"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.dataset.hex) applyColorTint(this.dataset.hex);
            const prodId = this.name.replace('preview_color_', '');
            updatePreviewSizeChips(prodId, this.value);
        });
    });

    // Initialize drag & resize (only for non-fixed designs)
    if (!isFixedDesign) {
        initDesignInteraction();
    }
    // Update view buttons based on available product images
    updateViewButtons();
    // Click away to hide design controls for cleaner preview
    const designArea = document.getElementById('designArea');
    if (designArea) {
        designArea.addEventListener('click', function(e) {
            designArea.classList.remove('inactive');
        });
    }
    document.addEventListener('click', function(e) {
        if (designArea && !designArea.contains(e.target) && 
            !e.target.closest('.design-controls') && 
            !e.target.closest('.side-toggle')) {
            designArea.classList.add('inactive');
        }
    });

    // --- Second design logic ---
    const addSecondDesign = document.getElementById('addSecondDesign');
    const secondDesignUpload = document.getElementById('secondDesignUpload');
    const oppositeSideLabel = document.getElementById('oppositeSideLabel');
    const secondSideUploadLabel = document.getElementById('secondSideUploadLabel');
    let mainSide = 'front';
    function updateOppositeLabel() {
        if (mainSide === 'front') {
            oppositeSideLabel.textContent = window.I18N.t('view_design.side.back');
            secondSideUploadLabel.textContent = window.I18N.t('view_design.side.back_cap');
        } else {
            oppositeSideLabel.textContent = window.I18N.t('view_design.side.front');
            secondSideUploadLabel.textContent = window.I18N.t('view_design.side.front_cap');
        }
    }
    document.getElementById('chooseFrontBtn').addEventListener('click', function() {
        mainSide = 'front';
        this.classList.add('active');
        document.getElementById('chooseBackBtn').classList.remove('active');
        updateOppositeLabel();
    });
    document.getElementById('chooseBackBtn').addEventListener('click', function() {
        mainSide = 'back';
        this.classList.add('active');
        document.getElementById('chooseFrontBtn').classList.remove('active');
        updateOppositeLabel();
    });
    addSecondDesign.addEventListener('change', function() {
        if (this.checked) {
            secondDesignUpload.style.display = '';
        } else {
            secondDesignUpload.style.display = 'none';
            document.getElementById('secondDesignFile').value = '';
            document.getElementById('secondDesignPreview').innerHTML = '';
        }
        updatePrice();
    });
    document.getElementById('secondDesignFile').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const preview = document.getElementById('secondDesignPreview');
        if (file) {
            const reader = new FileReader();
            reader.onload = function(evt) {
                preview.innerHTML = '<img src="' + evt.target.result + '" style="max-width:120px;max-height:120px;border:1px solid #ccc;border-radius:6px;">';
            };
            reader.readAsDataURL(file);
        } else {
            preview.innerHTML = '';
        }
    });
});
</script>

<?php require View::path('partials/size_guide_modal'); ?>
<?php require View::path('layouts/customer_footer'); ?>
