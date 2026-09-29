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
                        <?php // Ghost, not a red outlined button. Remove was the
                              // loudest control in every row, competing with the
                              // quantity stepper and pulling the eye toward
                              // deleting rather than buying. ?>
                        <button class="btn btn-sm btn-ghost cart-remove-btn" onclick="removeFromCart(<?= $item['id'] ?>)">
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

function updateAllPrices(total, count) {
    const fmt = '€' + total.toFixed(2);

    // Cart summary sidebar
    const subtotalEl = document.getElementById('cart-subtotal');
    if (subtotalEl) subtotalEl.textContent = fmt;
    const cartTotalEl = document.getElementById('cart-total');
    if (cartTotalEl) cartTotalEl.textContent = fmt;
    // Same bare number the page renders on load (was an English "3 pcs").
    const itemsCountEl = document.getElementById('items-count');
    if (itemsCountEl) itemsCountEl.textContent = count;
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
            UI.error(data.error || window.I18N.t('checkout.errors.generic'));
        }
    } catch (error) {
        console.error('Error updating quantity:', error);
        qtyEl.textContent = currentQty;
        UI.error(window.I18N.t('checkout.errors.generic'));
    }
}

let _pendingRemoveId = null;

function removeFromCart(cartItemId) {
    _pendingRemoveId = cartItemId;
    const overlay = document.getElementById('removeConfirmOverlay');
    // Every direct child of <body> is its own z-index:1 layer (style.css, to
    // sit above the paper grain). Inside <main> this dialog's z-index only
    // counted within main, so the footer painted over it.
    if (overlay.parentNode !== document.body) document.body.appendChild(overlay);
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
            UI.error(data.error || window.I18N.t('checkout.errors.generic'));
        }
    } catch (error) {
        console.error('Error removing item:', error);
        UI.error(window.I18N.t('checkout.errors.generic'));
    }
}
</script>

<?php require __DIR__ . '/../layouts/customer_footer.php'; ?>
