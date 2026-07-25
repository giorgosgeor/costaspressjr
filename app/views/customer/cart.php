<?php
if (!Auth::check()) {
    header('Location: /login');
    exit;
}

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

// Generate CSS filter for product color
function getCartProductColorFilter($hex) {
    if (!$hex) return '';
    $hex = trim($hex);
    if ($hex[0] !== '#') $hex = '#' . $hex;
    $hexLower = strtolower($hex);
    $hsl = cartHexToHSL($hex);
    
    $isWhite = $hexLower === '#ffffff' || $hexLower === '#fff' || $hsl['l'] > 95;
    $isBlack = $hexLower === '#000000' || $hexLower === '#000' || $hsl['l'] < 10;
    $isGray = $hsl['s'] < 10;
    
    if ($isWhite) {
        return 'saturate(0) brightness(2) contrast(0.8)';
    } elseif ($isBlack) {
        return 'saturate(0) brightness(0.65) contrast(1.1)';
    } elseif ($isGray) {
        $brightness = 0.2 + ($hsl['l'] / 100) * 1.5;
        return "saturate(0) brightness($brightness)";
    } else {
        $hueRotate = $hsl['h'] - 50; // Sepia base is ~50deg
        if ($hueRotate < 0) $hueRotate += 360;
        $isReddish = $hsl['h'] <= 20 || $hsl['h'] >= 340;
        $saturate = ($hsl['s'] / 100) * 2 + 0.5;
        if ($isReddish) $saturate = ($hsl['s'] / 100) * 3 + 1;
        if ($hsl['l'] < 30) {
            $brightness = 0.3 + ($hsl['l'] / 100) * 0.7;
        } elseif ($hsl['l'] < 50) {
            $brightness = 0.5 + ($hsl['l'] / 100) * 0.6;
        } else {
            $brightness = 0.6 + ($hsl['l'] / 100) * 0.5;
        }
        return "sepia(1) saturate($saturate) hue-rotate({$hueRotate}deg) brightness($brightness)";
    }
}
?>

<section class="section cart-section">
    <div class="container">
        <h1>🛒 <?= t('cart.title') ?></h1>

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
                            <span class="badge-custom">✨ Custom</span>
                            <?php endif; ?>
                        </h3>
                        <div class="cart-item-meta">
                            <?php if (!empty($item['size_name'])): ?>
                            <span class="cart-item-meta-item">
                                📏 <?= htmlspecialchars($item['size_name']) ?>
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
                                🎨 Design fee: +€<?= number_format($item['custom_design_fee'], 2) ?>
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
                            🗑️ <?= t('cart.item.remove') ?>
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
            <div class="empty-state-icon">🛒</div>
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

            <div class="checkout-agreement">
                <label class="checkout-checkbox-label">
                    <input type="checkbox" id="agreeTerms">
                    <span class="checkmark"></span>
                    <span><?= t('checkout.review.terms', false,
                        ['terms' => '<a href="/info/terms" target="_blank">' . t('checkout.review.terms_link', false) . '</a>',
                         'privacy' => '<a href="/info/privacy" target="_blank">' . t('checkout.review.privacy_link', false) . '</a>']) ?></span>
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
<div id="removeConfirmOverlay" style="display:none;position:fixed;inset:0;z-index:50000;background:rgba(21,19,14,0.78);align-items:center;justify-content:center;" onclick="if(event.target===this)closeRemoveConfirm()">
    <div style="background:var(--paper);border:3px solid var(--ink);padding:30px 26px;max-width:400px;width:90%;box-shadow:8px 8px 0 var(--spot);text-align:center;">
        <div style="width:56px;height:56px;background:var(--spot);border:2px solid var(--ink);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:1.6rem;color:var(--paper);transform:rotate(-4deg);">🗑️</div>
        <h3 style="margin:0 0 8px;font-family:var(--font-display);font-size:1.6rem;letter-spacing:0.04em;color:var(--ink);text-transform:uppercase;"><?= t('cart.remove.title') ?></h3>
        <p style="margin:0 0 22px;color:var(--ink-soft);font-family:var(--font-type);font-size:0.95rem;"><?= t('cart.remove.lead') ?></p>
        <div style="display:flex;gap:12px;">
            <button onclick="closeRemoveConfirm()" style="flex:1;padding:11px;border:2px solid var(--ink);background:var(--paper-2);color:var(--ink);font-family:var(--font-display);font-size:0.95rem;letter-spacing:0.06em;text-transform:uppercase;cursor:pointer;box-shadow:3px 3px 0 var(--ink);"><?= t('cart.remove.cancel') ?></button>
            <button id="confirmRemoveBtn" onclick="confirmRemove()" style="flex:1;padding:11px;border:2px solid var(--ink);background:var(--spot);color:var(--paper);font-family:var(--font-display);font-size:0.95rem;letter-spacing:0.06em;text-transform:uppercase;cursor:pointer;box-shadow:3px 3px 0 var(--ink);"><?= t('cart.remove.confirm') ?></button>
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

        const messages = {
            revolut: { icon: '🔵', text: 'You will be redirected to Revolut to authorize your payment securely.' },
            paypal: { icon: '🅿️', text: 'You will be redirected to PayPal to complete your payment securely.' },
            gcash: { icon: '💚', text: 'You will be redirected to GCash to complete your payment securely.' },
            applepay: { icon: '🍎', text: 'Confirm payment with Apple Pay using Face ID or Touch ID.' },
            googlepay: { icon: '🔷', text: 'Confirm payment with Google Pay securely.' }
        };
        const msg = messages[method] || { icon: '💳', text: 'You will be redirected to complete your payment.' };
        altIcon.textContent = msg.icon;
        altText.textContent = msg.text;
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
async function submitOrder() {
    const agreeCheckbox = document.getElementById('agreeTerms');
    if (!agreeCheckbox.checked) {
        agreeCheckbox.parentElement.style.color = '#f87171';
        agreeCheckbox.focus();
        setTimeout(() => { agreeCheckbox.parentElement.style.color = ''; }, 2000);
        return;
    }

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
            });
            const orderData = await orderRes.json();

            if (orderData.success) {
                closeCheckoutModal();
                showOrderSuccess(orderData.order_id);
            } else {
                alert(orderData.error || window.I18N.t('checkout.errors.generic'));
            }
        } else {
            // Non-card methods (future: Revolut, PayPal, etc.)
            const orderRes  = await jsonPost('/checkout', { payment_method: selectedPaymentMethod });
            const orderData = await orderRes.json();
            if (orderData.success) {
                closeCheckoutModal();
                showOrderSuccess(orderData.order_id);
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

function showOrderSuccess(orderId) {
    const i18n = window.I18N || {};
    const _t = (k, p) => i18n.t ? i18n.t(k, p) : k;
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
            <p style="font-size:0.85rem;">${_t('checkout.success.email')}</p>
            <div class="order-success-actions">
                <a href="/shop" style="background:var(--paper-2);color:var(--ink);border:2px solid var(--ink);box-shadow:3px 3px 0 var(--ink);">${_t('checkout.success.continue')}</a>
                <a href="/account" style="background:var(--ink);color:var(--paper);border:2px solid var(--ink);box-shadow:3px 3px 0 var(--spot);">${_t('checkout.success.view')}</a>
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
