<?php
// No login gate: guests shop too. The controller resolves the effective user
// (account or session guest) and passes an empty cart when there is neither.

$title = t('cart.title', false);
$extraCss = ['/css/cart.css'];
require __DIR__ . '/../layouts/customer_header.php';
?>
<?php // (Cart-specific styles now live in /css/cart.css)
// Helper function to convert hex to HSL
function cartHexToHSL($hex) {
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    $r = hexdec(substr($hex, 0, 2)) / 255;
    $g = hexdec(substr($hex, 2, 2)) / 255;
    $b = hexdec(substr($hex, 4, 2)) / 255;
    
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $l = ($max + $min) / 2;
    
    $h = 0;
    $s = 0;
    if ($max !== $min) {
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        switch ($max) {
            case $r: $h = (($g - $b) / $d + ($g < $b ? 6 : 0)) / 6; break;
            case $g: $h = (($b - $r) / $d + 2) / 6; break;
            case $b: $h = (($r - $g) / $d + 4) / 6; break;
        }
    }
    return ['h' => $h * 360, 's' => $s * 100, 'l' => $l * 100];
}

// Generate CSS filter for product color.
// Delegated to Tint, which the browser mirrors in public/js/color-tint.js, so a
// cart line shows the same shade as the studio the item was designed in. This
// file's own copy had drifted (hue-rotate h-50 vs h-38) and never applied the
// solved overrides that stop deep reds flattening.
function getCartProductColorFilter($hex) {
    if (!$hex) return '';
    return Tint::filterFor($hex);
}
?>

<section class="section cart-section">
    <div class="container">
        <h1><?= t('cart.title') ?></h1>

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
                    $colorFilter = getCartProductColorFilter($colorHex);
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
                                 onerror="this.src='/images/placeholder.png'">
                        <?php else: ?>
                            <img src="<?= htmlspecialchars($imagePath ?: '/images/placeholder.png') ?>"
                                 alt="<?= htmlspecialchars($item['product_name'] ?? 'Product') ?>"
                                 class="cart-product-img"
                                 loading="lazy"
                                 style="filter: <?= htmlspecialchars($colorFilter) ?>;"
                                 onerror="this.src='/images/placeholder.png'">
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
                            <button class="qty-btn" onclick="updateQuantity(<?= $item['id'] ?>, -1)" <?= $item['quantity'] <= 1 ? 'disabled' : '' ?>>−</button>
                            <span class="qty-value" id="qty-<?= $item['id'] ?>"><?= (int)$item['quantity'] ?></span>
                            <button class="qty-btn" onclick="updateQuantity(<?= $item['id'] ?>, 1)">+</button>
                        </div>
                        <button class="btn-danger-outline" onclick="removeFromCart(<?= $item['id'] ?>)">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="vertical-align:-2px;margin-right:5px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg><?= t('cart.item.remove') ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="cart-summary">
                <h2><?= t('cart.summary.title') ?></h2>
                <div class="summary-row">
                    <span class="summary-label"><?= I18n::t('cart.summary.items', ['count' => count($cartItems)]) ?></span>
                    <span class="summary-value" id="items-count"><?= array_sum(array_column($cartItems ?? [], 'quantity')) ?></span>
                </div>
                <div class="summary-row">
                    <span class="summary-label"><?= t('cart.summary.subtotal') ?></span>
                    <span class="summary-value" id="cart-subtotal">€<?= number_format($cartTotal ?? 0, 2) ?></span>
                </div>
                <div class="summary-row">
                    <span class="summary-label"><?= t('cart.summary.shipping') ?></span>
                    <span class="summary-value" style="color:#4ade80;"><?= t('cart.summary.shipping_free') ?></span>
                </div>
                <div class="summary-row total">
                    <span class="summary-label"><?= t('cart.summary.total') ?></span>
                    <span class="summary-value" id="cart-total">€<?= number_format($cartTotal ?? 0, 2) ?></span>
                </div>
                <div class="cart-summary-actions">
                    <button id="openCheckoutModal" class="btn-success-gradient" style="padding: 16px 24px; font-size: 1.1rem;"><?= t('cart.summary.checkout') ?></button>
                    <a href="/shop" class="btn-outline-light"><?= t('cart.summary.continue_shopping') ?></a>
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
            <a href="/shop" class="btn-primary-gradient" style="padding: 14px 32px; font-size: 1.1rem;"><?= t('cart.empty.button') ?></a>
        </div>
        <?php endif; ?>
    </div>
</section>

<div class="checkout-modal-overlay" id="checkoutModalOverlay" aria-hidden="true">
    <div class="checkout-modal" role="dialog" aria-modal="true" aria-labelledby="checkoutModalTitle">
        
        <!-- Secure Badge Header -->
        <div class="checkout-header">
            <div class="checkout-secure-badge">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                <span><?= t('checkout.modal.title') ?></span>
            </div>
            <button class="checkout-close-btn" id="closeCheckoutModal" type="button" aria-label="<?= t('checkout.modal.close') ?>">&times;</button>
        </div>

        <!-- Progress Steps -->
        <div class="checkout-steps">
            <div class="checkout-step active" data-step="1">
                <div class="step-number">1</div>
                <span><?= t('checkout.steps.payment') ?></span>
            </div>
            <div class="checkout-step-line"></div>
            <div class="checkout-step" data-step="2">
                <div class="step-number">2</div>
                <span><?= t('checkout.steps.review') ?></span>
            </div>
        </div>

        <!-- Step 1: Payment -->
        <div class="checkout-step-panel active" id="checkoutStep1">
            <h3 class="checkout-section-title"><?= t('checkout.payment.title') ?></h3>
            
            <!-- Payment Method Tabs -->
            <div class="payment-methods-grid">
                <button type="button" class="payment-method-btn active" data-method="card" onclick="selectPaymentMethod('card')">
                    <div class="pm-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="4" width="22" height="16" rx="3"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    </div>
                    <span><?= t('checkout.payment.card') ?></span>
                    <div class="pm-brands">
                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 48 32'%3E%3Crect width='48' height='32' rx='4' fill='%231a1f71'/%3E%3Cpath d='M19.5 21.5h-3l1.9-11h3l-1.9 11zm12.8-10.7c-.6-.2-1.5-.5-2.7-.5-3 0-5 1.5-5 3.7 0 1.6 1.5 2.5 2.6 3s1.5 1 1.5 1.4c0 .8-.9 1.1-1.7 1.1-1.1 0-1.8-.2-2.7-.6l-.4-.2-.4 2.5c.7.3 1.9.6 3.2.6 3.2 0 5.2-1.5 5.2-3.8 0-1.3-.8-2.2-2.5-3-.7-.6-1.5-.9-1.5-1.4 0-.5.5-1 1.5-1 .9 0 1.5.2 2 .4l.2.1.5-2.3zm7.9-.3h-2.3c-.7 0-1.3.2-1.6 1l-4.5 10.5h3.2l.6-1.7h3.9l.4 1.7h2.8l-2.5-11.5zm-3.7 7.4l1.6-4.3.9 4.3h-2.5zM17.2 10.5l-2.8 7.5-.3-1.5c-.5-1.7-2.1-3.6-3.9-4.5l2.7 9.5h3.2l4.8-11h-3.7z' fill='white'/%3E%3Cpath d='M12.1 10.5H7.2l0 .3c3.8.9 6.3 3.2 7.3 5.9l-1.1-5.2c-.2-.8-.7-1-1.3-1z' fill='%23f7b600'/%3E%3C/svg%3E" alt="Visa" style="height:20px;">
                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 48 32'%3E%3Crect width='48' height='32' rx='4' fill='%23252525'/%3E%3Ccircle cx='19' cy='16' r='9' fill='%23eb001b'/%3E%3Ccircle cx='29' cy='16' r='9' fill='%23f79e1b'/%3E%3Cpath d='M24 9.3a9 9 0 013 6.7 9 9 0 01-3 6.7 9 9 0 01-3-6.7 9 9 0 013-6.7z' fill='%23ff5f00'/%3E%3C/svg%3E" alt="Mastercard" style="height:20px;">
                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 48 32'%3E%3Crect width='48' height='32' rx='4' fill='%23016fd0'/%3E%3Cpath d='M24 6l-10 10 10 10 10-10L24 6z' fill='none' stroke='white' stroke-width='1.5'/%3E%3Ctext x='24' y='19' text-anchor='middle' font-size='7' font-weight='bold' fill='white' font-family='Arial'%3EAMEX%3C/text%3E%3C/svg%3E" alt="Amex" style="height:20px;">
                    </div>
                </button>
                <button type="button" class="payment-method-btn" data-method="revolut" onclick="selectPaymentMethod('revolut')">
                    <div class="pm-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/><path d="M9 8h4a2 2 0 010 4h-4v4m0-8v4m0 0h3l3 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <span><?= t('checkout.payment.revolut') ?></span>
                </button>
                <button type="button" class="payment-method-btn" data-method="paypal" onclick="selectPaymentMethod('paypal')">
                    <div class="pm-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M7.5 21L9 13h3c4 0 6-2.5 6-5.5S16 2 12 2H7l-3 19h3.5z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 13l-.5 3h3c3 0 5-2 5-4.5S15.5 8 13 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <span><?= t('checkout.payment.paypal') ?></span>
                </button>
                <button type="button" class="payment-method-btn" data-method="applepay" onclick="selectPaymentMethod('applepay')">
                    <div class="pm-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M18.7 8.3c-.1.1-2.2 1.3-2.2 3.8 0 3 2.6 4 2.7 4 0 0-.4 1.3-1.3 2.6-.8 1.2-1.6 2.3-2.9 2.3s-1.6-.7-3-.7-1.8.7-3 .7-2-.9-2.8-2.4C4.7 16 4 13 4 10.2 4 6.7 6.3 4.9 8.5 4.9c1.3 0 2.3.8 3.1.8.8 0 2-.9 3.5-.9.6 0 2.5.1 3.6 1.5zm-4.2-1c.5-.6.9-1.5.9-2.3 0-.1 0-.3 0-.4-.8 0-1.8.6-2.4 1.2-.5.5-1 1.4-1 2.3 0 .2 0 .3 0 .4.1 0 .2 0 .3 0 .8 0 1.7-.5 2.2-1.2z" fill="currentColor"/></svg>
                    </div>
                    <span><?= t('checkout.payment.applepay') ?></span>
                </button>
                <button type="button" class="payment-method-btn" data-method="googlepay" onclick="selectPaymentMethod('googlepay')">
                    <div class="pm-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12.24 10.3v3.7h5.2a4.5 4.5 0 01-2 2.9l3.2 2.5c1.8-1.7 2.9-4.2 2.9-7.1 0-.7-.1-1.3-.2-2H12.24z" fill="currentColor" opacity=".7"/><path d="M5.3 14.3l-.7.6-2.6 2c1.6 3.2 5 5.5 8.9 5.5 2.7 0 5-.9 6.6-2.4l-3.2-2.5c-.9.6-2 1-3.4 1-2.6 0-4.9-1.8-5.6-4.2z" fill="currentColor" opacity=".55"/><path d="M2 7.6a10.7 10.7 0 000 9.7l3.3-2.6a6.4 6.4 0 010-4.6L2 7.6z" fill="currentColor" opacity=".4"/><path d="M10.9 5.4c1.5 0 2.8.5 3.9 1.5l2.9-2.9C16 2.3 13.7 1.4 10.9 1.4 7 1.4 3.6 3.6 2 6.8l3.3 2.6c.7-2.3 2.9-4 5.6-4z" fill="currentColor" opacity=".85"/></svg>
                    </div>
                    <span><?= t('checkout.payment.googlepay') ?></span>
                </button>
            </div>

            <!-- Card Payment Form -->
            <div class="payment-form-section" id="paymentFormCard">
                <form id="checkoutForm" autocomplete="off" onsubmit="return false;" novalidate>
                    <div class="checkout-field">
                        <label for="cardName"><?= t('checkout.card.holder') ?></label>
                        <div class="input-with-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <input id="cardName" name="cardName" type="text" autocomplete="cc-name" placeholder="<?= t('checkout.card.holder_placeholder') ?>" required>
                        </div>
                    </div>
                    <div class="checkout-field">
                        <label>Card details</label>
                        <div id="stripe-card-element"></div>
                        <div class="field-error" id="stripe-card-errors"></div>
                    </div>
                    <div class="checkout-field">
                        <label for="billingZip"><?= t('checkout.card.zip') ?></label>
                        <div class="input-with-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <input id="billingZip" name="billingZip" type="text" autocomplete="postal-code" placeholder="<?= t('checkout.card.zip_placeholder') ?>" required>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Alternative Payment Message -->
            <div class="payment-form-section alt-payment-msg" id="paymentFormAlt" style="display:none;">
                <div class="alt-payment-info">
                    <div class="alt-payment-icon" id="altPaymentIcon"></div>
                    <p id="altPaymentText"><?= t('checkout.alt_redirect') ?></p>
                    <div class="alt-payment-note">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <span><?= t('checkout.secure_note') ?></span>
                    </div>
                </div>
            </div>

            <!-- Order Total -->
            <div class="checkout-total-bar">
                <div class="checkout-total-label">
                    <span><?= t('checkout.order_total') ?></span>
                    <span class="checkout-total-items" id="modal-items-count"><?= array_sum(array_column($cartItems ?? [], 'quantity')) ?> item<?= array_sum(array_column($cartItems ?? [], 'quantity')) > 1 ? 's' : '' ?></span>
                </div>
                <div class="checkout-total-price" id="modal-total-price">€<?= number_format($cartTotal ?? 0, 2) ?></div>
            </div>

            <div class="checkout-actions">
                <button class="checkout-btn-secondary" id="checkoutCancelBtn" type="button" onclick="closeCheckoutModal()"><?= t('checkout.cancel') ?></button>
                <button class="checkout-btn-primary" id="checkoutNextBtn" type="button" onclick="goToReview()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    <?= t('checkout.continue') ?>
                </button>
            </div>
        </div>

        <!-- Step 2: Review & Confirm -->
        <div class="checkout-step-panel" id="checkoutStep2">
            <h3 class="checkout-section-title"><?= t('checkout.review.title') ?></h3>

            <div class="review-order-summary">
                <div class="review-section">
                    <div class="review-label"><?= t('checkout.review.payment_method') ?></div>
                    <div class="review-value" id="reviewPaymentMethod">
                        <span id="reviewCardInfo"></span>
                    </div>
                </div>
                <div class="review-section">
                    <div class="review-label"><?= t('checkout.review.items') ?></div>
                    <div class="review-value" id="modal-review-items"><?= array_sum(array_column($cartItems ?? [], 'quantity')) ?> product<?= array_sum(array_column($cartItems ?? [], 'quantity')) > 1 ? 's' : '' ?></div>
                </div>
                <div class="review-divider"></div>
                <div class="review-section">
                    <div class="review-label"><?= t('checkout.review.subtotal') ?></div>
                    <div class="review-value" id="modal-review-subtotal">€<?= number_format($cartTotal ?? 0, 2) ?></div>
                </div>
                <div class="review-section">
                    <div class="review-label"><?= t('checkout.review.shipping') ?></div>
                    <div class="review-value review-free"><?= t('cart.summary.shipping_free') ?></div>
                </div>
                <div class="review-divider"></div>
                <div class="review-section review-total">
                    <div class="review-label"><?= t('checkout.review.total') ?></div>
                    <div class="review-value" id="modal-review-total">€<?= number_format($cartTotal ?? 0, 2) ?></div>
                </div>
            </div>

            <!-- Shipping address — required; stored on the order for fulfilment -->
            <div class="review-order-summary" style="margin-top:14px;">
                <div class="review-label" style="margin-bottom:10px;"><?= t('checkout.shipping.title') ?></div>
                <div class="checkout-field">
                    <label for="shipName"><?= t('checkout.shipping.name') ?></label>
                    <input id="shipName" type="text" autocomplete="shipping name" placeholder="<?= t('checkout.shipping.name_placeholder') ?>" required maxlength="100" style="width:100%;">
                </div>
                <div class="checkout-field">
                    <label for="shipPhone"><?= t('checkout.shipping.phone') ?></label>
                    <input id="shipPhone" type="tel" autocomplete="tel" placeholder="<?= t('checkout.shipping.phone_placeholder') ?>" required maxlength="30" style="width:100%;">
                </div>
                <div class="checkout-field">
                    <label for="shipStreet"><?= t('checkout.shipping.street') ?></label>
                    <input id="shipStreet" type="text" autocomplete="shipping street-address" placeholder="<?= t('checkout.shipping.street_placeholder') ?>" required maxlength="200" style="width:100%;">
                </div>
                <div style="display:flex;gap:10px;">
                    <div class="checkout-field" style="flex:2;">
                        <label for="shipCity"><?= t('checkout.shipping.city') ?></label>
                        <input id="shipCity" type="text" autocomplete="shipping address-level2" required maxlength="80" style="width:100%;">
                    </div>
                    <div class="checkout-field" style="flex:1;">
                        <label for="shipPostal"><?= t('checkout.shipping.postal') ?></label>
                        <input id="shipPostal" type="text" autocomplete="shipping postal-code" required maxlength="16" style="width:100%;">
                    </div>
                </div>
                <div class="checkout-field">
                    <label for="shipCountry"><?= t('checkout.shipping.country') ?></label>
                    <input id="shipCountry" type="text" autocomplete="shipping country-name" value="Cyprus" required maxlength="60" style="width:100%;">
                </div>
                <?php if (!Auth::check()): ?>
                <!-- Guests have no account email — collect one for order contact -->
                <div class="checkout-field">
                    <label for="shipEmail"><?= t('checkout.shipping.email') ?></label>
                    <input id="shipEmail" type="email" autocomplete="email" placeholder="<?= t('checkout.shipping.email_placeholder') ?>" required maxlength="254" style="width:100%;">
                    <small style="display:block;color:var(--ink-soft,#666);margin-top:3px;font-size:0.82rem;"><?= t('checkout.shipping.email_note') ?></small>
                </div>
                <?php endif; ?>
                <div id="shippingError" style="display:none;color:#dc3545;font-size:0.9rem;margin-top:4px;"><?= t('checkout.shipping.required') ?></div>
            </div>

            <div class="checkout-agreement">
                <label class="checkout-checkbox-label">
                    <input type="checkbox" id="agreeTerms">
                    <span class="checkmark"></span>
                    <span><?= t('checkout.review.terms', false,
                        ['terms' => '<a href="/terms" target="_blank">' . t('checkout.review.terms_link', false) . '</a>',
                         'privacy' => '<a href="/privacy" target="_blank">' . t('checkout.review.privacy_link', false) . '</a>']) ?></span>
                </label>
            </div>

            <div class="checkout-actions">
                <button class="checkout-btn-secondary" type="button" onclick="goBackToPayment()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    <?= t('checkout.review.back') ?>
                </button>
                <button class="checkout-btn-confirm" id="confirmCheckout" type="button" onclick="submitOrder()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                    <?= t('checkout.review.place_order') ?> <span id="modal-pay-amount">€<?= number_format($cartTotal ?? 0, 2) ?></span>
                </button>
            </div>
        </div>

        <!-- Security Assurance Footer -->
        <div class="checkout-security-footer">
            <div class="security-badges">
                <span class="security-badge">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    SSL Encrypted
                </span>
                <span class="security-badge">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    PCI Compliant
                </span>
                <span class="security-badge">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                    Secure Payment
                </span>
            </div>
        </div>

    </div>
</div>

<!-- Remove Item Confirmation Modal -->
<div id="removeConfirmOverlay" class="confirm-overlay" onclick="if(event.target===this)closeRemoveConfirm()" role="dialog" aria-modal="true" aria-labelledby="removeConfirmTitle">
    <div class="confirm-dialog">
        <div class="confirm-icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
        </div>
        <h3 id="removeConfirmTitle" class="confirm-title"><?= t('cart.remove.title') ?></h3>
        <p class="confirm-lead"><?= t('cart.remove.lead') ?></p>
        <div class="confirm-actions">
            <button type="button" class="confirm-btn confirm-btn-secondary" onclick="closeRemoveConfirm()"><?= t('cart.remove.cancel') ?></button>
            <button type="button" id="confirmRemoveBtn" class="confirm-btn confirm-btn-danger" onclick="confirmRemove()"><?= t('cart.remove.confirm') ?></button>
        </div>
    </div>
</div>


<script>
// ==================== CSRF helper ====================
function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

function jsonPost(url, body) {
    return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
        body: JSON.stringify(body)
    });
}

// ==================== Stripe ====================
const STRIPE_PK = <?= json_encode(Env::get('STRIPE_PUBLISHABLE_KEY', '')) ?>;
let stripe = null;
let stripeElements = null;
if (STRIPE_PK && /^pk_(test|live)_/.test(STRIPE_PK) && typeof Stripe !== 'undefined') {
    try {
        stripe = Stripe(STRIPE_PK);
        stripeElements = stripe.elements();
    } catch (e) {
        console.error('Stripe failed to initialise:', e);
    }
}
let   cardElement    = null;
let   stripeCardComplete = false;
let   stripeCardBrand    = null;

function mountStripeElement() {
    if (cardElement) return;
    if (!stripeElements) {
        const el = document.getElementById('stripe-card-element');
        if (el) el.innerHTML = '<span style="color:#f87171;font-size:0.85rem;">Stripe is not configured. Set STRIPE_PUBLISHABLE_KEY in .env</span>';
        return;
    }
    cardElement = stripeElements.create('card', {
        style: {
            base: {
                color: '#e2e8f0',
                fontFamily: 'inherit',
                fontSize: '15px',
                fontSmoothing: 'antialiased',
                '::placeholder': { color: '#475569' },
            },
            invalid: { color: '#f87171', iconColor: '#f87171' },
        },
        hidePostalCode: true,
    });
    cardElement.mount('#stripe-card-element');
    cardElement.on('change', function (event) {
        stripeCardComplete = event.complete;
        stripeCardBrand    = event.brand || null;
        const errEl = document.getElementById('stripe-card-errors');
        errEl.textContent = event.error ? event.error.message : '';
    });
}

// ==================== Card brand SVGs (for review display) ====================
const brandSVGs = {
    visa:       `<img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 48 32'%3E%3Crect width='48' height='32' rx='4' fill='%231a1f71'/%3E%3Cpath d='M19.5 21.5h-3l1.9-11h3l-1.9 11zm12.8-10.7c-.6-.2-1.5-.5-2.7-.5-3 0-5 1.5-5 3.7 0 1.6 1.5 2.5 2.6 3s1.5 1 1.5 1.4c0 .8-.9 1.1-1.7 1.1-1.1 0-1.8-.2-2.7-.6l-.4-.2-.4 2.5c.7.3 1.9.6 3.2.6 3.2 0 5.2-1.5 5.2-3.8 0-1.3-.8-2.2-2.5-3-.7-.6-1.5-.9-1.5-1.4 0-.5.5-1 1.5-1 .9 0 1.5.2 2 .4l.2.1.5-2.3zm7.9-.3h-2.3c-.7 0-1.3.2-1.6 1l-4.5 10.5h3.2l.6-1.7h3.9l.4 1.7h2.8l-2.5-11.5zm-3.7 7.4l1.6-4.3.9 4.3h-2.5zM17.2 10.5l-2.8 7.5-.3-1.5c-.5-1.7-2.1-3.6-3.9-4.5l2.7 9.5h3.2l4.8-11h-3.7z' fill='white'/%3E%3Cpath d='M12.1 10.5H7.2l0 .3c3.8.9 6.3 3.2 7.3 5.9l-1.1-5.2c-.2-.8-.7-1-1.3-1z' fill='%23f7b600'/%3E%3C/svg%3E" alt="Visa" style="height:22px;">`,
    mastercard: `<img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 48 32'%3E%3Crect width='48' height='32' rx='4' fill='%23252525'/%3E%3Ccircle cx='19' cy='16' r='9' fill='%23eb001b'/%3E%3Ccircle cx='29' cy='16' r='9' fill='%23f79e1b'/%3E%3Cpath d='M24 9.3a9 9 0 013 6.7 9 9 0 01-3 6.7 9 9 0 01-3-6.7 9 9 0 013-6.7z' fill='%23ff5f00'/%3E%3C/svg%3E" alt="Mastercard" style="height:22px;">`,
    amex:       `<img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 48 32'%3E%3Crect width='48' height='32' rx='4' fill='%23016fd0'/%3E%3Cpath d='M24 6l-10 10 10 10 10-10L24 6z' fill='none' stroke='white' stroke-width='1.5'/%3E%3Ctext x='24' y='19' text-anchor='middle' font-size='7' font-weight='bold' fill='white' font-family='Arial'%3EAMEX%3C/text%3E%3C/svg%3E" alt="Amex" style="height:22px;">`,
};

function updateAllPrices(total, count) {
    const fmt = '€' + total.toFixed(2);
    const pcs  = count + ' pcs';
    const items = count + (count === 1 ? ' item' : ' items');
    const prods = count + (count === 1 ? ' product' : ' products');

    // Cart summary sidebar
    const subtotalEl = document.getElementById('cart-subtotal');
    if (subtotalEl) subtotalEl.textContent = fmt;
    const cartTotalEl = document.getElementById('cart-total');
    if (cartTotalEl) cartTotalEl.textContent = fmt;
    const itemsCountEl = document.getElementById('items-count');
    if (itemsCountEl) itemsCountEl.textContent = pcs;

    // Checkout modal — step 1
    const modalTotal = document.getElementById('modal-total-price');
    if (modalTotal) modalTotal.textContent = fmt;
    const modalItemsCount = document.getElementById('modal-items-count');
    if (modalItemsCount) modalItemsCount.textContent = items;

    // Checkout modal — step 2 review
    const modalReviewItems = document.getElementById('modal-review-items');
    if (modalReviewItems) modalReviewItems.textContent = prods;
    const modalReviewSubtotal = document.getElementById('modal-review-subtotal');
    if (modalReviewSubtotal) modalReviewSubtotal.textContent = fmt;
    const modalReviewTotal = document.getElementById('modal-review-total');
    if (modalReviewTotal) modalReviewTotal.textContent = fmt;

    // Pay button
    const payAmount = document.getElementById('modal-pay-amount');
    if (payAmount) payAmount.textContent = fmt;
}

async function updateQuantity(cartItemId, delta) {
    const card = document.querySelector(`.cart-item-card[data-item-id="${cartItemId}"]`);
    const qtyEl = document.getElementById(`qty-${cartItemId}`);
    if (!card || !qtyEl) return;
    
    const currentQty = parseInt(qtyEl.textContent) || 1;
    const newQty = currentQty + delta;
    
    if (newQty < 1) return;
    
    // Optimistic update
    qtyEl.textContent = newQty;
    
    // Update minus button state
    const minusBtn = card.querySelector('.qty-btn');
    if (minusBtn) minusBtn.disabled = newQty <= 1;
    
    try {
        const response = await jsonPost('/cart/update-quantity', { cart_item_id: cartItemId, quantity: newQty });
        
        const data = await response.json();
        
        if (data.success) {
            // Update line total
            const lineTotalEl = card.querySelector('.cart-item-line-total');
            if (lineTotalEl) {
                lineTotalEl.textContent = '€' + data.line_total.toFixed(2);
            }

            // Update all prices (sidebar + checkout modal)
            updateAllPrices(data.cart_total, data.cart_count);

            // Update header cart count
            const cartCountEl = document.getElementById('cart-count');
            if (cartCountEl) {
                cartCountEl.textContent = data.cart_count;
            }
        } else {
            // Revert on error
            qtyEl.textContent = currentQty;
            alert(data.error || window.I18N.t('checkout.errors.generic'));
        }
    } catch (error) {
        console.error('Error updating quantity:', error);
        qtyEl.textContent = currentQty;
        alert(window.I18N.t('checkout.errors.generic'));
    }
}

let _pendingRemoveId = null;

function removeFromCart(cartItemId) {
    _pendingRemoveId = cartItemId;
    const overlay = document.getElementById('removeConfirmOverlay');
    overlay.style.display = 'flex';
}

function closeRemoveConfirm() {
    _pendingRemoveId = null;
    document.getElementById('removeConfirmOverlay').style.display = 'none';
}

async function confirmRemove() {
    const cartItemId = _pendingRemoveId;
    closeRemoveConfirm();
    if (!cartItemId) return;

    try {
        const response = await jsonPost('/cart/remove', { cart_item_id: cartItemId });
        
        const data = await response.json();
        
        if (data.success) {
            // Remove the item card with animation
            const card = document.querySelector(`.cart-item-card[data-item-id="${cartItemId}"]`);
            if (card) {
                card.style.transition = 'all 0.3s ease';
                card.style.opacity = '0';
                card.style.transform = 'translateX(-50px)';
                setTimeout(() => {
                    card.remove();

                    // Check if cart is now empty
                    const remainingItems = document.querySelectorAll('.cart-item-card');
                    if (remainingItems.length === 0) {
                        location.reload(); // Reload to show empty cart state
                    }
                }, 300);
            }

            // Update all prices (sidebar + checkout modal)
            updateAllPrices(data.cart_total, data.cart_count);

            // Update cart count in header
            const cartCountEl = document.getElementById('cart-count');
            if (cartCountEl) {
                if (data.cart_count > 0) {
                    cartCountEl.textContent = data.cart_count;
                } else {
                    cartCountEl.style.display = 'none';
                }
            }
        } else {
            alert(data.error || window.I18N.t('checkout.errors.generic'));
        }
    } catch (error) {
        console.error('Error removing item:', error);
        alert(window.I18N.t('checkout.errors.generic'));
    }
}

const checkoutModalOverlay = document.getElementById('checkoutModalOverlay');
const openCheckoutModalBtn = document.getElementById('openCheckoutModal');
const closeCheckoutModalBtn = document.getElementById('closeCheckoutModal');

// ==================== Payment Method ====================
let selectedPaymentMethod = 'card';

function selectPaymentMethod(method) {
    selectedPaymentMethod = method;
    document.querySelectorAll('.payment-method-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.method === method);
    });

    const cardForm = document.getElementById('paymentFormCard');
    const altForm = document.getElementById('paymentFormAlt');
    const altText = document.getElementById('altPaymentText');
    const altIcon = document.getElementById('altPaymentIcon');

    if (method === 'card') {
        cardForm.style.display = 'block';
        altForm.style.display = 'none';
    } else {
        cardForm.style.display = 'none';
        altForm.style.display = 'block';

        // One neutral "you'll be redirected" mark for every off-site method.
        // These were coloured emoji standing in for brand logos (a blue circle
        // for Revolut, an apple for Apple Pay), which read as placeholders.
        const REDIRECT_ICON =
            '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor"' +
            ' stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
            '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>' +
            '<polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';
        const messages = {
            revolut:   'You will be redirected to Revolut to authorise your payment.',
            paypal:    'You will be redirected to PayPal to complete your payment.',
            gcash:     'You will be redirected to GCash to complete your payment.',
            applepay:  'Confirm payment with Apple Pay using Face ID or Touch ID.',
            googlepay: 'Confirm payment with Google Pay.'
        };
        altIcon.innerHTML = REDIRECT_ICON;
        altText.textContent = messages[method] || 'You will be redirected to complete your payment.';
    }
}

// ==================== Checkout Modal Navigation ====================
function openCheckoutModal() {
    if (checkoutModalOverlay) {
        checkoutModalOverlay.style.display = 'flex';
        checkoutModalOverlay.setAttribute('aria-hidden', 'false');
        goToStep(1);
        selectPaymentMethod('card');
        // Mount Stripe element after the modal is visible so it has dimensions
        requestAnimationFrame(mountStripeElement);
    }
}

function closeCheckoutModal() {
    if (checkoutModalOverlay) {
        checkoutModalOverlay.style.display = 'none';
        checkoutModalOverlay.setAttribute('aria-hidden', 'true');
    }
}

function goToStep(step) {
    document.querySelectorAll('.checkout-step-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.checkout-step').forEach(s => {
        const sNum = parseInt(s.dataset.step);
        s.classList.remove('active', 'completed');
        if (sNum < step) s.classList.add('completed');
        if (sNum === step) s.classList.add('active');
    });
    document.querySelectorAll('.checkout-step-line').forEach(l => {
        l.classList.toggle('active', step > 1);
    });
    const panel = document.getElementById('checkoutStep' + step);
    if (panel) panel.classList.add('active');
}

function goToReview() {
    if (selectedPaymentMethod === 'card') {
        const nameEl = document.getElementById('cardName');
        const zipEl  = document.getElementById('billingZip');
        let valid = true;

        if (!nameEl.value.trim()) {
            nameEl.classList.add('error'); valid = false;
        } else { nameEl.classList.remove('error'); }

        if (!zipEl.value.trim()) {
            zipEl.classList.add('error'); valid = false;
        } else { zipEl.classList.remove('error'); }

        if (!stripeCardComplete) {
            document.getElementById('stripe-card-errors').textContent = 'Please complete your card details.';
            valid = false;
        }

        if (!valid) return;

        const brand      = stripeCardBrand || 'card';
        const brandLabel = brand.charAt(0).toUpperCase() + brand.slice(1);
        document.getElementById('reviewCardInfo').innerHTML =
            `${brandSVGs[brand] || ''} <span style="margin-left:8px;">${brandLabel}</span>`;
    } else {
        const labels = { revolut: 'Revolut', paypal: 'PayPal', applepay: 'Apple Pay', googlepay: 'Google Pay' };
        document.getElementById('reviewCardInfo').textContent = labels[selectedPaymentMethod] || selectedPaymentMethod;
    }

    goToStep(2);
}

function goBackToPayment() {
    goToStep(1);
}

// ==================== Submit Order ====================
// Collect + validate the shipping address. Returns the object, or null (and
// highlights the missing fields) when incomplete.
function collectShippingAddress() {
    const fields = ['shipName', 'shipPhone', 'shipStreet', 'shipCity', 'shipPostal', 'shipCountry'];
    const values = {};
    let ok = true;
    for (const id of fields) {
        const el = document.getElementById(id);
        const v = (el.value || '').trim();
        values[id] = v;
        el.style.borderColor = v ? '' : '#dc3545';
        if (!v) ok = false;
    }
    document.getElementById('shippingError').style.display = ok ? 'none' : '';
    if (!ok) {
        document.getElementById('shipName').closest('.review-order-summary').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return null;
    }
    const address = {
        name:    values.shipName,
        phone:   values.shipPhone,
        street:  values.shipStreet,
        city:    values.shipCity,
        postal:  values.shipPostal,
        country: values.shipCountry,
    };

    // Guest checkout renders an email field; logged-in users don't have one.
    const emailEl = document.getElementById('shipEmail');
    if (emailEl) {
        const email = (emailEl.value || '').trim();
        const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        emailEl.style.borderColor = emailOk ? '' : '#dc3545';
        if (!emailOk) {
            document.getElementById('shippingError').style.display = '';
            emailEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return null;
        }
        address.email = email;
    }

    return address;
}

async function submitOrder() {
    const agreeCheckbox = document.getElementById('agreeTerms');
    if (!agreeCheckbox.checked) {
        agreeCheckbox.parentElement.style.color = '#f87171';
        agreeCheckbox.focus();
        setTimeout(() => { agreeCheckbox.parentElement.style.color = ''; }, 2000);
        return;
    }

    // Shipping address is required BEFORE any charge is attempted, so a
    // validation failure can never leave a paid-but-unplaceable order.
    const shippingAddress = collectShippingAddress();
    if (!shippingAddress) return;

    const confirmBtn = document.getElementById('confirmCheckout');
    confirmBtn.disabled = true;
    confirmBtn.classList.add('btn-loading');

    try {
        if (selectedPaymentMethod === 'card') {
            if (!stripe || !cardElement) {
                alert('Stripe is not loaded. Please refresh the page.');
                return;
            }

            // 1. Create PaymentIntent server-side (amount calculated from cart in DB)
            const piRes  = await jsonPost('/api/create-payment-intent', {});
            const piData = await piRes.json();
            if (piData.error) {
                alert(piData.error);
                return;
            }

            // 2. Confirm card payment via Stripe.js (card data never touches our server)
            const { paymentIntent, error } = await stripe.confirmCardPayment(piData.clientSecret, {
                payment_method: {
                    card: cardElement,
                    billing_details: {
                        name: document.getElementById('cardName').value.trim(),
                        address: { postal_code: document.getElementById('billingZip').value.trim() },
                    },
                },
            });

            if (error) {
                document.getElementById('stripe-card-errors').textContent = error.message;
                goToStep(1);
                return;
            }

            // 3. Create order in DB using the verified PaymentIntent ID
            const orderRes  = await jsonPost('/checkout', {
                payment_method: 'card',
                stripe_payment_intent_id: paymentIntent.id,
                shipping_address: shippingAddress,
            });
            const orderData = await orderRes.json();

            if (orderData.success) {
                closeCheckoutModal();
                showOrderSuccess(orderData.order_id, orderData.tracking_number);
            } else {
                alert(orderData.error || window.I18N.t('checkout.errors.generic'));
            }
        } else {
            // Non-card methods (future: Revolut, PayPal, etc.)
            const orderRes  = await jsonPost('/checkout', { payment_method: selectedPaymentMethod, shipping_address: shippingAddress });
            const orderData = await orderRes.json();
            if (orderData.success) {
                closeCheckoutModal();
                showOrderSuccess(orderData.order_id, orderData.tracking_number);
            } else {
                alert(orderData.error || window.I18N.t('checkout.errors.generic'));
            }
        }
    } catch (err) {
        console.error('Checkout error:', err);
        alert(window.I18N.t('checkout.errors.generic'));
    } finally {
        confirmBtn.disabled = false;
        confirmBtn.classList.remove('btn-loading');
    }
}

function showOrderSuccess(orderId, trackingNumber) {
    const i18n = window.I18N || {};
    const _t = (k, p) => i18n.t ? i18n.t(k, p) : k;
    const isLoggedIn = <?= Auth::check() ? 'true' : 'false' ?>;
    // Guests have no order-history page — their tracking number IS the record,
    // so surface it prominently and link the tracker instead of /account.
    const trackingBlock = trackingNumber ? `
            <p style="margin:10px 0 2px;font-size:0.85rem;">${_t('checkout.success.tracking')}</p>
            <div class="order-id-display" style="letter-spacing:0.12em;">${trackingNumber}</div>
            <p style="font-size:0.8rem;">${_t('checkout.success.tracking_note')}</p>` : '';
    const secondAction = isLoggedIn
        ? `<a href="/account" class="order-success-action order-success-action-primary">${_t('checkout.success.view')}</a>`
        : `<a href="/track-order?code=${encodeURIComponent(trackingNumber || '')}" class="order-success-action order-success-action-primary">${_t('info.track.search_btn')}</a>`;
    const overlay = document.createElement('div');
    overlay.className = 'order-success-overlay';
    overlay.innerHTML = `
        <div class="order-success-card">
            <div class="success-check">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h2>${_t('checkout.success.title')}</h2>
            <p>${_t('checkout.success.thanks')}</p>
            <div class="order-id-display">${_t('checkout.success.order_id', {id: orderId || ''})}</div>
            ${trackingBlock}
            <p style="font-size:0.85rem;">${_t('checkout.success.email')}</p>
            <div class="order-success-actions">
                <a href="/shop" class="order-success-action order-success-action-secondary">${_t('checkout.success.continue')}</a>
                ${secondAction}
            </div>
        </div>
    `;
    document.body.appendChild(overlay);
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            overlay.remove();
            window.location.reload();
        }
    });
}

// ==================== Event Listeners ====================
if (openCheckoutModalBtn) {
    openCheckoutModalBtn.addEventListener('click', openCheckoutModal);
}
if (closeCheckoutModalBtn) {
    closeCheckoutModalBtn.addEventListener('click', closeCheckoutModal);
}
if (checkoutModalOverlay) {
    checkoutModalOverlay.addEventListener('click', (event) => {
        if (event.target === checkoutModalOverlay) {
            closeCheckoutModal();
        }
    });
}

// Keyboard: Escape to close
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && checkoutModalOverlay && checkoutModalOverlay.style.display === 'flex') {
        closeCheckoutModal();
    }
});
</script>

<script src="https://js.stripe.com/v3/"></script>
<?php require __DIR__ . '/../layouts/customer_footer.php'; ?>
