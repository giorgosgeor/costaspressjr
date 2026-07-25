// Tab switching
function switchTab(tabName) {
    document.querySelectorAll('.account-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    const tab = document.querySelector(`.account-tab[data-tab="${tabName}"]`);
    const content = document.getElementById('tab-' + tabName);
    if (tab) tab.classList.add('active');
    if (content) content.classList.add('active');
}

document.querySelectorAll('.account-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        switchTab(this.dataset.tab);
    });
});

// Support ?tab= query param (e.g. from order detail back link)
(function() {
    const params = new URLSearchParams(window.location.search);
    const tabParam = params.get('tab');
    if (tabParam) switchTab(tabParam);
})();

// Cart Modal State
let cartModalState = {
    designId: null,
    productId: null,
    productName: '',
    designName: '',
    basePrice: 0,
    selectedSize: null,
    selectedColor: null,
    quantity: 1,
    variants: [],
    sizes: [],
    colors: []
};

let pendingDeleteDesignId = null;

function deleteDesign(designId, designName) {
    pendingDeleteDesignId = designId;
    document.getElementById('deleteDesignNameText').textContent = designName;
    document.getElementById('deleteConfirmModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteConfirmModal').style.display = 'none';
    pendingDeleteDesignId = null;
}

function confirmDelete() {
    if (!pendingDeleteDesignId) return;

    const btn = document.getElementById('confirmDeleteBtn');
    btn.disabled = true;
    btn.textContent = (window.I18N ? window.I18N.t('account.deleting') : 'Deleting...');

    fetch('/custom-design/delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ design_id: pendingDeleteDesignId })
    })
    .then(response => {
        if (response.ok) {
            const card = document.querySelector(`.design-card[data-design-id="${pendingDeleteDesignId}"]`);
            if (card) card.remove();
            closeDeleteModal();
            const remainingCards = document.querySelectorAll('.design-card');
            if (remainingCards.length === 0) location.reload();
        } else {
            return response.text().then(text => { throw new Error(text); });
        }
    })
    .catch(err => {
        var msg = (window.I18N ? window.I18N.t('account.delete_failed', { error: err.message }) : 'Failed to delete design: ' + err.message);
        alert(msg);
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = (window.I18N ? window.I18N.t('account.delete') : 'Delete');
    });
}

function addDesignToCart(designData) {
    if (typeof designData === 'string') {
        designData = JSON.parse(designData);
    }

    cartModalState.designId = designData.id;
    cartModalState.productId = designData.productId;
    cartModalState.productName = designData.productName;
    cartModalState.designName = designData.designName;
    cartModalState.basePrice = parseFloat(designData.basePrice) || 0;
    cartModalState.selectedSize = null;
    cartModalState.selectedColor = null;
    cartModalState.quantity = 1;
    cartModalState.colorHex = designData.colorHex || '#000000';
    cartModalState.elementsJson = designData.elementsJson || '{}';
    cartModalState.productImage = designData.productImage || '';
    cartModalState.uploads = designData.uploads || [];
    cartModalState.texts = designData.texts || [];
    cartModalState.frontPreviewPath = designData.frontPreviewPath || null;
    cartModalState.frontDesignPreviewPath = designData.frontDesignPreviewPath || null;
    cartModalState.editorDAWidth  = parseFloat(designData.editorDAWidth)  || 225;
    cartModalState.editorDAHeight = parseFloat(designData.editorDAHeight) || 300;

    document.getElementById('cartProductName').textContent = designData.productName;
    document.getElementById('cartDesignName').textContent = designData.designName;
    document.getElementById('cartQuantity').value = 1;
    document.getElementById('cartError').style.display = 'none';

    renderCartPreview();
    fetchProductVariants(designData.productId);
    updateCartPrices();

    document.getElementById('addToCartModal').style.display = 'flex';
}

// Convert hex color to HSL
function hexToHSL(hex) {
    hex = hex.replace('#', '');
    if (hex.length === 3) {
        hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
    }
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

    return { h: h * 360, s: s * 100, l: l * 100 };
}

// Apply color filter to product image (same algorithm as shop_custom.php)
function applyProductColorFilter(imgElement, hex) {
    if (!imgElement || !hex) return;

    hex = hex.trim();
    if (!hex.startsWith('#')) hex = '#' + hex;

    const hsl = hexToHSL(hex);
    const hexLower = hex.toLowerCase();

    const isWhite = hexLower === '#ffffff' || hexLower === '#fff' || hsl.l > 95;
    const isBlack = hexLower === '#000000' || hexLower === '#000' || hsl.l < 10;
    const isGray = hsl.s < 10;

    let filter;
    if (isWhite) {
        filter = 'saturate(0) brightness(2) contrast(0.8)';
    } else if (isBlack) {
        filter = 'saturate(0) brightness(0.65) contrast(1.1)';
    } else if (isGray) {
        const brightness = 0.2 + (hsl.l / 100) * 1.5;
        filter = `saturate(0) brightness(${brightness})`;
    } else {
        const hueRotate = hsl.h - 50;
        const isReddish = hsl.h <= 20 || hsl.h >= 340;
        let saturate = (hsl.s / 100) * 2 + 0.5;
        if (isReddish) saturate = (hsl.s / 100) * 3 + 1;

        let brightness;
        if (hsl.l < 30) {
            brightness = 0.3 + (hsl.l / 100) * 0.7;
        } else if (hsl.l < 50) {
            brightness = 0.5 + (hsl.l / 100) * 0.6;
        } else {
            brightness = 0.6 + (hsl.l / 100) * 0.5;
        }
        filter = `sepia(1) saturate(${saturate}) hue-rotate(${hueRotate}deg) brightness(${brightness})`;
    }

    imgElement.style.filter = filter;
}

function _getCartFrontElements() {
    let uploads = (cartModalState.uploads || []).filter(u => u.view_placement === 'front' || !u.view_placement);
    let texts   = (cartModalState.texts   || []).filter(t => t.view_placement === 'front' || !t.view_placement);

    // Fallback: parse elements_json when uploads table is empty
    if (uploads.length === 0 && texts.length === 0 && cartModalState.elementsJson) {
        try {
            const parsed = typeof cartModalState.elementsJson === 'string'
                ? JSON.parse(cartModalState.elementsJson) : cartModalState.elementsJson;
            const frontEls = Object.values(parsed).filter(el =>
                el && typeof el === 'object' && el.type &&
                (el.view === 'front' || !el.view)
            );
            uploads = frontEls.filter(el => el.type === 'image' && el.src)
                .map(el => ({ stored_file_path: el.src, view_placement: 'front',
                    position_x: el.x||0, position_y: el.y||0, width: el.width||80, height: el.height||80 }));
            texts = frontEls.filter(el => el.type === 'text')
                .map(el => ({ text_content: el.text||'', view_placement: 'front',
                    position_x: el.x||0, position_y: el.y||0, font_size: el.fontSize||24,
                    font_family: el.fontFamily||'Arial, sans-serif', text_color: el.color||'#000',
                    is_bold: el.bold?1:0, is_italic: el.italic?1:0, is_underline: el.underline?1:0 }));
        } catch(e) {}
    }
    return { uploads, texts };
}

function _drawCartDesignArea(editorDAW, editorDAH) {
    const designArea = document.getElementById('cartPreviewDesignArea');
    if (!designArea) return;
    designArea.innerHTML = '';

    const daW = designArea.offsetWidth  || Math.round(220 * 0.45);
    const daH = designArea.offsetHeight || Math.round(220 * 0.60);
    const { uploads, texts } = _getCartFrontElements();

    uploads.forEach((upload) => {
        if (!upload.stored_file_path) return;
        let src = upload.stored_file_path;
        if (!src.startsWith('data:')) {
            if (src.startsWith('public/')) src = src.substring(7);
            if (!src.startsWith('/') && !src.startsWith('http')) src = '/' + src;
        }
        const x = (parseFloat(upload.position_x) || 0) * (daW / editorDAW);
        const y = (parseFloat(upload.position_y) || 0) * (daH / editorDAH);
        const w = (parseFloat(upload.width)  || editorDAW) * (daW / editorDAW);
        const h = (parseFloat(upload.height) || editorDAH) * (daH / editorDAH);
        const img = document.createElement('img');
        img.src = src;
        img.style.cssText = `position:absolute;left:${x}px;top:${y}px;width:${w}px;height:${h}px;object-fit:contain;pointer-events:none;`;
        designArea.appendChild(img);
    });

    texts.forEach(text => {
        const x = (parseFloat(text.position_x) || 0) * (daW / editorDAW);
        const y = (parseFloat(text.position_y) || 0) * (daH / editorDAH);
        const fontSize = Math.max(6, (parseFloat(text.font_size)||24) * (daW / editorDAW));
        const el = document.createElement('div');
        el.textContent = text.text_content || '';
        el.style.cssText = `position:absolute;left:${x}px;top:${y}px;font-size:${fontSize}px;`
            + `font-family:${text.font_family||'Arial,sans-serif'};color:${text.text_color||'#000'};`
            + `font-weight:${text.is_bold==1?'bold':'normal'};font-style:${text.is_italic==1?'italic':'normal'};`
            + `text-decoration:${text.is_underline==1?'underline':'none'};white-space:nowrap;pointer-events:none;line-height:1;`;
        designArea.appendChild(el);
    });
}

function renderCartPreview() {
    const previewImg    = document.getElementById('cartPreviewImg');
    const productImg    = document.getElementById('cartProductImage');
    const colorOverlay  = document.getElementById('cartColorOverlay');
    const designOverlay = document.getElementById('cartDesignOverlay');
    const designArea    = document.getElementById('cartPreviewDesignArea');

    let imgSrc = cartModalState.productImage || '';
    imgSrc = imgSrc.replace(/^\/+/, '');
    if (imgSrc.startsWith('public/')) imgSrc = imgSrc.substring(7);
    if (imgSrc) imgSrc = '/' + imgSrc;

    colorOverlay.style.display = 'none';

    // Static composite cover (z-10): shown until user picks a different colour
    if (cartModalState.frontPreviewPath) {
        previewImg.src = cartModalState.frontPreviewPath;
        previewImg.style.display = 'block';
    } else {
        previewImg.style.display = 'none';
    }

    // Design-only transparent PNG overlay (preferred — pixel-perfect positioning)
    if (cartModalState.frontDesignPreviewPath) {
        designOverlay.src = cartModalState.frontDesignPreviewPath;
        designOverlay.style.display = 'block';
        if (designArea) designArea.innerHTML = '';
    } else {
        if (designOverlay) designOverlay.style.display = 'none';
        // Fallback: coordinate-based DOM elements
        let editorDAW = cartModalState.editorDAWidth  || 225;
        let editorDAH = cartModalState.editorDAHeight || 300;
        try {
            const _ej = typeof cartModalState.elementsJson === 'string'
                ? JSON.parse(cartModalState.elementsJson) : cartModalState.elementsJson;
            if (_ej && _ej._meta && _ej._meta.editorDAWidth  > 0) editorDAW = _ej._meta.editorDAWidth;
            if (_ej && _ej._meta && _ej._meta.editorDAHeight > 0) editorDAH = _ej._meta.editorDAHeight;
        } catch(e) {}
        _drawCartDesignArea(editorDAW, editorDAH);
    }

    productImg.onerror = function() { this.src = '/images/placeholder.png'; };
    productImg.src = imgSrc || '/images/placeholder.png';
    applyProductColorFilter(productImg, cartModalState.colorHex || '#000000');
    productImg.style.display = 'block';
}

function closeAddToCartModal() {
    document.getElementById('addToCartModal').style.display = 'none';
}

function fetchProductVariants(productId) {
    fetch('/api/product-variants/' + productId)
        .then(response => response.json())
        .then(data => {
            cartModalState.variants = data.variants || [];
            cartModalState.sizes = data.sizes || [];
            cartModalState.colors = data.colors || [];
            renderSizeOptions();
            renderColorOptions();
        })
        .catch(() => {});
}

function renderSizeOptions() {
    const container = document.getElementById('cartSizeOptions');
    container.innerHTML = '';

    cartModalState.sizes.forEach(size => {
        const btn = document.createElement('button');
        btn.className = 'cart-size-btn';
        btn.textContent = size.name;
        btn.dataset.sizeId = size.id;
        btn.style.cssText = 'padding:8px 16px; border:2px solid var(--border-light, rgba(255,255,255,0.2)); border-radius:8px; background:var(--bg-card-dark, rgba(255,255,255,0.05)); cursor:pointer; font-weight:500; color:var(--text-light, #fff);';

        const hasAvailable = cartModalState.variants.some(v => v.size_id == size.id && v.is_available);
        if (!hasAvailable) {
            btn.style.opacity = '0.4';
            btn.style.cursor = 'not-allowed';
            btn.disabled = true;
        }

        btn.onclick = function() {
            if (this.disabled) return;
            selectCartSize(size.id);
        };
        container.appendChild(btn);
    });
}

function renderColorOptions() {
    const container = document.getElementById('cartColorOptions');
    container.innerHTML = '';

    cartModalState.colors.forEach(color => {
        const btn = document.createElement('button');
        btn.className = 'cart-color-btn';
        btn.dataset.colorId = color.id;
        btn.dataset.colorHex = color.hex;
        btn.title = color.name;
        btn.style.cssText = `width:36px; height:36px; border-radius:50%; border:3px solid var(--border-light, rgba(255,255,255,0.2)); cursor:pointer; background:${color.hex};`;

        const hasAvailable = cartModalState.variants.some(v => v.color_id == color.id && v.is_available);
        if (!hasAvailable) {
            btn.style.opacity = '0.4';
            btn.style.cursor = 'not-allowed';
            btn.disabled = true;
        }

        btn.onclick = function() {
            if (this.disabled) return;
            selectCartColor(color.id, color.hex);
        };
        container.appendChild(btn);
    });
}

function selectCartSize(sizeId) {
    cartModalState.selectedSize = sizeId;

    document.querySelectorAll('.cart-size-btn').forEach(btn => {
        if (btn.dataset.sizeId == sizeId) {
            btn.style.borderColor = 'var(--primary, #2d5fff)';
            btn.style.background = 'rgba(45, 95, 255, 0.2)';
        } else {
            btn.style.borderColor = 'var(--border-light, rgba(255,255,255,0.2))';
            btn.style.background = 'var(--bg-card-dark, rgba(255,255,255,0.05))';
        }
    });

    updateColorAvailability();
}

function selectCartColor(colorId, colorHex) {
    cartModalState.selectedColor = colorId;
    cartModalState.colorHex = colorHex || '#000000';

    const previewImg    = document.getElementById('cartPreviewImg');
    const productImg    = document.getElementById('cartProductImage');
    const designOverlay = document.getElementById('cartDesignOverlay');

    // Hide the static composite cover — reveals colored shirt + design overlay below
    if (previewImg) previewImg.style.display = 'none';

    // Apply color filter ONLY to the shirt image
    if (productImg) applyProductColorFilter(productImg, cartModalState.colorHex);

    // Keep design overlay visible
    if (designOverlay && cartModalState.frontDesignPreviewPath) {
        designOverlay.style.display = 'block';
    }

    document.querySelectorAll('.cart-color-btn').forEach(btn => {
        if (btn.dataset.colorId == colorId) {
            btn.style.borderColor = 'var(--primary, #2d5fff)';
            btn.style.boxShadow = '0 0 0 2px var(--primary, #2d5fff)';
        } else {
            btn.style.borderColor = 'var(--border-light, rgba(255,255,255,0.2))';
            btn.style.boxShadow = 'none';
        }
    });

    updateSizeAvailability();
}

function updateColorAvailability() {
    if (!cartModalState.selectedSize) return;

    document.querySelectorAll('.cart-color-btn').forEach(btn => {
        const colorId = btn.dataset.colorId;
        const isAvailable = cartModalState.variants.some(v =>
            v.size_id == cartModalState.selectedSize &&
            v.color_id == colorId &&
            v.is_available
        );

        btn.style.opacity = isAvailable ? '1' : '0.3';
        btn.style.cursor = isAvailable ? 'pointer' : 'not-allowed';
        btn.disabled = !isAvailable;
    });
}

function updateSizeAvailability() {
    if (!cartModalState.selectedColor) return;

    document.querySelectorAll('.cart-size-btn').forEach(btn => {
        const sizeId = btn.dataset.sizeId;
        const isAvailable = cartModalState.variants.some(v =>
            v.color_id == cartModalState.selectedColor &&
            v.size_id == sizeId &&
            v.is_available
        );

        btn.style.opacity = isAvailable ? '1' : '0.3';
        btn.style.cursor = isAvailable ? 'pointer' : 'not-allowed';
        btn.disabled = !isAvailable;
    });
}

function adjustCartQuantity(delta) {
    const input = document.getElementById('cartQuantity');
    let qty = parseInt(input.value) || 1;
    qty = Math.max(1, Math.min(100, qty + delta));
    input.value = qty;
    cartModalState.quantity = qty;
    updateCartPrices();
}

function updateCartPrices() {
    const qty = parseInt(document.getElementById('cartQuantity').value) || 1;
    cartModalState.quantity = qty;

    // basePrice holds the SUPPLIER cost; apply the quantity-tiered margin so the
    // preview matches what the server charges.
    const category = window.Pricing ? Pricing.categoryFor('', cartModalState.productName) : 'tshirt';
    const unitRetail = window.Pricing
        ? Pricing.unitPrice(cartModalState.basePrice, category, qty)
        : cartModalState.basePrice;
    const total = unitRetail * qty;

    document.getElementById('cartBasePrice').textContent = '€' + total.toFixed(2);
    document.getElementById('cartTotalPrice').textContent = '€' + total.toFixed(2);
}

document.getElementById('cartQuantity').addEventListener('change', function() {
    let qty = parseInt(this.value) || 1;
    qty = Math.max(1, Math.min(100, qty));
    this.value = qty;
    updateCartPrices();
});

document.getElementById('confirmAddToCartBtn').addEventListener('click', function() {
    const errorEl = document.getElementById('cartError');

    if (!cartModalState.selectedSize) {
        errorEl.textContent = (window.I18N ? window.I18N.t('account.cart_modal.size_required') : 'Please select a size.');
        errorEl.style.display = 'block';
        return;
    }
    if (!cartModalState.selectedColor) {
        errorEl.textContent = (window.I18N ? window.I18N.t('account.cart_modal.color_required') : 'Please select a color.');
        errorEl.style.display = 'block';
        return;
    }

    errorEl.style.display = 'none';

    const cartData = {
        custom: true,
        design_id: cartModalState.designId,
        product_id: cartModalState.productId,
        size_id: cartModalState.selectedSize,
        color_id: cartModalState.selectedColor,
        quantity: cartModalState.quantity,
        custom_design_fee: 0
    };

    fetch('/cart/add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(cartData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.requireLogin) {
            redirectToLoginWithPendingCart(cartData);
            return;
        }
        if (!data.success) throw new Error(data.error || 'Failed to add to cart');
        closeAddToCartModal();
        showAccountCartToast();
        if (data.cart_item_id) {
            saveAccountCartPreview(data.cart_item_id, cartModalState.designId);
        }
    })
    .catch(() => {
        errorEl.textContent = (window.I18N ? window.I18N.t('account.cart_modal.failed') : 'Failed to add to cart. Please try again.');
        errorEl.style.display = 'block';
    });
});

function showAccountCartToast() {
    let toast = document.getElementById('accountCartToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'accountCartToast';
        toast.style.cssText = 'position:fixed;top:24px;left:50%;transform:translateX(-50%) translateY(-80px);background:#28a745;color:#fff;padding:14px 28px;border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,0.2);z-index:99999;font-weight:600;font-size:1rem;transition:transform 0.35s cubic-bezier(.4,0,.2,1),opacity 0.35s;opacity:0;pointer-events:none;white-space:nowrap;';
        toast.textContent = (window.I18N ? window.I18N.t('account.cart_modal.toast') : '✓ Design added to cart!');
        document.body.appendChild(toast);
    }
    requestAnimationFrame(() => {
        toast.style.transform = 'translateX(-50%) translateY(0)';
        toast.style.opacity = '1';
    });
    clearTimeout(toast._hideTimer);
    toast._hideTimer = setTimeout(() => {
        toast.style.transform = 'translateX(-50%) translateY(-80px)';
        toast.style.opacity = '0';
    }, 3000);
}

async function saveAccountCartPreview(cartItemId, designId) {
    try {
        const productImg = document.getElementById('cartProductImage');

        if (productImg && !productImg.complete) {
            await new Promise(resolve => {
                productImg.addEventListener('load',  resolve, { once: true });
                productImg.addEventListener('error', resolve, { once: true });
                setTimeout(resolve, 4000);
            });
        }

        const size = 400;
        const offscreen = document.createElement('canvas');
        offscreen.width  = size;
        offscreen.height = size;
        const ctx = offscreen.getContext('2d');

        // 1. Draw shirt with the currently-selected color filter
        if (productImg && productImg.complete && productImg.naturalWidth) {
            const filterStr = productImg.style.filter;
            if (filterStr) ctx.filter = filterStr;
            ctx.drawImage(productImg, 0, 0, size, size);
            ctx.filter = 'none';
        }

        // 2. Draw design layer — use the same transparent design PNG the modal uses
        if (cartModalState.frontDesignPreviewPath) {
            await new Promise(resolve => {
                const designImg = new Image();
                designImg.onload = () => {
                    ctx.drawImage(designImg, 0, 0, size, size);
                    resolve();
                };
                designImg.onerror = resolve;
                setTimeout(resolve, 3000);
                designImg.src = cartModalState.frontDesignPreviewPath;
            });
        } else {
            // Fallback: draw from coordinate data
            let frontUploads = (cartModalState.uploads || []).filter(u => u.view_placement === 'front' || !u.view_placement);
            let frontSaveTexts = (cartModalState.texts || []).filter(t => t.view_placement === 'front' || !t.view_placement);

            if (frontUploads.length === 0 && frontSaveTexts.length === 0 && cartModalState.elementsJson) {
                try {
                    const parsed = typeof cartModalState.elementsJson === 'string'
                        ? JSON.parse(cartModalState.elementsJson)
                        : cartModalState.elementsJson;
                    const frontEls = parsed['front'] || [];
                    frontUploads = frontEls.filter(el => el.type === 'image' && el.src)
                        .map(el => ({ stored_file_path: el.src, position_x: el.x||0, position_y: el.y||0, width: el.width||80, height: el.height||80 }));
                    frontSaveTexts = frontEls.filter(el => el.type === 'text')
                        .map(el => ({ text_content: el.text||'', position_x: el.x||0, position_y: el.y||0, font_size: el.fontSize||24, font_family: el.fontFamily||'Arial', text_color: el.color||'#000', is_bold: el.bold?1:0, is_italic: el.italic?1:0 }));
                } catch(e) {}
            }

            const daLeft   = size * 0.275;
            const daTop    = size * 0.25;
            const daWidth  = size * 0.45;
            const daHeight = size * 0.60;
            const editorDAW = 225;
            const editorDAH = 300;
            const scaleX = daWidth  / editorDAW;
            const scaleY = daHeight / editorDAH;

            await Promise.all(frontUploads.map(upload => {
                if (!upload.stored_file_path) return Promise.resolve();
                return new Promise(resolve => {
                    let src = upload.stored_file_path;
                    if (src.startsWith('public/')) src = src.substring(7);
                    if (!src.startsWith('/') && !src.startsWith('http')) src = '/' + src;
                    const img = new Image();
                    upload._saveImg = img;
                    img.onload  = resolve;
                    img.onerror = resolve;
                    setTimeout(resolve, 3000);
                    img.src = src;
                });
            }));

            ctx.save();
            ctx.beginPath();
            ctx.rect(daLeft, daTop, daWidth, daHeight);
            ctx.clip();
            frontUploads.forEach(upload => {
                if (!upload._saveImg || !upload._saveImg.complete || !upload._saveImg.naturalWidth) return;
                const x = daLeft + (parseFloat(upload.position_x) || 0) * scaleX;
                const y = daTop  + (parseFloat(upload.position_y) || 0) * scaleY;
                const w = (parseFloat(upload.width)  || editorDAW) * scaleX;
                const h = (parseFloat(upload.height) || editorDAH) * scaleY;
                ctx.drawImage(upload._saveImg, x, y, w, h);
            });

            frontSaveTexts.forEach(text => {
                const x = daLeft + (parseFloat(text.position_x) || 0) * scaleX;
                const y = daTop  + (parseFloat(text.position_y) || 0) * scaleY;
                const fontSize = Math.max(6, (parseFloat(text.font_size) || 24) * scaleX);
                ctx.font = `${text.is_italic==1?'italic':'normal'} ${text.is_bold==1?'bold':'normal'} ${fontSize}px ${text.font_family || 'Arial, sans-serif'}`;
                ctx.fillStyle = text.text_color || '#000000';
                ctx.fillText(text.text_content || '', x, y + fontSize);
            });
            ctx.restore();
        }

        let previewData;
        try {
            previewData = offscreen.toDataURL('image/png');
        } catch (secErr) {
            return;
        }

        await fetch('/cart/save-previews', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cart_item_id: cartItemId, design_id: designId, previews: { front: previewData } })
        });
    } catch (e) {}
}
