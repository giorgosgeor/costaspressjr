/* cart.js — from views/cart/show.php, loaded where the inline script used to run. */

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
    // Every direct child of <body> is its own z-index:1 layer (base.css, to
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
