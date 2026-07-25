<?php $title = htmlspecialchars($design['name']) . ' - ' . htmlspecialchars($design['section_name']); ?>
<?php require __DIR__ . '/../layouts/customer_header.php'; ?>

<!-- Interact.js for drag & resize (only loaded for non-fixed designs) -->
<?php if (empty($design['is_fixed'])): ?>
<script src="https://cdn.jsdelivr.net/npm/interactjs/dist/interact.min.js"></script>
<?php endif; ?>

<section class="section design-section">
    <div class="container">
        <nav class="breadcrumb">
            <a href="/"><?= t('header.nav.home') ?></a> &gt;
            <a href="/shop"><?= t('header.nav.shop') ?></a> &gt;
            <a href="/shop/premade"><?= t('view_design.breadcrumb.premade') ?></a> &gt;
            <a href="/shop/premade/<?= htmlspecialchars($design['section_slug']) ?>"><?= htmlspecialchars($design['section_name']) ?></a> &gt;
            <span><?= htmlspecialchars($design['name']) ?></span>
        </nav>

        <div class="design-page">
            <!-- Product Mockup Preview Column -->
            <div class="design-preview-column">
                <!-- Front/Back Toggle -->
                <div class="side-toggle" id="sideToggle">
                    <label style="margin-right:10px;font-weight:500;"><?= t('view_design.placement') ?></label>
                    <button type="button" class="side-btn active" data-side="front" id="chooseFrontBtn"><?= t('studio.view.front') ?></button>
                    <button type="button" class="side-btn" data-side="back" id="chooseBackBtn" style="display:none;"><?= t('studio.view.back') ?></button>
                    <button type="button" class="side-btn" data-side="left-sleeve" id="chooseLeftSleeveBtn" style="display:none;"><?= t('studio.view.left_sleeve') ?></button>
                    <button type="button" class="side-btn" data-side="right-sleeve" id="chooseRightSleeveBtn" style="display:none;"><?= t('studio.view.right_sleeve') ?></button>
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
                        🔄 <?= t('view_design.reset') ?>
                    </button>
                    <button type="button" class="control-btn" onclick="centerDesign()" title="<?= t('view_design.center') ?>">
                        ⊙ <?= t('view_design.center') ?>
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
                             data-size-chart="<?= htmlspecialchars($product['size_chart_image'] ?? '') ?>">
                            <div class="product-option-image">
                                <?php if (!empty($product['image_path'])): ?>
                                    <img src="/<?= htmlspecialchars($product['image_path']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="no-image-small"><?= t('view_design.no_image') ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="product-option-info">
                                <h3><?= htmlspecialchars($product['name']) ?></h3>
                                <p class="product-base-price"><?= I18n::t('view_design.from_price', ['price' => number_format($product['base_price'] + $design['price'], 2)]) ?></p>
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
                    📏 Size guide
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
                                <span id="basePrice">€<?= number_format($availableProducts[0]['base_price'], 2) ?></span>
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
                                <span id="totalPrice">€<?= number_format($availableProducts[0]['base_price'] + $design['price'], 2) ?></span>
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
                            🛒 <?= t('studio.cart.add') ?>
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

<style>
.design-page {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    margin-top: 20px;
}

.design-preview-column {
    position: sticky;
    top: 20px;
    align-self: start;
}

/* Front/Back Toggle */
.side-toggle {
    display: flex;
    gap: 5px;
    margin-bottom: 10px;
}

.side-btn {
    flex: 1;
    padding: 10px 20px;
    border: 2px solid #ddd;
    background: white;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s;
}

.side-btn:hover {
    border-color: #15130E;
}

.side-btn.active {
    background: var(--ink);
    color: white;
    border-color: transparent;

}

.side-btn.disabled {
    opacity: 0.5;
}

/* Product Mockup Styles */
.mockup-container {
    background: #ffffff;
    border-radius: 12px;
    overflow: hidden;
    aspect-ratio: 1;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.3s;
}

.mockup-container.dark-bg {
    background: #1a1a1a;
}

/* Color overlay - hidden, we use filters instead */
.color-overlay {
    display: none;
}

.mockup-product {
    width: 100%;
    height: 100%;
    object-fit: contain;
    position: relative;
    z-index: 0;
    transition: filter 0.3s;
}

.mockup-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e0e0e0;
    color: #999;
    font-size: 1.2rem;
}

/* Design placement area - FIXED boundary box */
.design-area {
    position: absolute;
    top: 55%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 50%;
    height: 75%;
    border: none;
    border-radius: 8px;
    z-index: 10;
    overflow: hidden;
    background: transparent;
    transition: all 0.3s ease;
}

.design-area.sleeve-view {
    width: 40%;
    height: 50%;
}

.design-area-label {
    display: none;
    position: absolute;
    top: -25px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 11px;
    color: #15130E;
    background: white;
    padding: 2px 8px;
    border-radius: 4px;
    white-space: nowrap;
    opacity: 0.8;
    z-index: 12;
}

/* Draggable/resizable design element inside the fixed box */
.design-element {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 160px;
    height: 160px;
    cursor: move;
    touch-action: none;
    display: flex;
    align-items: center;
    justify-content: center;
    /* Prevent blue selection highlight */
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
}

.design-element:hover {
    outline: 2px solid rgba(125, 128, 218, 0.5);
    outline-offset: 2px;
}

.mockup-design {
    max-width: 100%;
    max-height: 100%;
    width: 100%;
    height: auto;
    object-fit: contain;
    pointer-events: none;
    filter: drop-shadow(2px 2px 4px rgba(0,0,0,0.2));
    /* Prevent image dragging and selection */
    user-select: none;
    -webkit-user-select: none;
    -webkit-user-drag: none;
    -moz-user-select: none;
    -ms-user-select: none;
    pointer-events: none;
}

.resize-handle {
    position: absolute;
    bottom: -6px;
    right: -6px;
    width: 14px;
    height: 14px;
    background: #15130E;
    border-radius: 50%;
    cursor: se-resize;
    z-index: 11;
    border: 2px solid white;
    box-shadow: 0 1px 3px rgba(0,0,0,0.3);
    transition: opacity 0.2s;
}

/* Inactive state - cleaner preview when clicking outside */
.design-area.inactive {
    border-color: transparent;
    background: transparent;
}

.design-area.inactive .design-area-label {
    opacity: 0;
}

.design-area.inactive .resize-handle {
    opacity: 0;
    pointer-events: none;
}

.design-area.inactive .design-element {
    outline: none !important;
    cursor: default;
}

.design-area.inactive .mockup-design {
    filter: drop-shadow(1px 1px 2px rgba(0,0,0,0.15));
}

/* Design controls */
.design-controls {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
    padding: 10px;
    background: #f9f9f9;
    border-radius: 8px;
}

.control-btn {
    padding: 8px 15px;
    border: 1px solid #ddd;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s;
}

.control-btn:hover {
    border-color: #15130E;
    background: #f0f0ff;
}

.size-slider {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-left: auto;
}

.size-slider label {
    font-size: 13px;
    color: #666;
}

.size-slider input[type="range"] {
    width: 100px;
}

/* Fixed design styles */
.design-area-fixed {
    border: none;
    cursor: default;
    pointer-events: none;
}

.design-element-fixed {
    cursor: default;
    touch-action: auto;
}

.design-element-fixed:hover {
    outline: none;
}

/* Design only preview thumbnail */
.design-only-preview {
    margin-top: 15px;
    padding: 15px;
    background: #fff;
    border-radius: 10px;
    border: 1px solid #eee;
}

.design-only-preview h4 {
    font-size: 0.9rem;
    color: #666;
    margin-bottom: 10px;
}

.design-thumbnail {
    width: 80px;
    height: 80px;
    border-radius: 8px;
    overflow: hidden;
    background: #f5f5f5;
}

.design-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.design-info {
    margin-top: 20px;
}

.design-info h1 {
    font-size: 1.8rem;
    margin-bottom: 10px;
}

.section-tag {
    display: inline-block;
    background: var(--ink);
    color: white;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 0.9rem;
    margin-bottom: 15px;
}

.design-description {
    color: #666;
    line-height: 1.6;
    margin-bottom: 15px;
}

.design-price-note {
    color: #888;
    font-size: 0.95rem;
}

.product-selection-column h2 {
    font-size: 1.5rem;
    margin-bottom: 5px;
}

.selection-subtitle {
    color: #666;
    margin-bottom: 20px;
}

.product-options {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 25px;
}

.product-option {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;
}

.product-option:hover {
    border-color: #15130E;
    background: #fafafa;
}

.product-option.selected {
    border-color: #15130E;
    background: linear-gradient(135deg, rgba(125, 128, 218, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
}

.product-option-image {
    width: 70px;
    height: 70px;
    border-radius: 8px;
    overflow: hidden;
    background: #f0f0f0;
    flex-shrink: 0;
}

.product-option-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.no-image-small {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    color: #999;
}

.product-option-info {
    flex: 1;
}

.product-option-info h3 {
    font-size: 1rem;
    margin-bottom: 5px;
}

.product-base-price {
    color: #e74c3c;
    font-weight: 600;
}

.product-option-check {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: 2px solid #e0e0e0;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.product-option.selected .product-option-check {
    background: #15130E;
    border-color: #15130E;
}

.checkmark {
    color: white;
    font-weight: bold;
    opacity: 0;
    transition: opacity 0.2s;
}

.product-option.selected .checkmark {
    opacity: 1;
}

.customization-section {
    background: #f9f9f9;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 25px;
}

.size-selection, .color-selection {
    margin-bottom: 20px;
}

.size-selection h4, .color-selection h4 {
    margin-bottom: 12px;
    font-size: 1rem;
}

.size-options {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.size-option {
    cursor: pointer;
}

.size-option input {
    display: none;
}

.size-label {
    display: inline-block;
    padding: 10px 20px;
    border: 2px solid #ddd;
    border-radius: 8px;
    background: white;
    transition: all 0.2s;
}

.size-option input:checked + .size-label {
    border-color: #15130E;
    background: #15130E;
    color: white;
}

.size-label small {
    opacity: 0.8;
}

.color-options {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    padding-bottom: 25px; /* Space for labels */
}

.color-option {
    cursor: pointer;
    position: relative;
}

.color-option input {
    display: none;
}

.color-swatch {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 3px solid #e0e0e0;
    transition: all 0.2s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.color-option input:checked + .color-swatch {
    border-color: #333;
    transform: scale(1.15);
    box-shadow: 0 3px 8px rgba(0,0,0,0.2);
}

.color-option:hover .color-swatch {
    transform: scale(1.1);
}

.color-swatch.is-white {
    box-shadow: inset 0 0 0 2px #333, 0 2px 4px rgba(0,0,0,0.1);
}

.color-name {
    position: absolute;
    bottom: -20px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 10px;
    white-space: nowrap;
    color: #666;
    max-width: 60px;
    overflow: hidden;
    text-overflow: ellipsis;
    text-align: center;
}

.order-summary {
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    padding: 20px;
}

.price-breakdown {
    margin-bottom: 20px;
}

.price-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.price-row.total {
    border-bottom: none;
    border-top: 2px solid #333;
    margin-top: 10px;
    padding-top: 15px;
    font-size: 1.2rem;
    font-weight: bold;
}

.quantity-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.quantity-control {
    display: flex;
    align-items: center;
    gap: 5px;
}

.qty-btn {
    width: 36px;
    height: 36px;
    border: 1px solid #bbb;
    background: #eee;
    border-radius: 5px;
    font-size: 18px;
    font-weight: 600;
    color: #333;
    cursor: pointer;
    transition: background 0.2s;
}

.qty-btn:hover {
    background: #ddd;
}

.quantity-control input {
    width: 50px;
    height: 36px;
    text-align: center;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 16px;
}

.btn-large {
    width: 100%;
    padding: 15px 30px;
    font-size: 1.1rem;
}

.btn-add-cart {
    background: var(--ink);
    color: white;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
}

.btn-add-cart:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(125, 128, 218, 0.4);
}

.no-products-message {
    background: #f8f9fa;
    padding: 40px;
    text-align: center;
    border-radius: 10px;
    color: #666;
}

.no-variants {
    color: #666;
    font-style: italic;
}

.preview-hint {
    font-size: 0.75rem;
    color: #999;
    font-weight: 400;
}
.preview-color-selection h4 { margin-bottom: 12px; font-size: 1rem; }
.preview-color-selection { margin-bottom: 20px; }

.preview-size-display { margin-bottom: 20px; }
.preview-size-display h4 { margin-bottom: 10px; font-size: 1rem; }
.preview-size-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.preview-size-chip {
    display: inline-block;
    padding: 6px 12px;
    background: #f5f5f5;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 500;
    color: #555;
    user-select: none;
}
.preview-size-chip.unavailable {
    display: none;
}

/* Fixed design: color swatches + size chips */
.fixed-color-selection, .fixed-size-area {
    margin-bottom: 20px;
}

.fixed-color-selection h4, .fixed-size-area h4 {
    margin-bottom: 12px;
    font-size: 1rem;
}

.fixed-color-swatches {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    padding-bottom: 20px;
}

.fixed-color-label {
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}

.fixed-color-label input[type="radio"] {
    display: none;
}

.fixed-swatch {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 3px solid #e0e0e0;
    transition: all 0.2s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.fixed-swatch.is-white {
    box-shadow: inset 0 0 0 2px #333, 0 2px 4px rgba(0,0,0,0.1);
}

.fixed-color-label input:checked + .fixed-swatch {
    border-color: #15130E;
    transform: scale(1.18);
    box-shadow: 0 3px 8px rgba(102,126,234,0.35);
}

.fixed-color-label:hover .fixed-swatch {
    transform: scale(1.1);
}

.fixed-color-name {
    font-size: 10px;
    color: #666;
    white-space: nowrap;
    max-width: 52px;
    overflow: hidden;
    text-overflow: ellipsis;
    text-align: center;
}

.size-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.size-chip-label {
    cursor: pointer;
}

.size-chip-label input[type="radio"] {
    display: none;
}

.size-chip {
    display: inline-block;
    padding: 6px 14px;
    border: 2px solid #ddd;
    border-radius: 20px;
    font-size: 0.85rem;
    background: white;
    transition: all 0.2s;
    font-weight: 500;
}

.size-chip-label input:checked + .size-chip {
    border-color: #15130E;
    background: #15130E;
    color: white;
}

.size-chip-label:hover .size-chip {
    border-color: #15130E;
    background: #f0f0ff;
}

@media (max-width: 768px) {
    .design-page {
        grid-template-columns: 1fr;
    }

    .design-preview-column {
        position: static;
    }
}

/* ===== Confirm Cart Modal ===== */
.confirm-cart-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.45);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

.confirm-cart-overlay.active {
    display: flex;
}

.confirm-cart-box {
    background: #fff;
    border-radius: 16px;
    width: 92%;
    max-width: 480px;
    max-height: 92vh;
    overflow-y: auto;
    padding: 28px 28px 22px;
    position: relative;
    box-shadow: 0 8px 32px rgba(0,0,0,0.18);
}

.confirm-cart-close {
    position: absolute;
    top: 14px; right: 16px;
    background: none; border: none;
    font-size: 1.8rem; color: #888;
    cursor: pointer; line-height: 1;
}

.confirm-cart-title {
    font-size: 1.3rem;
    font-weight: 700;
    text-align: center;
    margin-bottom: 18px;
    color: #333;
}

.confirm-preview-row {
    display: flex;
    gap: 16px;
    margin-bottom: 18px;
    align-items: flex-start;
}

.confirm-mockup-wrap {
    position: relative;
    width: 110px;
    height: 110px;
    flex-shrink: 0;
    background: #f5f5f5;
    border-radius: 10px;
    overflow: hidden;
}

.confirm-product-img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.confirm-design-img {
    position: absolute;
    transform: translate(-50%, -50%);
    height: auto;
    pointer-events: none;
    filter: drop-shadow(1px 2px 3px rgba(0,0,0,0.2));
}

.confirm-item-meta {
    flex: 1;
}

.confirm-product-name {
    font-weight: 700;
    font-size: 1rem;
    color: #333;
    margin-bottom: 6px;
}

.confirm-meta-line {
    color: #555;
    font-size: 0.88rem;
    margin-bottom: 4px;
}

.confirm-price-box {
    background: #f9f9f9;
    border-radius: 10px;
    padding: 14px 16px;
    margin-bottom: 16px;
}

.confirm-price-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
    padding: 4px 0;
    border-bottom: 1px solid #eee;
}

.confirm-price-row:last-child { border-bottom: none; }

.confirm-price-total {
    font-weight: 700;
    font-size: 1.05rem;
    margin-top: 6px;
    padding-top: 10px;
    border-top: 2px solid #333 !important;
}

.confirm-qty-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}

.confirm-qty-row label {
    font-weight: 600;
}

.confirm-qty-ctrl {
    display: flex;
    align-items: center;
    gap: 6px;
}

.confirm-qty-ctrl button {
    width: 34px; height: 34px;
    border: 1px solid #ddd;
    background: #f0f0f0;
    border-radius: 6px;
    font-size: 1.1rem;
    cursor: pointer;
}

.confirm-qty-ctrl input {
    width: 52px; height: 34px;
    text-align: center;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 1rem;
}
</style>

<script>
const designPrice = <?= $design['price'] ?>;
const isFixedDesign = <?= !empty($design['is_fixed']) ? 'true' : 'false' ?>;
const savedDesignPos = {
    x: <?= (float)($design['design_pos_x'] ?? 0) ?>,
    y: <?= (float)($design['design_pos_y'] ?? 0) ?>,
    size: <?= (float)($design['design_pos_size'] ?? 55) ?>
};
const savedDesignPosBack = {
    x: <?= (float)($design['design_pos_back_x'] ?? 0) ?>,
    y: <?= (float)($design['design_pos_back_y'] ?? 0) ?>,
    size: <?= (float)($design['design_pos_back_size'] ?? 55) ?>
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

        // Update mockup
        updateMockupProduct();
        updateViewButtons();
        
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
    if (isWhite) {
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
    if (!selectedProductId) { alert('Please select a product.'); return; }

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
        const mRect  = mockup.getBoundingClientRect();
        const elRect = designEl.getBoundingClientRect();
        const scale  = 130 / (mRect.width || 130);
        const elLeft = elRect.left - mRect.left;
        const elTop  = elRect.top  - mRect.top;
        const elW    = elRect.width;
        cDesignImg.src   = designImg.src;
        cDesignImg.style.display = 'block';
        cDesignImg.style.width   = (elW * scale) + 'px';
        cDesignImg.style.left    = ((elLeft + elW / 2) * scale) + 'px';
        cDesignImg.style.top     = ((elTop  + elW / 2) * scale) + 'px';
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

    const btn = document.getElementById('doAddToCartBtn');
    btn.textContent = 'Adding...';
    btn.disabled    = true;

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
        btn.textContent = window.I18N.t('view_design.modal.title');
        btn.disabled    = false;
        if (data.success || data.cart_item_id) {
            closeConfirmCart();
            showCartSuccessNotification();
        } else {
            throw new Error(data.error || window.I18N.t('studio.cart.error_generic'));
        }
    })
    .catch(err => {
        btn.textContent = window.I18N.t('view_design.modal.title');
        btn.disabled    = false;
        errorEl.textContent = err.message;
        errorEl.style.display = 'block';
    });
}

function showCartSuccessNotification() {
    let n = document.getElementById('cartSuccessNotif');
    if (!n) {
        n = document.createElement('div');
        n.id = 'cartSuccessNotif';
        n.style.cssText = 'position:fixed;top:20px;right:20px;background:#28a745;color:#fff;padding:16px 22px;border-radius:10px;box-shadow:0 4px 14px rgba(0,0,0,0.15);z-index:50000;display:flex;align-items:center;gap:12px;';
        n.innerHTML = '<span style="font-size:1.4rem;">&#10003;</span><span><strong>Added to cart!</strong><br><small style="opacity:0.9"><a href="/cart" style="color:#fff">Go to cart &rarr;</a></small></span>';
        document.body.appendChild(n);
    }
    n.style.display = 'flex';
    setTimeout(() => { n.style.display = 'none'; }, 4000);
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

<?php require __DIR__ . '/../partials/size_guide_modal.php'; ?>
<?php require __DIR__ . '/../layouts/customer_footer.php'; ?>
