/* cart-modal.js — from views/shop/designer.php, loaded where the inline script used to run. */

// Cart Modal State
let cartModalState = {
    designId: null,
    productId: null,
    productName: '',
    designName: '',
    basePrice: 0,
    designFee: 0,
    selectedSize: null,
    selectedColor: null,
    quantity: 1,
    variants: [],
    sizes: [],
    colors: []
};

function openAddToCartModal(designId, productId, productName, designName, basePrice, designFee) {
    cartModalState.designId = designId;
    cartModalState.productId = productId;
    cartModalState.productName = productName || 'Custom Product';
    cartModalState.designName = designName || 'Your Design';
    cartModalState.basePrice = parseFloat(basePrice) || 0;
    cartModalState.designFee = parseFloat(designFee) || 0;
    cartModalState.selectedSize = null;
    cartModalState.selectedColor = null;
    cartModalState.quantity = 1;
    cartModalState.currentColorHex = currentColorHex || '#ffffff';

    // Update UI
    document.getElementById('cartProductName').textContent = cartModalState.productName;
    document.getElementById('cartDesignName').textContent = cartModalState.designName;
    document.getElementById('cartQuantity').value = 1;
    document.getElementById('cartError').style.display = 'none';

    // Capture the design preview from the mockup container
    captureDesignPreview();

    // Fetch available variants for this product
    fetchProductVariants(productId);

    // Update prices
    updateCartPrices();

    document.getElementById('addToCartModal').style.display = 'flex';
}

// Helper function to convert hex to HSL for color filters
function cartHexToHSL(hex) {
    hex = hex.replace('#', '');
    if (hex.length === 3) {
        hex = hex.split('').map(c => c + c).join('');
    }
    const r = parseInt(hex.substring(0, 2), 16) / 255;
    const g = parseInt(hex.substring(2, 4), 16) / 255;
    const b = parseInt(hex.substring(4, 6), 16) / 255;

    const max = Math.max(r, g, b), min = Math.min(r, g, b);
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

// Apply color filter to cart preview product image
function applyCartProductColorFilter(hex) {
    const productImg = document.getElementById('cartPreviewProduct');
    if (!productImg || !hex) return;

    hex = hex.trim();
    if (!hex.startsWith('#')) hex = '#' + hex;

    const hexLower = hex.toLowerCase();
    const hsl = cartHexToHSL(hex);
    const isWhite = hexLower === '#ffffff' || hexLower === '#fff' || hsl.l > 95;
    const isBlack = hexLower === '#000000' || hexLower === '#000' || hsl.l < 10;
    const isGray = hsl.s < 10;

    const tintOverride = window.CostasTint && window.CostasTint.getOverride(hex);
    if (tintOverride) {
        productImg.style.filter = tintOverride;
    } else if (isWhite) {
        productImg.style.filter = 'grayscale(1) brightness(2.2) contrast(0.85)';
    } else if (isBlack) {
        productImg.style.filter = 'grayscale(1) brightness(0.45) contrast(1.2)';
    } else if (isGray) {
        const brightness = 0.2 + (hsl.l / 100) * 1.5;
        productImg.style.filter = `grayscale(1) brightness(${brightness})`;
    } else {
        const hueRotate = hsl.h - 38; // sepia base hue ≈ 38°
        const isReddish   = hsl.h <= 20 || hsl.h >= 340;
        const isYellowish = hsl.h >= 45 && hsl.h <= 80;
        let saturation = (hsl.s / 100) * 3 + 0.8;
        if (isReddish)   saturation = (hsl.s / 100) * 4 + 1.2;
        if (isYellowish) saturation = (hsl.s / 100) * 4 + 1.0;
        let brightness;
        if (hsl.l < 30)      brightness = 0.3 + (hsl.l / 100) * 0.7;
        else if (hsl.l < 50) brightness = 0.5 + (hsl.l / 100) * 0.6;
        else                 brightness = 0.6 + (hsl.l / 100) * 0.5;
        if (isYellowish && hsl.l >= 45) brightness = Math.min(brightness * 1.25, 1.5);
        productImg.style.filter = `grayscale(1) sepia(1) saturate(${saturation}) hue-rotate(${hueRotate}deg) brightness(${brightness})`;
    }
}

// Capture the current design into the cart preview using HTML elements
function captureDesignPreview() {
    const productImg = document.getElementById('mockupProduct');
    const previewProduct = document.getElementById('cartPreviewProduct');
    const designArea = document.getElementById('cartPreviewDesignArea');

    if (!previewProduct || !designArea) return;

    // Set product image
    if (productImg && productImg.src) {
        previewProduct.src = productImg.src;
    }

    // Apply color filter
    if (currentColorHex) {
        applyCartProductColorFilter(currentColorHex);
    }

    // Clear previous design elements
    designArea.innerHTML = '';

    // Compute editor design area dimensions from the product image (same approach as generateAndSavePreviews)
    // This is robust when the #designArea element has no offsetWidth (shop_custom.css not loaded on this page)
    const editorMockupImg = document.getElementById('mockupProduct');
    const editorImgW = editorMockupImg ? editorMockupImg.offsetWidth : 0;
    const editorImgH = editorMockupImg ? editorMockupImg.offsetHeight : 0;

    // Get design area percentages for current view (same defaults as generateAndSavePreviews)
    const p = window.currentProduct;
    const _daDefaults = {
        'front': { w: 45, h: 60 }, 'back': { w: 45, h: 60 },
        'left-sleeve': { w: 13, h: 16 }, 'right-sleeve': { w: 13, h: 16 }
    };
    const _daMap = {
        'front':        { w: p && p.da_front_w   != null ? p.da_front_w   : null, h: p && p.da_front_h   != null ? p.da_front_h   : null },
        'back':         { w: p && p.da_back_w    != null ? p.da_back_w    : null, h: p && p.da_back_h    != null ? p.da_back_h    : null },
        'left-sleeve':  { w: p && p.da_lsleeve_w != null ? p.da_lsleeve_w : null, h: p && p.da_lsleeve_h != null ? p.da_lsleeve_h : null },
        'right-sleeve': { w: p && p.da_rsleeve_w != null ? p.da_rsleeve_w : null, h: p && p.da_rsleeve_h != null ? p.da_rsleeve_h : null }
    };
    const _def = _daDefaults[currentView] || _daDefaults['front'];
    const _dm  = _daMap[currentView] || {};
    const _daW = _dm.w != null ? _dm.w : _def.w;
    const _daH = _dm.h != null ? _dm.h : _def.h;

    const editorDAWidth  = (editorImgW > 0) ? (_daW / 100) * editorImgW : 225;
    const editorDAHeight = (editorImgH > 0) ? (_daH / 100) * editorImgH : 300;

    // Cart preview design area is 45% of 200px = 90px wide, 60% of 200px = 120px tall
    const previewDAWidth = designArea.offsetWidth || 90;
    const previewDAHeight = designArea.offsetHeight || 120;

    const scaleX = editorDAWidth > 0 ? previewDAWidth / editorDAWidth : 1;
    const scaleY = editorDAHeight > 0 ? previewDAHeight / editorDAHeight : 1;

    // Get all design elements for current view
    const currentElements = elements[currentView] || [];

    currentElements.forEach(el => {
        if (el.type === 'image') {
            const img = document.createElement('img');
            let imgSrc = el.src || '';
            if (imgSrc && imgSrc.startsWith('public/')) {
                imgSrc = '/' + imgSrc.substring(7);
            } else if (imgSrc && !imgSrc.startsWith('/') && !imgSrc.startsWith('data:') && !imgSrc.startsWith('http')) {
                imgSrc = '/' + imgSrc;
            }
            img.src = imgSrc;
            const scaledW = (el.width || 80) * scaleX;
            const scaledH = (el.height || 80) * scaleY;
            const scaledX = (el.x || 0) * scaleX;
            const scaledY = (el.y || 0) * scaleY;
            let transforms = [];
            if (el.rotation) transforms.push('rotate(' + el.rotation + 'deg)');
            if (el.flipped) transforms.push('scaleX(-1)');
            img.style.cssText = `
                position: absolute;
                left: ${scaledX}px;
                top: ${scaledY}px;
                width: ${scaledW}px;
                height: ${scaledH}px;
                object-fit: contain;
                ${transforms.length ? 'transform: ' + transforms.join(' ') + ';' : ''}
            `;
            designArea.appendChild(img);
        } else if (el.type === 'text') {
            const textDiv = document.createElement('div');
            textDiv.textContent = el.text || '';
            const scaledX = (el.x || 0) * scaleX;
            const scaledY = (el.y || 0) * scaleY;
            const scaledFontSize = Math.max(6, (el.fontSize || 24) * scaleX);
            textDiv.style.cssText = `
                position: absolute;
                left: ${scaledX}px;
                top: ${scaledY}px;
                font-family: ${el.fontFamily || 'Arial'};
                font-size: ${scaledFontSize}px;
                color: ${el.color || '#000000'};
                font-weight: ${el.bold ? 'bold' : 'normal'};
                font-style: ${el.italic ? 'italic' : 'normal'};
                text-decoration: ${el.underline ? 'underline' : 'none'};
                white-space: nowrap;
                ${el.rotation ? 'transform: rotate(' + el.rotation + 'deg);' : ''}
            `;
            designArea.appendChild(textDiv);
        }
    });
}

// Update cart preview when color changes
function updateCartPreviewColor(hex) {
    cartModalState.currentColorHex = hex;
    applyCartProductColorFilter(hex);
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
        .catch(err => {
            console.error('Failed to fetch variants:', err);
            // Fallback: use product options from the page
            renderSizeOptionsFromPage();
            renderColorOptionsFromPage();
        });
}

function renderSizeOptions() {
    const container = document.getElementById('cartSizeOptions');
    container.innerHTML = '';

    cartModalState.sizes.forEach(size => {
        const btn = document.createElement('button');
        btn.className = 'cart-size-btn';
        btn.textContent = size.name;
        btn.dataset.sizeId = size.id;
        btn.type = 'button';

        // Check if this size has any available variants
        const hasAvailable = cartModalState.variants.some(v => v.size_id == size.id && v.is_available);
        if (!hasAvailable) btn.disabled = true;

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
        btn.title = color.name;
        btn.type = 'button';
        btn.setAttribute('aria-label', color.name);
        btn.style.backgroundColor = color.hex;
        if (/^#?f{3}(f{3})?$/i.test(color.hex || '')) btn.classList.add('is-white');

        // Check if this color has any available variants
        const hasAvailable = cartModalState.variants.some(v => v.color_id == color.id && v.is_available);
        if (!hasAvailable) btn.disabled = true;

        btn.onclick = function() {
            if (this.disabled) return;
            selectCartColor(color.id);
        };
        container.appendChild(btn);
    });
}

function renderSizeOptionsFromPage() {
    // Fallback: render from existing product options on page
    const container = document.getElementById('cartSizeOptions');
    container.innerHTML = '';

    if (!window.currentProduct) return;

    const sizeRadios = document.querySelectorAll(`input[name="size_${window.currentProduct.id}"]`);
    sizeRadios.forEach(radio => {
        const label = radio.parentElement;
        const sizeName = label ? label.textContent.trim() : radio.value;
        const btn = document.createElement('button');
        btn.className = 'cart-size-btn';
        btn.textContent = sizeName;
        btn.dataset.sizeId = radio.value;
        btn.type = 'button';
        btn.onclick = () => selectCartSize(radio.value);
        container.appendChild(btn);
    });
}

function renderColorOptionsFromPage() {
    // Fallback: render from existing color swatches on page
    const container = document.getElementById('cartColorOptions');
    container.innerHTML = '';

    if (!window.currentProduct) return;

    const swatches = document.querySelectorAll(`#colors-${window.currentProduct.id} .color-swatch`);
    swatches.forEach(swatch => {
        const btn = document.createElement('button');
        btn.className = 'cart-color-btn';
        btn.dataset.colorId = swatch.dataset.colorId;
        btn.title = swatch.title || 'Color';
        btn.type = 'button';
        btn.style.backgroundColor = swatch.style.backgroundColor;
        btn.onclick = () => selectCartColor(swatch.dataset.colorId);
        container.appendChild(btn);
    });
}

function selectCartSize(sizeId) {
    cartModalState.selectedSize = sizeId;

    document.querySelectorAll('.cart-size-btn').forEach(btn => {
        btn.classList.toggle('is-selected', btn.dataset.sizeId == sizeId);
    });

    // Update color availability based on selected size
    updateColorAvailability();
}

function selectCartColor(colorId) {
    cartModalState.selectedColor = colorId;

    // Find the color hex from the button or from cartModalState.colors
    let colorHex = '#ffffff';
    const colorBtn = document.querySelector(`.cart-color-btn[data-color-id="${colorId}"]`);
    if (colorBtn) {
        colorHex = colorBtn.style.backgroundColor || '#ffffff';
    }
    // Also check in colors array
    const colorObj = cartModalState.colors.find(c => c.id == colorId);
    if (colorObj && colorObj.hex) {
        colorHex = colorObj.hex;
    }

    // Update the preview color indicator
    updateCartPreviewColor(colorHex);

    document.querySelectorAll('.cart-color-btn').forEach(btn => {
        btn.classList.toggle('is-selected', btn.dataset.colorId == colorId);
    });

    // Update size availability based on selected color
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

    // basePrice holds the SUPPLIER cost; print add-ons (designFee) are the raw
    // pre-margin cost. Both are marked up through the quantity-tiered margin so
    // the preview matches what the server charges.
    const category = window.Pricing ? Pricing.categoryFor('', cartModalState.productName) : 'tshirt';
    const rawExtra = cartModalState.designFee || 0;
    const unitBase = window.Pricing
        ? Pricing.unitPrice(cartModalState.basePrice, category, qty)
        : cartModalState.basePrice;
    const unitAll = window.Pricing
        ? Pricing.unitPrice(cartModalState.basePrice, category, qty, rawExtra)
        : (cartModalState.basePrice + rawExtra);

    const baseTotal = unitBase * qty;
    const designFee = (unitAll - unitBase) * qty; // marked-up print add-ons
    const total = unitAll * qty;

    document.getElementById('cartBasePrice').textContent = '€' + baseTotal.toFixed(2);
    document.getElementById('cartDesignFee').textContent = '€' + designFee.toFixed(2);
    document.getElementById('cartTotalPrice').textContent = '€' + total.toFixed(2);
}

// Quantity input change handler
document.addEventListener('DOMContentLoaded', function() {
    const qtyInput = document.getElementById('cartQuantity');
    if (qtyInput) {
        qtyInput.addEventListener('change', function() {
            let qty = parseInt(this.value) || 1;
            qty = Math.max(1, Math.min(100, qty));
            this.value = qty;
            updateCartPrices();
        });
    }

    // Confirm add to cart button
    const confirmBtn = document.getElementById('confirmAddToCartBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            const errorEl = document.getElementById('cartError');

            if (!cartModalState.selectedSize) {
                errorEl.textContent = window.I18N.t('studio.cart.error_size');
                errorEl.style.display = 'block';
                return;
            }
            if (!cartModalState.selectedColor) {
                errorEl.textContent = window.I18N.t('studio.cart.error_color');
                errorEl.style.display = 'block';
                return;
            }

            errorEl.style.display = 'none';

            // Send add to cart request
            const cartData = {
                custom: true,
                design_id: cartModalState.designId,
                product_id: cartModalState.productId,
                size_id: cartModalState.selectedSize,
                color_id: cartModalState.selectedColor,
                quantity: cartModalState.quantity,
                custom_design_fee: cartModalState.designFee
            };

            // Show loading state
            const btn = document.getElementById('confirmAddToCartBtn');
            const originalText = btn.textContent;
            btn.textContent = window.I18N.t('studio.cart.adding');
            btn.disabled = true;

            fetch('/cart/add', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(cartData)
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(text || window.I18N.t('studio.cart.error_generic'));
                    });
                }
                return response.json();
            })
            .then(data => {
                btn.textContent = originalText;
                btn.disabled = false;

                if (data.success) {
                    // Generate previews with the cart-selected color for this cart item
                    generateAndSavePreviews(cartModalState.designId, {
                        colorHex: cartModalState.currentColorHex,
                        cartItemId: data.cart_item_id
                    });
                    // Show success notification
                    showCartSuccessNotification();
                    closeAddToCartModal();
                    // Stay on design saved modal so user can add more or checkout
                } else {
                    throw new Error(data.error || window.I18N.t('studio.cart.error_generic'));
                }
            })
            .catch(err => {
                btn.textContent = originalText;
                btn.disabled = false;
                console.error('Cart error:', err);
                errorEl.textContent = err.message || window.I18N.t('studio.cart.error_generic');
                errorEl.style.display = 'block';
            });
        });
    }
});

// Confirmation after a cart add: the site's shared toast (site/ui.js), the
// same one every other page uses, with a way on to the cart.
function showCartSuccessNotification() {
    UI.success(window.I18N.t('studio.cart.success_title'), {
        action: { label: window.I18N.t('view_design.modal.go_cart'), href: '/cart' }
    });
}

// "Size guide" link in the add-to-cart modal: the chart of whichever product
// is in the studio (data-on-click="openCurrentProductSizeGuide").
function openCurrentProductSizeGuide() {
    if (window.currentProduct && window.currentProduct.sizeChartImage) {
        openSizeGuide(window.currentProduct.sizeChartImage, window.currentProduct.name || 'Product');
    }
}
