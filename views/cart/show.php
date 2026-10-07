<?php
// No login gate: guests shop too. The controller resolves the effective user
// (account or session guest) and passes an empty cart when there is neither.

$title = t('cart.title', false);
$extraCss = ['/css/pages/cart.css'];
require View::path('layouts/customer_header');
?>

<section class="section cart-section">
    <div class="container">
        <?php
            $crumbs  = [[t('header.nav.home', false), '/'], [t('cart.title', false), null]];
            $heading = t('cart.title', false);
            $lead    = t('cart.subtitle', false);
            require View::path('partials/page_head');
        ?>

        <?php if (!empty($cartItems)): ?>
        <div class="cart-container">
            <div class="cart-items-list">
                <?php foreach ($cartItems as $item):
                    $imagePath = $item['product_image'] ?? '';
                    if (strpos($imagePath, 'public/') === 0) {
                        $imagePath = substr($imagePath, 7);
                    }
                    if ($imagePath && strpos($imagePath, '/') !== 0) {
                        $imagePath = '/' . $imagePath;
                    }
                    $unitPrice = (float)($item['unit_total'] ?? $item['base_price'] ?? 0);
                    $lineTotal = (float)($item['line_total'] ?? ($unitPrice * $item['quantity']));
                    $isCustom = !empty($item['is_custom_design']) || !empty($item['custom_design_fee']);
                    $colorHex = $item['color_hex'] ?? '#ffffff';
                    $colorFilter = $colorHex ? Tint::filterFor($colorHex) : '';
                    $uploads = $item['uploads'] ?? [];

                    // Premade design overlay position (same formula as shop_anime.php)
                    $posX    = (float)($item['premade_pos_x']    ?? 0);
                    $posY    = (float)($item['premade_pos_y']    ?? 0);
                    $posSize = (float)($item['premade_pos_size'] ?? 55);
                    $overlayLeft = 50 + $posX * 0.25;
                    $overlayTop  = 55 + $posY * 0.375;
                    $overlayW    = $posSize * 0.5;
                ?>
                <div class="cart-item-card" data-item-id="<?= $item['id'] ?>" data-unit-price="<?= $unitPrice ?>">
                    <div class="cart-item-image-wrapper">
                        <?php if (!empty($item['front_preview'])): ?>
                            <?php
                                $prevPath = $item['front_preview'];
                                if (strpos($prevPath, 'public/') === 0) $prevPath = substr($prevPath, 7);
                                if ($prevPath && strpos($prevPath, '/') !== 0) $prevPath = '/' . $prevPath;
                            ?>
                            <img src="<?= htmlspecialchars($prevPath) ?>"
                                 alt="<?= htmlspecialchars($item['product_name'] ?? 'Product') ?>"
                                 class="cart-product-img"
                                 loading="lazy"
                                 data-fallback="/images/placeholder.png">
                        <?php else: ?>
                            <img src="<?= htmlspecialchars($imagePath ?: '/images/placeholder.png') ?>"
                                 alt="<?= htmlspecialchars($item['product_name'] ?? 'Product') ?>"
                                 class="cart-product-img"
                                 loading="lazy"
                                 style="filter: <?= htmlspecialchars($colorFilter) ?>;"
                                 data-fallback="/images/placeholder.png">
                            <?php if (!empty($item['premade_design_image'])): ?>
                            <img src="/<?= htmlspecialchars($item['premade_design_image']) ?>"
                                 alt=""
                                 class="cart-design-overlay"
                                 style="left:<?= $overlayLeft ?>%;top:<?= $overlayTop ?>%;width:<?= $overlayW ?>%;">
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="cart-item-details">
                        <h3 class="cart-item-name">
                            <?= htmlspecialchars($item['product_name'] ?? 'Product #' . $item['product_id']) ?>
                            <?php if (!empty($item['premade_design_name'])): ?>
                            <span class="badge-premade"><?= I18n::t('cart.item.design_label', ['name' => htmlspecialchars($item['premade_design_name'])]) ?></span>
                            <?php elseif ($isCustom): ?>
                            <span class="badge-custom"><?= t('cart.item.custom') ?></span>
                            <?php endif; ?>
                        </h3>
                        <div class="cart-item-meta">
                            <?php if (!empty($item['size_name'])): ?>
                            <span class="cart-item-meta-item">
                                <?= t('cart.item.size_label') ?> <?= htmlspecialchars($item['size_name']) ?>
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($item['color_name'])): ?>
                            <span class="cart-item-meta-item">
                                <span class="color-swatch-sm" style="background: <?= htmlspecialchars($colorHex) ?>"></span>
                                <?= htmlspecialchars($item['color_name']) ?>
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($item['custom_design_fee']) && $item['custom_design_fee'] > 0): ?>
                            <span class="cart-item-meta-item">
                                <?= t('cart.item.design_fee') ?> +€<?= number_format($item['custom_design_fee'], 2) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="cart-item-pricing">
                            <span class="cart-item-unit-price">€<?= number_format($unitPrice, 2) ?> each</span>
                            <span class="cart-item-line-total">€<?= number_format($lineTotal, 2) ?></span>
                        </div>
                    </div>
                    
                    <div class="cart-item-actions">
                        <div class="quantity-controls">
                            <button class="qty-btn" data-on-click="updateQuantity" data-args="[<?= (int)$item['id'] ?>, -1]" <?= $item['quantity'] <= 1 ? 'disabled' : '' ?>>−</button>
                            <span class="qty-value" id="qty-<?= $item['id'] ?>"><?= (int)$item['quantity'] ?></span>
                            <button class="qty-btn" data-on-click="updateQuantity" data-args="[<?= (int)$item['id'] ?>, 1]">+</button>
                        </div>
                        <?php // Ghost, not a red outlined button. Remove was the
                              // loudest control in every row, competing with the
                              // quantity stepper and pulling the eye toward
                              // deleting rather than buying. ?>
                        <button class="btn btn-sm btn-ghost cart-remove-btn" data-on-click="removeFromCart" data-args="[<?= (int)$item['id'] ?>]">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="vertical-align:-2px;margin-right:5px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg><?= t('cart.item.remove') ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="cart-summary">
                <h2><?= t('cart.summary.title') ?></h2>
                <div class="summary-row">
                    <span class="summary-label"><?= t('cart.summary.items') ?></span>
                    <span class="summary-value" id="items-count"><?= array_sum(array_column($cartItems ?? [], 'quantity')) ?></span>
                </div>
                <div class="summary-row">
                    <span class="summary-label"><?= t('cart.summary.subtotal') ?></span>
                    <span class="summary-value" id="cart-subtotal">€<?= number_format($cartTotal ?? 0, 2) ?></span>
                </div>
                <div class="summary-row">
                    <span class="summary-label"><?= t('cart.summary.collection') ?></span>
                    <span class="summary-value summary-value-note"><?= t('cart.summary.collection_value') ?></span>
                </div>
                <div class="summary-row total">
                    <span class="summary-label"><?= t('cart.summary.total') ?></span>
                    <span class="summary-value" id="cart-total">€<?= number_format($cartTotal ?? 0, 2) ?></span>
                </div>
                <?php // btn-outline-light is for DARK surfaces — white text on a
                      // white border. On this light summary card it rendered
                      // invisible. Secondary is the correct intent here. ?>
                <div class="cart-summary-actions">
                    <a href="/checkout" class="btn btn-lg btn-success"><?= t('cart.summary.checkout') ?></a>
                    <a href="/shop" class="btn btn-lg btn-secondary"><?= t('cart.summary.continue_shopping') ?></a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon" aria-hidden="true">
                <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            </div>
            <h2><?= t('cart.empty.title') ?></h2>
            <p><?= t('cart.empty.lead') ?></p>
            <a href="/shop" class="btn btn-lg"><?= t('cart.empty.button') ?></a>
        </div>
        <?php endif; ?>
    </div>
</section>


<!-- Remove Item Confirmation Modal -->
<div id="removeConfirmOverlay" class="confirm-overlay" data-on-click="closeRemoveConfirm" data-click-self role="dialog" aria-modal="true" aria-labelledby="removeConfirmTitle">
    <div class="confirm-dialog">
        <div class="confirm-icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
        </div>
        <h3 id="removeConfirmTitle" class="confirm-title"><?= t('cart.remove.title') ?></h3>
        <p class="confirm-lead"><?= t('cart.remove.lead') ?></p>
        <div class="confirm-actions">
            <button type="button" class="confirm-btn confirm-btn-secondary" data-on-click="closeRemoveConfirm"><?= t('cart.remove.cancel') ?></button>
            <button type="button" id="confirmRemoveBtn" class="confirm-btn confirm-btn-danger" data-on-click="confirmRemove"><?= t('cart.remove.confirm') ?></button>
        </div>
    </div>
</div>

<?= View::script('/js/pages/cart.js') ?>

<?php require View::path('layouts/customer_footer'); ?>
