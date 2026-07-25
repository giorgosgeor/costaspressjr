// ============================================================
// shop_custom.js
// All non-PHP JavaScript extracted from shop_custom.php
// PHP data variables are defined in the inline data island in the view.
// ============================================================

// --------------- Cart Modal ---------------

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

    if (isWhite) {
        productImg.style.filter = 'saturate(0) brightness(2) contrast(0.8)';
    } else if (isBlack) {
        productImg.style.filter = 'saturate(0) brightness(0.4) contrast(1.2)';
    } else if (isGray) {
        const brightness = 0.2 + (hsl.l / 100) * 1.5;
        productImg.style.filter = `saturate(0) brightness(${brightness})`;
    } else {
        // Colorize using sepia base then hue-rotate
        const baseHue = 30; // Orange base
        const targetHue = hsl.h;
        let hueRotate = targetHue - baseHue;
        if (hueRotate < 0) hueRotate += 360;

        const saturation = Math.max(1, hsl.s / 50);
        const brightness = 0.4 + (hsl.l / 100) * 0.8;

        productImg.style.filter = `sepia(1) saturate(${saturation}) hue-rotate(${hueRotate}deg) brightness(${brightness})`;
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

    // Get the editor design area dimensions for scaling
    const editorDesignArea = document.getElementById('designArea');
    const editorDAWidth = editorDesignArea ? editorDesignArea.offsetWidth : 225;
    const editorDAHeight = editorDesignArea ? editorDesignArea.offsetHeight : 300;

    // Cart preview design area is 45% of 200px = 90px wide, 60% of 200px = 120px tall
    const previewDAWidth = designArea.offsetWidth || 90;
    const previewDAHeight = designArea.offsetHeight || 120;

    const scaleX = previewDAWidth / editorDAWidth;
    const scaleY = previewDAHeight / editorDAHeight;

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
        btn.style.cssText = 'padding:8px 16px; border:2px solid #ddd; border-radius:8px; background:#fff; cursor:pointer; font-weight:500; transition:all 0.2s;';

        // Check if this size has any available variants
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
        btn.title = color.name;
        btn.style.cssText = `width:36px; height:36px; border-radius:50%; border:3px solid #ddd; cursor:pointer; background:${color.hex}; transition:all 0.2s;`;

        // Check if this color has any available variants
        const hasAvailable = cartModalState.variants.some(v => v.color_id == color.id && v.is_available);
        if (!hasAvailable) {
            btn.style.opacity = '0.4';
            btn.style.cursor = 'not-allowed';
            btn.disabled = true;
        }

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
        btn.style.cssText = 'padding:8px 16px; border:2px solid #ddd; border-radius:8px; background:#fff; cursor:pointer; font-weight:500;';
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
        btn.style.cssText = `width:36px; height:36px; border-radius:50%; border:3px solid #ddd; cursor:pointer; background:${swatch.style.backgroundColor};`;
        btn.onclick = () => selectCartColor(swatch.dataset.colorId);
        container.appendChild(btn);
    });
}

function selectCartSize(sizeId) {
    cartModalState.selectedSize = sizeId;

    // Update button styles
    document.querySelectorAll('.cart-size-btn').forEach(btn => {
        if (btn.dataset.sizeId == sizeId) {
            btn.style.borderColor = '#2d5fff';
            btn.style.background = '#e8efff';
        } else {
            btn.style.borderColor = '#ddd';
            btn.style.background = '#fff';
        }
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

    // Update button styles
    document.querySelectorAll('.cart-color-btn').forEach(btn => {
        if (btn.dataset.colorId == colorId) {
            btn.style.borderColor = '#2d5fff';
            btn.style.boxShadow = '0 0 0 2px #2d5fff';
        } else {
            btn.style.borderColor = '#ddd';
            btn.style.boxShadow = 'none';
        }
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

        if (!isAvailable) {
            btn.style.opacity = '0.3';
            btn.style.cursor = 'not-allowed';
            btn.disabled = true;
        } else {
            btn.style.opacity = '1';
            btn.style.cursor = 'pointer';
            btn.disabled = false;
        }
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

        if (!isAvailable) {
            btn.style.opacity = '0.3';
            btn.style.cursor = 'not-allowed';
            btn.disabled = true;
        } else {
            btn.style.opacity = '1';
            btn.style.cursor = 'pointer';
            btn.disabled = false;
        }
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

// Show success notification for cart add
function showCartSuccessNotification() {
    // Create notification element
    let notification = document.getElementById('cartSuccessNotification');
    if (!notification) {
        notification = document.createElement('div');
        notification.id = 'cartSuccessNotification';
        notification.style.cssText = 'position:fixed; top:20px; right:20px; background:#28a745; color:#fff; padding:16px 24px; border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.15); z-index:50000; display:flex; align-items:center; gap:12px; animation:slideIn 0.3s ease;';
        notification.innerHTML = `
            <span style="font-size:1.5rem;">✓</span>
            <span>
                <strong>Added to cart!</strong><br>
                <small>You can add more or go to checkout</small>
            </span>
        `;
        document.body.appendChild(notification);
    }

    notification.style.display = 'flex';

    // Auto-hide after 3 seconds
    setTimeout(() => {
        notification.style.display = 'none';
    }, 3000);
}

// --------------- Save Design Modal ---------------

document.addEventListener('DOMContentLoaded', function() {
    // Cart quantity / confirm handlers
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
                errorEl.textContent = 'Please select a size.';
                errorEl.style.display = 'block';
                return;
            }
            if (!cartModalState.selectedColor) {
                errorEl.textContent = 'Please select a color.';
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
            btn.textContent = 'Adding...';
            btn.disabled = true;

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
                    throw new Error(data.error || 'Failed to add to cart');
                }
            })
            .catch(err => {
                btn.textContent = originalText;
                btn.disabled = false;
                console.error('Cart error:', err);
                errorEl.textContent = err.message || 'Failed to add to cart. Please try again.';
                errorEl.style.display = 'block';
            });
        });
    }
});

document.addEventListener('DOMContentLoaded', function() {
    function updateSaveDesignBtnState() {
        const name = document.getElementById('saveDesignName').value.trim();
        const email = document.getElementById('saveDesignEmail').value.trim();
        const privacy = document.getElementById('saveDesignPrivacy').checked;
        const btn = document.getElementById('saveDesignModalBtn');
        const validEmail = /^\S+@\S+\.\S+$/.test(email);
        if (name && email && validEmail && privacy) {
            btn.disabled = false;
            btn.style.background = '#2d5fff';
            btn.style.color = '#fff';
            btn.style.cursor = 'pointer';
        } else {
            btn.disabled = true;
            btn.style.background = '#eee';
            btn.style.color = '#aaa';
            btn.style.cursor = 'not-allowed';
        }
    }
    ['saveDesignName','saveDesignEmail','saveDesignPrivacy'].forEach(id => {
        document.getElementById(id).addEventListener('input', updateSaveDesignBtnState);
        document.getElementById(id).addEventListener('change', updateSaveDesignBtnState);
    });
    updateSaveDesignBtnState();

    // ============ PREVIEW IMAGE GENERATION (Canvas-based) ============
    // Helper: load an image as a promise (global)
    window.loadImageAsync = function loadImageAsync(src) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = () => resolve(img);
            img.onerror = () => reject(new Error('Failed to load image: ' + src));
            img.src = src;
        });
    }

    // Helper: get the CSS filter string for a given hex color (mirrors applyColorTint logic)
    window.getColorFilterString = function getColorFilterString(hex) {
        if (!hex) return 'none';
        hex = hex.trim();
        if (!hex.startsWith('#')) hex = '#' + hex;

        const hexLower = hex.toLowerCase();
        const hexClean = hex.replace('#', '');
        const r = parseInt(hexClean.substring(0,2), 16) / 255;
        const g = parseInt(hexClean.substring(2,4), 16) / 255;
        const b = parseInt(hexClean.substring(4,6), 16) / 255;
        const max = Math.max(r, g, b), min = Math.min(r, g, b);
        let h, s, l = (max + min) / 2;
        if (max === min) { h = s = 0; }
        else {
            const d = max - min;
            s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
            switch(max) {
                case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
                case g: h = ((b - r) / d + 2) / 6; break;
                case b: h = ((r - g) / d + 4) / 6; break;
            }
        }
        h *= 360; s *= 100; l *= 100;

        const isWhite = hexLower === '#ffffff' || hexLower === '#fff' || l > 95;
        const isBlack = hexLower === '#000000' || hexLower === '#000' || l < 10;
        const isGray = s < 10;

        if (isWhite) return 'saturate(0) brightness(2) contrast(0.8)';
        if (isBlack) return 'saturate(0) brightness(0.4) contrast(1.2)';
        if (isGray) {
            const brightness = 0.2 + (l / 100) * 1.5;
            return `saturate(0) brightness(${brightness})`;
        }

        const hueRotate = h - 50;
        const isReddish = h <= 20 || h >= 340;
        let saturate = (s / 100) * 2 + 0.5;
        if (isReddish) saturate = (s / 100) * 3 + 1;

        let brightness;
        if (l < 30) brightness = 0.3 + (l / 100) * 0.7;
        else if (l < 50) brightness = 0.5 + (l / 100) * 0.6;
        else brightness = 0.6 + (l / 100) * 0.5;

        return `sepia(1) saturate(${saturate}) hue-rotate(${hueRotate}deg) brightness(${brightness})`;
    }

    // Generates preview images for each view using Canvas API
    // options: { colorHex: string, cartItemId: number|null }
    // Renders the front-view preview as a data URL (no server call).
    // Used to capture the preview before a login redirect so it can be replayed.
    window.captureCurrentFrontPreview = async function captureCurrentFrontPreview() {
        if (!window.currentProduct || !elements['front'] || elements['front'].length === 0) return null;
        const colorHexToUse = currentColorHex;
        const canvasSize = 800;
        const editorDesignArea = document.getElementById('designArea');
        const editorDAWidth = editorDesignArea ? editorDesignArea.offsetWidth : 225;
        const editorDAHeight = editorDesignArea ? editorDesignArea.offsetHeight : 300;
        const imagePath = window.currentProduct.imagePath;
        if (!imagePath) return null;
        try {
            const productImg = await loadImageAsync('/' + imagePath);
            const canvas = document.createElement('canvas');
            canvas.width = canvasSize; canvas.height = canvasSize;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#f5f5f5';
            ctx.fillRect(0, 0, canvasSize, canvasSize);
            const maxDim = canvasSize * 0.98;
            const imgScale = Math.min(maxDim / productImg.width, maxDim / productImg.height);
            const imgW = productImg.width * imgScale, imgH = productImg.height * imgScale;
            const imgX = (canvasSize - imgW) / 2, imgY = (canvasSize - imgH) / 2;
            ctx.filter = getColorFilterString(colorHexToUse);
            ctx.drawImage(productImg, imgX, imgY, imgW, imgH);
            ctx.filter = 'none';
            const daWidth = canvasSize * 0.45, daHeight = canvasSize * 0.60;
            const daTop = canvasSize * 0.25, daLeft = canvasSize * 0.50 - daWidth / 2;
            const scaleX = daWidth / editorDAWidth, scaleY = daHeight / editorDAHeight;
            ctx.save();
            ctx.beginPath();
            ctx.rect(daLeft, daTop, daWidth, daHeight);
            ctx.clip();
            const sorted = [...elements['front']].sort((a, b) => (a.layer_order || 0) - (b.layer_order || 0));
            for (const el of sorted) {
                const elX = daLeft + (el.x || 0) * scaleX;
                const elY = daTop + (el.y || 0) * scaleY;
                if (el.type === 'image' && el.src) {
                    try {
                        const elImg = await loadImageAsync(el.src.startsWith('data:') || el.src.startsWith('/') || el.src.startsWith('http') ? el.src : '/' + el.src);
                        const elW = (el.width || 80) * scaleX, elH = (el.height || 80) * scaleY;
                        ctx.globalAlpha = el.bgRemoved ? 0.5 : 1;
                        ctx.drawImage(elImg, elX, elY, elW, elH);
                        ctx.globalAlpha = 1;
                    } catch(e) {}
                } else if (el.type === 'text' && el.text) {
                    const fontSize = Math.max(8, (el.fontSize || 24) * scaleX);
                    ctx.font = `${el.italic ? 'italic ' : ''}${el.bold ? 'bold ' : ''}${fontSize}px ${el.fontFamily || 'Arial'}`;
                    ctx.fillStyle = el.color || '#000000';
                    ctx.fillText(el.text, elX, elY + fontSize);
                }
            }
            ctx.restore();
            return canvas.toDataURL('image/png');
        } catch(e) { return null; }
    };

    window.generateAndSavePreviews = async function generateAndSavePreviews(designId, options = {}) {
        if (!designId || !window.currentProduct) {
            console.warn('Cannot generate previews: missing designId or product');
            return;
        }

        const colorHexToUse = options.colorHex || currentColorHex;
        const cartItemId = options.cartItemId || null;

        const views = ['front', 'back', 'left-sleeve', 'right-sleeve'];
        const previews = {};
        const canvasSize = 800;

        // Get actual design area dimensions from the DOM for coordinate scaling
        const editorDesignArea = document.getElementById('designArea');
        const editorDAWidth = editorDesignArea ? editorDesignArea.offsetWidth : 225;
        const editorDAHeight = editorDesignArea ? editorDesignArea.offsetHeight : 300;

        for (const view of views) {
            if (!elements[view] || elements[view].length === 0) continue;

            // Determine product image path for this view
            let imagePath = '';
            if (view === 'front') imagePath = window.currentProduct.imagePath;
            else if (view === 'back') imagePath = window.currentProduct.backImagePath;
            else if (view === 'left-sleeve') imagePath = window.currentProduct.leftSleeveImagePath;
            else if (view === 'right-sleeve') imagePath = window.currentProduct.rightSleeveImagePath;

            if (!imagePath) continue;

            try {
                // Load product image
                const productImg = await loadImageAsync('/' + imagePath);

                // Create canvas
                const canvas = document.createElement('canvas');
                canvas.width = canvasSize;
                canvas.height = canvasSize;
                const ctx = canvas.getContext('2d');

                // Fill background
                ctx.fillStyle = '#f5f5f5';
                ctx.fillRect(0, 0, canvasSize, canvasSize);

                // Draw product image centered (simulating object-fit: contain with 98% max)
                const maxDim = canvasSize * 0.98;
                const imgScale = Math.min(maxDim / productImg.width, maxDim / productImg.height);
                const imgW = productImg.width * imgScale;
                const imgH = productImg.height * imgScale;
                const imgX = (canvasSize - imgW) / 2;
                const imgY = (canvasSize - imgH) / 2;

                // Apply color tint using canvas filter (same as CSS filter)
                const filterStr = getColorFilterString(colorHexToUse);
                ctx.filter = filterStr;
                ctx.drawImage(productImg, imgX, imgY, imgW, imgH);
                ctx.filter = 'none';

                // Calculate design area position on canvas
                let daLeft, daTop, daWidth, daHeight;
                const isSleeve = (view === 'left-sleeve' || view === 'right-sleeve');
                if (isSleeve) {
                    daWidth = canvasSize * 0.13;
                    daHeight = canvasSize * 0.16;
                    daTop = canvasSize * 0.27;
                    daLeft = canvasSize * 0.52 - daWidth / 2;
                } else {
                    daWidth = canvasSize * 0.45;
                    daHeight = canvasSize * 0.60;
                    daTop = canvasSize * 0.25;
                    daLeft = canvasSize * 0.50 - daWidth / 2;
                }

                // Scale factor from editor design area to canvas design area
                const scaleX = daWidth / editorDAWidth;
                const scaleY = daHeight / editorDAHeight;

                // Clip to design area (elements shouldn't overflow)
                ctx.save();
                ctx.beginPath();
                ctx.rect(daLeft, daTop, daWidth, daHeight);
                ctx.clip();

                // Sort elements by layer order
                const sortedElements = [...elements[view]].sort((a, b) => {
                    return (a.layer_order || 0) - (b.layer_order || 0);
                });

                // Draw each element
                for (const el of sortedElements) {
                    const elX = daLeft + (el.x || 0) * scaleX;
                    const elY = daTop + (el.y || 0) * scaleY;

                    if (el.type === 'image' && el.src) {
                        try {
                            const elImg = await loadImageAsync(
                                el.src.startsWith('data:') || el.src.startsWith('http') || el.src.startsWith('/')
                                    ? el.src
                                    : '/' + el.src
                            );

                            const elW = (el.width || 80) * scaleX;
                            const elH = (el.height || 80) * scaleY;

                            ctx.save();
                            // Apply rotation around element center
                            if (el.rotation) {
                                ctx.translate(elX + elW / 2, elY + elH / 2);
                                ctx.rotate((el.rotation * Math.PI) / 180);
                                ctx.translate(-(elX + elW / 2), -(elY + elH / 2));
                            }
                            // Apply flip
                            if (el.flipped) {
                                ctx.translate(elX + elW, 0);
                                ctx.scale(-1, 1);
                                ctx.translate(-elX, 0);
                            }

                            // Set opacity for bg-removed images
                            ctx.globalAlpha = el.bgRemoved ? 0.5 : 1;
                            ctx.drawImage(elImg, elX, elY, elW, elH);

                            // Color overlay
                            if (el.color && el.color !== '#ffffff' && el.color !== '#fff') {
                                ctx.globalCompositeOperation = 'multiply';
                                ctx.globalAlpha = 0.35;
                                ctx.fillStyle = el.color;
                                ctx.fillRect(elX, elY, elW, elH);
                                ctx.globalCompositeOperation = 'source-over';
                            }
                            ctx.globalAlpha = 1;
                            ctx.restore();
                        } catch (imgErr) {
                            console.warn('Could not load element image:', el.src, imgErr);
                        }
                    } else if (el.type === 'text' && el.text) {
                        ctx.save();

                        const fontSize = Math.max(8, (el.fontSize || 24) * scaleX);
                        const fontWeight = el.bold ? 'bold' : 'normal';
                        const fontStyle = el.italic ? 'italic' : 'normal';
                        const fontFamily = el.fontFamily || 'Arial, sans-serif';

                        ctx.font = `${fontStyle} ${fontWeight} ${fontSize}px ${fontFamily}`;
                        ctx.fillStyle = el.color || '#000000';
                        ctx.textBaseline = 'top';

                        // Apply rotation
                        if (el.rotation) {
                            const metrics = ctx.measureText(el.text);
                            const textW = metrics.width;
                            const textH = fontSize;
                            ctx.translate(elX + textW / 2, elY + textH / 2);
                            ctx.rotate((el.rotation * Math.PI) / 180);
                            ctx.translate(-(elX + textW / 2), -(elY + textH / 2));
                        }

                        ctx.fillText(el.text, elX, elY);

                        // Underline
                        if (el.underline) {
                            const metrics = ctx.measureText(el.text);
                            ctx.beginPath();
                            ctx.strokeStyle = el.color || '#000000';
                            ctx.lineWidth = Math.max(1, fontSize / 15);
                            ctx.moveTo(elX, elY + fontSize + 2);
                            ctx.lineTo(elX + metrics.width, elY + fontSize + 2);
                            ctx.stroke();
                        }

                        ctx.restore();
                    }
                }

                ctx.restore(); // End design area clipping

                previews[view] = canvas.toDataURL('image/png');
            } catch (err) {
                console.error('Preview generation failed for view:', view, err);
            }
        }

        // Send previews to server
        if (Object.keys(previews).length > 0) {
            try {
                let endpoint, payload;
                if (cartItemId) {
                    endpoint = '/cart/save-previews';
                    payload = { cart_item_id: cartItemId, design_id: designId, previews: previews };
                } else {
                    endpoint = '/custom-design/save-previews';
                    payload = { design_id: designId, previews: previews };
                }
                const resp = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await resp.json();
                console.log('Previews saved:', result);
            } catch (err) {
                console.error('Failed to save previews:', err);
            }
        }
    }

    // Update Design button handler (for editing existing designs)
    document.getElementById('updateDesignBtn').addEventListener('click', function() {
        if (!window.loadedDesignId) {
            alert('No design loaded to update.');
            return;
        }

        let allElements = [];
        Object.keys(elements).forEach(view => {
            allElements = allElements.concat(elements[view].map(el => ({...el, view})));
        });

        if (!window.currentProduct) {
            alert('Please select a product before saving your design.');
            return;
        }

        const designData = {
            design_id: window.loadedDesignId,
            email: window.loadedDesignEmail || '',
            product_id: window.currentProduct.id,
            elements: allElements,
            color_hex: currentColorHex
        };

        const btn = document.getElementById('updateDesignBtn');
        btn.disabled = true;
        btn.textContent = 'Updating...';

        (async () => {
            try {
                const resp = await fetch('/custom-design/update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(designData)
                });
                const parsed = await resp.json();

                if (parsed && parsed.requireLogin) {
                    window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
                    return;
                }
                if (parsed && parsed.id) {
                    window.savedDesignId = parsed.id;
                    // Await preview so it's saved before user navigates away
                    btn.textContent = 'Saving preview...';
                    await generateAndSavePreviews(parsed.id);
                    closeSaveDesignModal();
                    document.getElementById('designSavedModal').style.display = 'flex';
                } else {
                    alert('Failed to update design. Please try again.');
                }
            } catch(e) {
                alert('Failed to update design. Please try again.');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Update Design';
            }
        })();
    });

    // Delete Design button handler
    document.getElementById('deleteDesignBtn').addEventListener('click', function() {
        if (!window.loadedDesignId) {
            alert('No design loaded to delete.');
            return;
        }

        const designName = window.loadedDesignName || 'this design';
        if (!confirm(`Are you sure you want to permanently delete "${designName}"? This cannot be undone.`)) {
            return;
        }

        this.disabled = true;
        this.textContent = 'Deleting...';

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/custom-design/delete', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                document.getElementById('deleteDesignBtn').disabled = false;
                document.getElementById('deleteDesignBtn').textContent = 'Delete Design';

                let isJson = false;
                try { JSON.parse(xhr.responseText); isJson = true; } catch(e){}
                if (xhr.status === 200 && isJson) {
                    alert('Design deleted successfully.');
                    // Redirect to home or designs page
                    window.location.href = '/account';
                } else if (xhr.status === 200 && !isJson) {
                    alert('Session expired or not authorized. Please log in and try again.');
                } else {
                    alert('Failed to delete design: ' + xhr.responseText);
                }
            }
        };
        xhr.send(JSON.stringify({ design_id: window.loadedDesignId }));
    });

    // Save as New Design button handler
    document.getElementById('saveDesignModalBtn').addEventListener('click', async function() {
        if (this.disabled) return;
        let allElements = [];
        Object.keys(elements).forEach(view => {
            allElements = allElements.concat(elements[view].map(el => ({...el, view})));
        });
        const name = document.getElementById('saveDesignName').value.trim();
        const email = document.getElementById('saveDesignEmail').value.trim();
        const privacyChecked = document.getElementById('saveDesignPrivacy').checked;
        if (!name || !email || !privacyChecked) return;
        if (!window.currentProduct) {
            alert('Please select a product before saving your design.');
            return;
        }
        // Don't require size/color at save time - user selects when adding to cart
        const designData = {
            name: name,
            email: email,
            product_id: window.currentProduct.id,
            size_id: null, // User selects at cart time
            color_id: null, // User selects at cart time
            elements: allElements,
            color_hex: currentColorHex
        };
        const btn = this;
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Saving...';
        try {
            const r = await fetch('/custom-design/save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(designData)
            });
            const parsed = await r.json();
            if (parsed.requireLogin) {
                const preview = await captureCurrentFrontPreview();
                sessionStorage.setItem('pendingDesignSave', JSON.stringify({ designData, frontPreview: preview }));
                window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
                return;
            }
            if (parsed && parsed.id) {
                window.savedDesignId = parsed.id;
                window.loadedDesignId = null;
                window.loadedDesignName = null;
                // Await preview so it's saved before user navigates away
                btn.textContent = 'Saving preview...';
                await generateAndSavePreviews(parsed.id);
                closeSaveDesignModal();
                document.getElementById('designSavedModal').style.display = 'flex';
            } else {
                alert('Failed to save design. Please try again.');
            }
        } catch(e) {
            alert('Failed to save design. Please try again.');
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    });

    // Add to Cart button handler - opens Add to Cart modal with size/color selection
    document.getElementById('addToCartNowBtn').addEventListener('click', function() {
        if (!window.savedDesignId) {
            alert('Design not saved yet.');
            return;
        }

        // Calculate design fee
        const frontCount = (typeof elements !== 'undefined' && elements['front']) ? elements['front'].length : 0;
        const backCount = (typeof elements !== 'undefined' && elements['back']) ? elements['back'].length : 0;
        const leftSleeveCount = (typeof elements !== 'undefined' && elements['left-sleeve']) ? elements['left-sleeve'].length : 0;
        const rightSleeveCount = (typeof elements !== 'undefined' && elements['right-sleeve']) ? elements['right-sleeve'].length : 0;
        let designFee = 0;
        if (frontCount > 0 && backCount > 0) {
            designFee += 3.0;
        }
        if (leftSleeveCount > 0) {
            designFee += 1.5;
        }
        if (rightSleeveCount > 0) {
            designFee += 1.5;
        }

        const productName = window.currentProduct ? window.currentProduct.name : 'Custom Product';
        const basePrice = window.currentProduct ? window.currentProduct.basePrice : 0;
        const designName = document.getElementById('saveDesignName') ? document.getElementById('saveDesignName').value : 'Your Design';

        openAddToCartModal(
            window.savedDesignId,
            window.currentProduct ? window.currentProduct.id : null,
            productName,
            designName,
            basePrice,
            designFee
        );
    });

    // Go to Checkout button handler
    document.getElementById('goToCheckoutBtn').addEventListener('click', function() {
        window.location.href = '/cart';
    });
});

// --------------- Image Editor ---------------

function updateImageSize(type) {
    if (!selectedElement) return;
    const el = elements[currentView].find(e => e.id === selectedElement);
    if (!el || el.type !== 'image') return;
    const width = parseFloat(document.getElementById('imgEditWidth').value);
    const height = parseFloat(document.getElementById('imgEditHeight').value);
    if (type === 'width' && width > 0) el.width = width;
    if (type === 'height' && height > 0) el.height = height;
    renderElements();
    showImageEditor(el);
}

function updateImageColor() {
    if (!selectedElement) return;
    const el = elements[currentView].find(e => e.id === selectedElement);
    if (!el || el.type !== 'image') return;
    const color = document.getElementById('imgEditColor').value;
    el.color = color;
    // Optionally apply color filter to the image (if supported)
    renderElements();
    showImageEditor(el);
}

// Update image rotation for the selected image
function updateImageRotation() {
    if (!selectedElement) return;
    const el = elements[currentView].find(e => e.id === selectedElement);
    if (!el || el.type !== 'image') return;
    let rotation = parseFloat(document.getElementById('imgEditRotation').value);
    if (isNaN(rotation)) rotation = 0;
    el.rotation = rotation;
    document.getElementById('imgEditRotationVal').value = rotation;
    renderElements();
    showImageEditor(el);
}

function closeDesignSavedModal() {
    document.getElementById('designSavedModal').style.display = 'none';
}

// --- Image Editor Action Buttons Implementation ---
let imageEditApplyLive = true;
function toggleImageApply() {
    imageEditApplyLive = document.getElementById('imgEditApplyChanges').checked;
}

function applyImageEditIfLive(cb) {
    if (imageEditApplyLive) {
        cb();
    }
}

function centerImage() {
    if (!selectedElement) return;
    const el = elements[currentView].find(e => e.id === selectedElement);
    if (!el || el.type !== 'image') return;
    const designArea = document.getElementById('designArea');
    const areaRect = designArea.getBoundingClientRect();
    el.x = (areaRect.width - el.width) / 2;
    el.y = (areaRect.height - el.height) / 2;
    renderElements();
}

function layerImage(dir) {
    if (!selectedElement) return;
    const arr = elements[currentView];
    const idx = arr.findIndex(e => e.id === selectedElement);
    if (idx === -1) return;
    if (dir === 'up' && idx < arr.length - 1) {
        [arr[idx], arr[idx+1]] = [arr[idx+1], arr[idx]];
    } else if (dir === 'down' && idx > 0) {
        [arr[idx], arr[idx-1]] = [arr[idx-1], arr[idx]];
    }
    renderElements();
}

function flipImage() {
    if (!selectedElement) return;
    const el = elements[currentView].find(e => e.id === selectedElement);
    if (!el || el.type !== 'image') return;
    el.flipped = !el.flipped;
    renderElements();
}

function duplicateImage() {
    if (!selectedElement) return;
    const el = elements[currentView].find(e => e.id === selectedElement);
    if (!el || el.type !== 'image') return;
    // Deep copy, including original data
    const newEl = {...el, id: 'element-' + (++elementIdCounter), x: el.x + 20, y: el.y + 20};
    elements[currentView].push(newEl);
    renderElements();
    selectElementById(newEl.id);
}

// --- Crop functionality ---
function cropImage() {
    // Visual crop: show draggable/resizable rectangle overlay on selected image
    if (!selectedElement) return;
    const el = elements[currentView].find(e => e.id === selectedElement);
    if (!el || el.type !== 'image') return;
    // Remove any existing crop overlay
    let oldOverlay = document.getElementById('cropOverlay');
    if (oldOverlay) oldOverlay.remove();
    // Find the image DOM element (the .design-element for this image)
    const elementDiv = document.getElementById(el.id);
    if (!elementDiv) return alert('Image not found');
    const imgDiv = elementDiv.querySelector('img');
    if (!imgDiv) return alert('Image not found');
    // Always append overlay to the design-area, not the image parent
    const designArea = document.getElementById('designArea');
    // Get image position relative to design-area
    const imgRect = imgDiv.getBoundingClientRect();
    const areaRect = designArea.getBoundingClientRect();
    const iw = imgRect.width, ih = imgRect.height;
    const ix = imgRect.left - areaRect.left, iy = imgRect.top - areaRect.top;
    // Create overlay
    const overlay = document.createElement('div');
    overlay.id = 'cropOverlay';
    overlay.style.position = 'absolute';
    overlay.style.border = '2px dashed #2d5fff';
    overlay.style.background = 'rgba(45,95,255,0.08)';
    overlay.style.zIndex = 9999;
    overlay.style.left = (ix + iw*0.1) + 'px';
    overlay.style.top = (iy + ih*0.1) + 'px';
    overlay.style.width = (iw*0.8) + 'px';
    overlay.style.height = (ih*0.8) + 'px';
    overlay.style.pointerEvents = 'auto';
    overlay.style.boxSizing = 'border-box';
    // Append overlay after all design elements so it's always on top
    designArea.appendChild(overlay);
    // Prevent page scroll
    overlay.addEventListener('mousedown', function(e) { e.preventDefault(); });
    // Add handles via interact.js
    interact(overlay).draggable({
        modifiers: [
            interact.modifiers.restrictRect({ restriction: designArea, endOnly: true })
        ],
        listeners: {
            move (event) {
                const target = event.target;
                let x = (parseFloat(target.style.left) || 0) + event.dx;
                let y = (parseFloat(target.style.top) || 0) + event.dy;
                // Constrain
                const maxX = designArea.offsetWidth - target.offsetWidth;
                const maxY = designArea.offsetHeight - target.offsetHeight;
                x = Math.max(0, Math.min(x, maxX));
                y = Math.max(0, Math.min(y, maxY));
                target.style.left = x + 'px';
                target.style.top = y + 'px';
            }
        }
    }).resizable({
        edges: { left: true, right: true, bottom: true, top: true },
        listeners: {
            move (event) {
                let { x, y } = event.target.getBoundingClientRect();
                let areaRect = designArea.getBoundingClientRect();
                let left = x - areaRect.left + event.deltaRect.left;
                let top = y - areaRect.top + event.deltaRect.top;
                let width = event.rect.width;
                let height = event.rect.height;
                // Constrain
                left = Math.max(0, Math.min(left, designArea.offsetWidth - width));
                top = Math.max(0, Math.min(top, designArea.offsetHeight - height));
                event.target.style.left = left + 'px';
                event.target.style.top = top + 'px';
                event.target.style.width = width + 'px';
                event.target.style.height = height + 'px';
            }
        },
        modifiers: [
            interact.modifiers.restrictEdges({ outer: designArea }),
            interact.modifiers.restrictSize({ min: { width: 30, height: 30 }, max: { width: designArea.offsetWidth, height: designArea.offsetHeight } })
        ]
    });
    // Add Apply Crop button
    let applyBtn = document.createElement('button');
    applyBtn.textContent = 'Apply Crop';
    applyBtn.className = 'img-edit-btn';
    applyBtn.style.position = 'absolute';
    applyBtn.style.right = '-90px';
    applyBtn.style.top = '0px';
    applyBtn.onclick = function() { applyCropToImage(el.id); };
    overlay.appendChild(applyBtn);
    // Focus overlay
    overlay.focus();
    // Optionally: scroll into view
    overlay.scrollIntoView({behavior:'smooth',block:'center'});
    // Hide overlay if image is deselected
    document.addEventListener('click', function hideOverlay(e) {
        if (!overlay.contains(e.target) && !imgDiv.contains(e.target)) {
            overlay.remove();
            document.removeEventListener('click', hideOverlay);
        }
    });
}

// Actually crop the image to the overlay rectangle
function applyCropToImage(elementId) {
    const el = elements[currentView].find(e => e.id === elementId);
    if (!el) return;
    const imgDiv = document.querySelector(`.design-element[data-id='${el.id}'] img`);
    const overlay = document.getElementById('cropOverlay');
    if (!imgDiv || !overlay) return;
    // Get crop rectangle relative to image
    const imgRect = imgDiv.getBoundingClientRect();
    const parentRect = imgDiv.parentNode.getBoundingClientRect();
    const overlayRect = overlay.getBoundingClientRect();
    // Calculate crop area in image coordinates
    const scaleX = imgDiv.naturalWidth / imgRect.width;
    const scaleY = imgDiv.naturalHeight / imgRect.height;
    const cropX = (overlayRect.left - imgRect.left) * scaleX;
    const cropY = (overlayRect.top - imgRect.top) * scaleY;
    const cropW = overlayRect.width * scaleX;
    const cropH = overlayRect.height * scaleY;
    // Draw to canvas
    const img = new window.Image();
    img.onload = function() {
        const canvas = document.createElement('canvas');
        canvas.width = cropW;
        canvas.height = cropH;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, cropX, cropY, cropW, cropH, 0, 0, cropW, cropH);
        el.src = canvas.toDataURL();
        renderElements();
        overlay.remove();
    };
    img.src = el.src;
}

// --- Reset to original functionality ---
function resetImageEdit() {
    if (!selectedElement) return;
    const el = elements[currentView].find(e => e.id === selectedElement);
    if (!el || el.type !== 'image') return;
    if (!el._original) return;
    // Restore all original properties
    Object.assign(el, JSON.parse(JSON.stringify(el._original)));
    renderElements();
    showImageEditor(el);
}

function removeImageBg() {
    if (!selectedElement) return;
    const el = elements[currentView].find(e => e.id === selectedElement);
    if (!el || el.type !== 'image') return;
    el.bgRemoved = !el.bgRemoved;
    renderElements();
}

// Patch image editor controls to respect Apply Changes
// (Functions must be defined before this patching logic)
const origUpdateImageSize = updateImageSize;
updateImageSize = function(type) {
    applyImageEditIfLive(() => origUpdateImageSize(type));
}
const origUpdateImageColor = updateImageColor;
updateImageColor = function() {
    applyImageEditIfLive(origUpdateImageColor);
}
const origUpdateImageRotation = updateImageRotation;
updateImageRotation = function() {
    applyImageEditIfLive(origUpdateImageRotation);
}

// --------------- Upload / Color Modal ---------------

function openSaveDesignModal() {
    // First close any open editing modals
    closeUploadEditor();
    hideTextEditor();
    hideImageEditor();

    const isEditing = !!window.loadedDesignId;
    const editingModeDiv = document.getElementById('saveEditingMode');
    const saveModalTitle = document.getElementById('saveModalTitle');
    const saveBtn = document.getElementById('saveDesignModalBtn');

    if (isEditing && window.loadedDesignName) {
        // Show editing mode
        editingModeDiv.style.display = 'block';
        document.getElementById('editingDesignName').textContent = window.loadedDesignName;
        saveModalTitle.textContent = 'Update or Save New';
        saveBtn.textContent = 'Save as New Design';
    } else {
        // Hide editing mode - show only new save
        editingModeDiv.style.display = 'none';
        saveModalTitle.textContent = 'Save your design';
        saveBtn.textContent = 'Save';
    }

    document.getElementById('saveDesignModal').style.display = 'flex';
}

function closeSaveDesignModal() {
    document.getElementById('saveDesignModal').style.display = 'none';
}

// --- Upload Editor Modal Logic ---
function openUploadEditor() {
    hideTextEditor();
    document.getElementById('uploadEditorModal').style.display = 'flex';
}
function closeUploadEditor() {
    document.getElementById('uploadEditorModal').style.display = 'none';
}
function handleUploadFile(files) {
    if (!files || !files.length) return;
    const file = files[0];
    if (!file.type.startsWith('image/')) {
        alert('Only image files are allowed.');
        return;
    }
    if (file.size > 20 * 1024 * 1024) {
        alert('File too large. Max 20MB.');
        return;
    }
    const reader = new FileReader();
    reader.onload = function(event) {
        addImageElement(event.target.result);
        saveRecentUpload(event.target.result);
        closeUploadEditor();
    };
    reader.readAsDataURL(file);
}
function handleUploadDrop(e) {
    e.preventDefault();
    e.currentTarget.classList.remove('dragover');
    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
        handleUploadFile(e.dataTransfer.files);
    }
}
// Note: saveRecentUpload / renderRecentUploads are overridden by the inline
// script in shop_custom.php which uses a user-scoped localStorage key.
// These stubs exist only as a fallback.
function saveRecentUpload(dataUrl) {}
function renderRecentUploads() {}
document.addEventListener('DOMContentLoaded', function() {
    renderRecentUploads();
});

// --- Whats Next Panel Upload/Add Text Logic ---
function triggerWhatsNextUpload() {
    hideTextEditor();
    document.getElementById('whatsNextImageUpload').click();
    showImageEditor();
}
function triggerWhatsNextAddText() {
    closeUploadEditor();
    showTextEditor();
}

// --- Change Color Modal Logic ---
function openChangeColorModal() {
    var modal = document.getElementById('changeColorModal');
    var optionsDiv = document.getElementById('changeColorOptions');
    if (!window.currentProduct) {
        alert('Please select a product first.');
        return;
    }

    // If data not loaded yet, fetch it now
    if (!window.studioVariantsData || window.studioVariantsData.colors.length === 0) {
        optionsDiv.innerHTML = '<p style="text-align:center;padding:1rem;">Loading colors...</p>';
        modal.style.display = 'flex';

        fetch('/api/product-variants/' + window.currentProduct.id)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                window.studioVariantsData = {
                    variants: data.variants || [],
                    sizes: data.sizes || [],
                    colors: data.colors || []
                };
                // Re-render the modal with loaded data
                renderChangeColorModalContent();
                // Also render the studio panel
                renderStudioColorSwatches();
            })
            .catch(function(err) {
                console.error('Failed to fetch colors:', err);
                optionsDiv.innerHTML = '<p style="color:red;text-align:center;">Failed to load colors. Please try again.</p>';
            });
        return;
    }

    renderChangeColorModalContent();
    modal.style.display = 'flex';
}

function renderChangeColorModalContent() {
    var optionsDiv = document.getElementById('changeColorOptions');
    var colors = window.studioVariantsData.colors || [];
    var sizes = window.studioVariantsData.sizes || [];
    var variants = window.studioVariantsData.variants || [];

    var html = '';
    html += '<div style="margin-bottom:1.2rem;"><b>Colors:</b><div class="modal-color-swatches">';
    colors.forEach(function(c) {
        var selected = (window.studioSelectedColorId && String(c.id) === String(window.studioSelectedColorId)) ? 'modal-color-selected' : '';
        var borderStyle = (c.hex && (c.hex.toLowerCase() === '#ffffff' || c.hex.toLowerCase() === '#fff')) ? 'border:2px solid #ccc;' : '';
        html += `<div class="modal-color-swatch ${selected}" title="${c.name || 'Color'}" style="background:${c.hex || '#ccc'};${borderStyle}" data-color-id="${c.id}" data-hex="${c.hex || '#ccc'}" onclick="selectModalColor('${c.id}', '${c.hex || '#ccc'}')">${selected ? '<span class="modal-color-check">&#10003;</span>' : ''}</div>`;
    });
    html += '</div></div>';

    // Show sizes for currently selected color
    html += '<div style="margin-bottom:1.2rem;"><b>Available Sizes:</b><div class="modal-size-list" id="modalSizeList">';
    if (window.studioSelectedColorId) {
        var availableSizes = [];
        variants.forEach(function(v) {
            if (String(v.color_id) === String(window.studioSelectedColorId) && v.is_available) {
                var sz = sizes.find(function(s) { return String(s.id) === String(v.size_id); });
                if (sz && !availableSizes.some(function(s) { return s.id === sz.id; })) {
                    availableSizes.push(sz);
                }
            }
        });
        if (availableSizes.length > 0) {
            availableSizes.forEach(function(sz) {
                html += `<span class="modal-size-item">${sz.name}</span>`;
            });
        } else {
            html += '<span style="color:#888;">No sizes available</span>';
        }
    } else {
        html += '<span style="color:#888;">Select a color to see sizes</span>';
    }
    html += '</div></div>';

    optionsDiv.innerHTML = html;
}

function closeChangeColorModal() {
    document.getElementById('changeColorModal').style.display = 'none';
}

function selectModalColor(colorId, colorHex) {
    // Apply color directly to mockup
    if (colorHex) {
        applyColorTint(colorHex);
    }

    // Update studio selected color
    window.studioSelectedColorId = colorId;

    // Update the studio panel swatches
    var studioContainer = document.getElementById('studioColorSwatches');
    if (studioContainer) {
        studioContainer.querySelectorAll('.studio-color-swatch').forEach(function(s) {
            s.classList.remove('selected');
            s.style.border = '3px solid #ddd';
            var hex = (s.dataset.hex || '').toLowerCase();
            if (hex === '#ffffff' || hex === '#fff') {
                s.style.border = '3px solid #ccc';
            }
            if (String(s.dataset.colorId) === String(colorId)) {
                s.classList.add('selected');
                s.style.border = '3px solid #4CAF50';
            }
        });
    }

    // Update sizes display
    renderStudioSizesForColor(colorId);

    // Re-render modal to show updated selection (keep modal open)
    renderChangeColorModalContent();
}

function selectModalSize(sizeId) {
    // Sizes are display-only in the modal
}

// Prevent browser from opening files on window drag/drop
window.addEventListener('dragover', function(e) { e.preventDefault(); }, false);
window.addEventListener('drop', function(e) { e.preventDefault(); }, false);

// --------------- Main Studio JS ---------------

// --- Strict Apply Changes Logic ---
let isEditing = false;

function setEditing(state) {
    isEditing = state;
    document.querySelectorAll('.view-btn').forEach(btn => btn.disabled = state);
}

function showApplyChangesPopup() {
    // Removed: apply changes modal logic
}

// Intercept actions if editing
function interceptIfEditing(e) {
    if (isEditing) {
        e.preventDefault();
        showApplyChangesPopup();
        return true;
    }
    return false;
}

// Hook into upload, add text, and view switch
// Removed: addImageBtn/addTextBtn intercept logic
document.querySelectorAll('.view-btn').forEach(btn => btn.addEventListener('click', function(e) { if (interceptIfEditing(e)) return; }));

// Set editing true on drag/resize/text edit
function startEditing() { setEditing(true); }
function finishEditing() { setEditing(false); }

// State - using window. to make globally accessible
window.currentProduct = null;
let currentView = 'front';
let currentColorHex = '#ffffff';
let editingTextElement = null;
let elements = {
    front: [],
    back: [],
    'left-sleeve': [],
    'right-sleeve': []
};
// Expose as shared global so PHP inline script's generateAndSavePreviews can access the correct elements
window.designElements = elements;
let selectedElement = null;
let elementIdCounter = 0;
const CUSTOM_DESIGN_FEE = 5.00;

// NOTE: productsData and loadDesignData are defined in the PHP data island in the view.

// --- Apply Changes Button Logic ---
let lastAppliedItem = null;

// Only require 'Apply Changes' for saving edits to an existing element
function afterElementAdded() {
    // No need to disable upload/text buttons after adding
}

    // Show the image editor panel and populate fields with selected image's properties
    function showImageEditor(el) {
        if (!el) return;
        document.getElementById('imageEditorPanel').style.display = 'flex';
        document.getElementById('imgEditWidth').value = el.width || 80;
        document.getElementById('imgEditHeight').value = el.height || 80;
        document.getElementById('imgEditRotation').value = el.rotation || 0;
        document.getElementById('imgEditRotationVal').value = el.rotation || 0;
        document.getElementById('imgEditColor').value = el.color || '#ffffff';
        // Optionally set checkboxes if you have them (color, remove bg, etc.)
    }

    // Hide the image editor panel
    function hideImageEditor() {
        document.getElementById('imageEditorPanel').style.display = 'none';
    }

// Convert hex color to HSL values
function hexToHSL(hex) {
    hex = hex.replace('#', '');
    const r = parseInt(hex.substring(0,2), 16) / 255;
    const g = parseInt(hex.substring(2,4), 16) / 255;
    const b = parseInt(hex.substring(4,6), 16) / 255;

    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    let h, s, l = (max + min) / 2;

    if (max === min) {
        h = s = 0;
    } else {
        const d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        switch(max) {
            case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
            case g: h = ((b - r) / d + 2) / 6; break;
            case b: h = ((r - g) / d + 4) / 6; break;
        }
    }

    return { h: h * 360, s: s * 100, l: l * 100 };
}

// Apply color tint to the mockup product image
function applyColorTint(hex) {
    if (!hex) return;

    // Ensure hex has # prefix
    hex = hex.trim();
    if (!hex.startsWith('#')) {
        hex = '#' + hex;
    }

    currentColorHex = hex;
    const mockupImg = document.getElementById('mockupProduct');
    const mockupContainer = document.getElementById('mockupContainer');
    if (!mockupImg) return;

    const hexLower = hex.toLowerCase();

    // Convert hex to HSL for the filter calculation
    const hsl = hexToHSL(hex);
    const isWhite = hexLower === '#ffffff' || hexLower === '#fff' || hsl.l > 95;
    const isVeryLight = hsl.l > 85;
    const isBlack = hexLower === '#000000' || hexLower === '#000' || hsl.l < 10;
    const isGray = hsl.s < 10; // Low saturation = gray

    // Set background: dark for white/light products so they're visible
    if (isWhite || isVeryLight) {
        mockupContainer.classList.add('dark-bg');
    } else {
        mockupContainer.classList.remove('dark-bg');
    }

    // Apply color filter to product image
    // Base image is ORANGE (~30deg hue) with transparent background
    if (isWhite) {
        // White product - desaturate completely and brighten significantly
        mockupImg.style.filter = 'saturate(0) brightness(2) contrast(0.8)';
    } else if (isBlack) {
        // Black product - desaturate and darken, but keep visibility
        mockupImg.style.filter = 'saturate(0) brightness(0.4) contrast(1.2)';
    } else if (isGray) {
        // Gray - desaturate and adjust brightness based on lightness
        const brightness = 0.2 + (hsl.l / 100) * 1.5;
        mockupImg.style.filter = `saturate(0) brightness(${brightness})`;
    } else {
        // Colorize using sepia base then hue-rotate to target
        // This works better than direct hue-rotate from orange
        const hueRotate = hsl.h - 50; // Sepia is ~50deg

        // Red hues (around 0-20 and 340-360) need extra saturation to not look pinkish
        const isReddish = hsl.h <= 20 || hsl.h >= 340;
        let saturate = (hsl.s / 100) * 2 + 0.5;
        if (isReddish) {
            saturate = (hsl.s / 100) * 3 + 1; // Boost saturation for reds
        }

        // Brightness: preserve dark colors better (like maroon)
        // For dark colors (low lightness), keep brightness low
        let brightness;
        if (hsl.l < 30) {
            // Dark colors - keep them dark
            brightness = 0.3 + (hsl.l / 100) * 0.7;
        } else if (hsl.l < 50) {
            // Medium colors
            brightness = 0.5 + (hsl.l / 100) * 0.6;
        } else {
            // Light colors
            brightness = 0.6 + (hsl.l / 100) * 0.5;
        }

        mockupImg.style.filter = `sepia(1) saturate(${saturate}) hue-rotate(${hueRotate}deg) brightness(${brightness})`;
    }
}

// Load an existing design for editing
function loadExistingDesign(designData) {
    if (!designData) return;

    console.log('[DEBUG] Loading design:', designData);

    // Find and select the product
    const product = productsData.find(p => String(p.id) === String(designData.product_id));
    if (!product) {
        console.error('[DEBUG] Product not found for design:', designData.product_id);
        return;
    }

    // Find and click the product card to select it visually
    const productCard = document.querySelector(`.product-choice[data-product-id="${product.id}"]`);
    if (productCard) {
        document.querySelectorAll('.product-choice').forEach(p => p.classList.remove('selected'));
        productCard.classList.add('selected');
    }

    // Set current product
    window.currentProduct = {
        id: product.id,
        name: product.name,
        basePrice: parseFloat(product.base_price),
        imagePath: product.image_path,
        backImagePath: product.back_image_path || '',
        leftSleeveImagePath: product.left_sleeve_image_path || '',
        rightSleeveImagePath: product.right_sleeve_image_path || ''
    };

    // Store the design ID, name, and email for saving updates
    window.loadedDesignId = designData.id;
    window.loadedDesignName = designData.name || '';
    window.loadedDesignEmail = designData.email || '';

    // Update sleeve buttons visibility
    if (window.currentProduct.leftSleeveImagePath || window.currentProduct.rightSleeveImagePath) {
        document.getElementById('leftSleeveBtn').style.display = window.currentProduct.leftSleeveImagePath ? '' : 'none';
        document.getElementById('rightSleeveBtn').style.display = window.currentProduct.rightSleeveImagePath ? '' : 'none';
    } else {
        document.getElementById('leftSleeveBtn').style.display = 'none';
        document.getElementById('rightSleeveBtn').style.display = 'none';
    }

    // Show correct options panel (may not exist in studio mode)
    document.querySelectorAll('.product-options').forEach(p => p.style.display = 'none');
    const optionsPanel = document.getElementById('options-' + product.id);
    if (optionsPanel) {
        optionsPanel.style.display = '';

        // Set size radio if available
        if (designData.size_id) {
            const sizeRadio = optionsPanel.querySelector(`input[type=radio][name^='size_'][value='${designData.size_id}']`);
            if (sizeRadio) sizeRadio.checked = true;
        }

        // Init color swatches and select the saved color
        initColorSwatches(product.id);

        setTimeout(() => {
            if (designData.color_id) {
                const colorSwatch = optionsPanel.querySelector(`.color-swatches .color-swatch[data-color-id='${designData.color_id}']`);
                if (colorSwatch) {
                    colorSwatch.click();
                    return;
                }
            }
            // If no swatch found, apply color directly from hex
            const colorHex = designData.saved_color_hex || designData.color_hex;
            if (colorHex) {
                applyColorTint(colorHex);
            }
        }, 100);
    } else {
        // No options panel - apply color directly if we have one saved
        setTimeout(() => {
            // Use saved_color_hex from joined query or color_hex from design
            const colorHex = designData.saved_color_hex || designData.color_hex;
            if (colorHex) {
                applyColorTint(colorHex);
            }
        }, 100);
    }

    // Load the design elements from uploads and texts arrays (fetched from separate tables)
    // These have proper file paths instead of base64 data

    // Load image uploads
    if (designData.uploads && Array.isArray(designData.uploads)) {
        console.log('[DEBUG] Loading uploads:', designData.uploads);
        designData.uploads.forEach(upload => {
            const view = upload.view_placement || 'front';
            if (!elements[view]) elements[view] = [];

            // Construct proper image src path
            // The stored path is "public/images/..." but web root is "public/", so we need "/images/..."
            let imageSrc = upload.stored_file_path || '';
            if (imageSrc) {
                // Remove "public" prefix if present (since public is web root)
                if (imageSrc.startsWith('public/')) {
                    imageSrc = imageSrc.substring(6); // Remove "public" but keep the "/"
                }
                // Ensure it starts with /
                if (!imageSrc.startsWith('/') && !imageSrc.startsWith('data:')) {
                    imageSrc = '/' + imageSrc;
                }
            }

            console.log('[DEBUG] Image src after processing:', imageSrc);

            const el = {
                id: 'element-' + (++elementIdCounter),
                type: 'image',
                src: imageSrc,
                x: parseFloat(upload.position_x) || 0,
                y: parseFloat(upload.position_y) || 0,
                width: parseFloat(upload.width) || 80,
                height: parseFloat(upload.height) || 80,
                rotation: parseFloat(upload.rotation) || 0,
                flipped: upload.is_flipped == 1,
                color: upload.color_overlay || null,
                bgRemoved: upload.bg_removed == 1,
                view: view
            };
            elements[view].push(el);
        });
    }

    // Load text elements
    if (designData.texts && Array.isArray(designData.texts)) {
        console.log('[DEBUG] Loading texts:', designData.texts);
        designData.texts.forEach(text => {
            const view = text.view_placement || 'front';
            if (!elements[view]) elements[view] = [];

            const el = {
                id: 'element-' + (++elementIdCounter),
                type: 'text',
                text: text.text_content || '',
                fontFamily: text.font_family || 'Arial, sans-serif',
                fontSize: parseInt(text.font_size) || 24,
                color: text.text_color || '#000000',
                bold: text.is_bold == 1,
                italic: text.is_italic == 1,
                underline: text.is_underline == 1,
                x: parseFloat(text.position_x) || 0,
                y: parseFloat(text.position_y) || 0,
                view: view
            };
            elements[view].push(el);
        });
    }

    // Fallback: If no uploads/texts arrays, try to parse from elements_json
    const hasLoadedElements = (designData.uploads && designData.uploads.length > 0) ||
                               (designData.texts && designData.texts.length > 0);

    if (!hasLoadedElements) {
        const elementsData = designData.elements_json || designData.design_data;
        if (elementsData) {
            try {
                const parsedElements = typeof elementsData === 'string'
                    ? JSON.parse(elementsData)
                    : elementsData;

                console.log('[DEBUG] Fallback - Parsed elements_json:', parsedElements);

                // Helper function to process an element
                const processElement = (el, view) => {
                    if (!el || el._meta) return; // Skip metadata
                    el.id = 'element-' + (++elementIdCounter);

                    // Handle image elements
                    if (el.type === 'image') {
                        // If src is null/undefined but src_type is 'uploaded', skip - the file path was lost
                        if (!el.src && el.src_type === 'uploaded') {
                            console.warn('[DEBUG] Skipping image with lost file path:', el);
                            return;
                        }

                        // Fix image src path if it exists
                        if (el.src) {
                            if (!el.src.startsWith('/') && !el.src.startsWith('data:') && !el.src.startsWith('http')) {
                                el.src = '/' + el.src;
                            }
                        } else {
                            // No src at all, skip this element
                            console.warn('[DEBUG] Skipping image with no src:', el);
                            return;
                        }
                    }

                    if (!elements[view]) elements[view] = [];
                    elements[view].push(el);
                };

                // Check if it's the new flat format (array with view property) or nested by view
                if (Array.isArray(parsedElements)) {
                    // Flat array format - each element has a 'view' property
                    parsedElements.forEach(el => {
                        const view = el.view || 'front';
                        processElement(el, view);
                    });
                } else if (typeof parsedElements === 'object') {
                    // Could be nested by view OR object with _meta and element keys
                    ['front', 'back', 'left-sleeve', 'right-sleeve'].forEach(view => {
                        if (parsedElements[view] && Array.isArray(parsedElements[view])) {
                            parsedElements[view].forEach(el => {
                                processElement(el, view);
                            });
                        }
                    });

                    // Also check for flat elements mixed with _meta (numeric keys)
                    Object.keys(parsedElements).forEach(key => {
                        if (key === '_meta' || ['front', 'back', 'left-sleeve', 'right-sleeve'].includes(key)) return;
                        const el = parsedElements[key];
                        if (el && typeof el === 'object' && el.type) {
                            const view = el.view || 'front';
                            processElement(el, view);
                        }
                    });
                }
            } catch (e) {
                console.error('[DEBUG] Error parsing elements_json:', e);
            }
        }
    }

    // Render elements for current view
    renderElements();
    updateLayerList();

    console.log('[DEBUG] Design elements loaded:', elements);

    // Update mockup and summary
    updateMockupImage();
    updateSummary();

    console.log('[DEBUG] Design loaded successfully');
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Select first product by default
    const firstProduct = document.querySelector('.product-choice');
    if (firstProduct) {
        selectProduct(firstProduct);
    }

    // Product selection
    document.querySelectorAll('.product-choice').forEach(el => {
        el.addEventListener('click', () => selectProduct(el));
    });

    // View toggle
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', () => switchView(btn.dataset.view));
    });

    // Add image button
    // Removed: addImageBtn/addTextBtn/imageUpload event listeners

    // Text editor controls
    document.getElementById('applyTextBtn').addEventListener('click', applyText);
    document.getElementById('cancelTextBtn').addEventListener('click', hideTextEditor);
    document.getElementById('fontSize').addEventListener('input', updateFontSizeDisplay);

    // Live-update text element as the user types or changes font/size/color/style
    function liveUpdateText() {
        if (!editingTextElementId) return;
        const element = elements[currentView].find(el => el.id === editingTextElementId);
        if (!element || element.type !== 'text') return;
        const text = document.getElementById('textContent').value;
        if (!text.trim()) return; // don't blank it out
        element.text = text;
        element.fontFamily = document.getElementById('fontFamily').value;
        element.fontSize = parseInt(document.getElementById('fontSize').value);
        element.color = document.getElementById('textColor').value;
        element.bold = document.getElementById('boldBtn').classList.contains('active');
        element.italic = document.getElementById('italicBtn').classList.contains('active');
        element.underline = document.getElementById('underlineBtn').classList.contains('active');
        renderElements();
        updateLayerList();
    }
    document.getElementById('textContent').addEventListener('input', liveUpdateText);
    document.getElementById('fontFamily').addEventListener('change', liveUpdateText);
    document.getElementById('fontSize').addEventListener('input', liveUpdateText);
    document.getElementById('textColor').addEventListener('input', liveUpdateText);

    // Style buttons
    ['boldBtn', 'italicBtn', 'underlineBtn'].forEach(id => {
        document.getElementById(id).addEventListener('click', function() {
            this.classList.toggle('active');
            liveUpdateText();
        });
    });

    // Document-level click to deselect elements, but ignore clicks on elements or their editor panels
    let imageClickInProgress = false;
    document.addEventListener('mousedown', function(e) {
        const designElement = e.target.closest('.design-element');
        const imageEditorPanel = e.target.closest('#imageEditorPanel');
        const textEditorModal = e.target.closest('#textEditorModal');
        // If click is inside a design element or any editor panel, do not deselect
        if (designElement || imageEditorPanel || textEditorModal) {
            return;
        }
        // Otherwise, deselect all and close editors
        deselectAll();
        var imgPanel = document.getElementById('imageEditorPanel');
        if (imgPanel) imgPanel.style.display = 'none';
        // Close text editor if open
        hideTextEditor();
    });

    // If coming from custom_product.php, prefill product/color/size directly
    const prodId = sessionStorage.getItem('custom_product_id');
    const colorId = sessionStorage.getItem('custom_color');
    const sizeId = sessionStorage.getItem('custom_size');
    if (prodId && colorId && sizeId) {
        // Find product in productsData
        const product = productsData.find(p => String(p.id) === String(prodId));
        console.log('[DEBUG] Matched product:', product);
        if (product) {
            window.currentProduct = {
                id: product.id,
                name: product.name,
                basePrice: parseFloat(product.base_price),
                imagePath: product.image_path,
                backImagePath: product.back_image_path || '',
                leftSleeveImagePath: product.left_sleeve_image_path || '',
                rightSleeveImagePath: product.right_sleeve_image_path || ''
            };
            // Show sleeve buttons if any sleeve image exists
            if (window.currentProduct.leftSleeveImagePath || window.currentProduct.rightSleeveImagePath) {
                document.getElementById('leftSleeveBtn').style.display = window.currentProduct.leftSleeveImagePath ? '' : 'none';
                document.getElementById('rightSleeveBtn').style.display = window.currentProduct.rightSleeveImagePath ? '' : 'none';
            } else {
                document.getElementById('leftSleeveBtn').style.display = 'none';
                document.getElementById('rightSleeveBtn').style.display = 'none';
            }
            // Show correct options panel
            document.querySelectorAll('.product-options').forEach(p => p.style.display = 'none');
            const optionsPanel = document.getElementById('options-' + product.id);
            if (optionsPanel) {
                optionsPanel.style.display = '';
                // Set size radio
                const sizeRadio = optionsPanel.querySelector(`input[type=radio][name^='size_'][value='${sizeId}']`);
                if (sizeRadio) sizeRadio.checked = true;
                // Re-init color swatches for this size
                initColorSwatches(product.id);
                // Set color swatch AFTER initColorSwatches completes (it has a 50ms timeout)
                setTimeout(() => {
                    const colorSwatch = optionsPanel.querySelector(`.color-swatches .color-swatch[data-color-id='${colorId}']`);
                    if (colorSwatch) {
                        colorSwatch.click();
                        console.log('[DEBUG] Applied color from sessionStorage:', colorId);
                    }
                }, 100);
            }
            // Update mockup and summary
            updateMockupImage();
            updateSummary();
        }
        // Optionally clear sessionStorage so it doesn't auto-select again
        sessionStorage.removeItem('custom_product_id');
        sessionStorage.removeItem('custom_color');
        sessionStorage.removeItem('custom_size');
    }

    // Load existing design if editing
    if (loadDesignData) {
        console.log('[DEBUG] Loading existing design:', loadDesignData);
        loadExistingDesign(loadDesignData);
    }
});

function selectProduct(el) {
    // Update UI
    document.querySelectorAll('.product-choice').forEach(p => p.classList.remove('selected'));
    el.classList.add('selected');

    // Get product data
    const productId = el.dataset.productId;
    const productName = el.dataset.productName;
    const basePrice = parseFloat(el.dataset.basePrice);
    const imagePath = el.dataset.image;
    const backImagePath = el.dataset.backImage;
    const hasSleeves = el.dataset.hasSleeves === '1';

    window.currentProduct = {
        id: productId,
        name: productName,
        basePrice: basePrice,
        imagePath: imagePath,
        backImagePath: backImagePath,
        leftSleeveImagePath: el.dataset.leftSleeveImage || '',
        rightSleeveImagePath: el.dataset.rightSleeveImage || ''
    };
    // ...existing code...
    // Update mockup image
    updateMockupImage();
    // Show sleeve buttons if any sleeve image exists
    if (window.currentProduct.leftSleeveImagePath || window.currentProduct.rightSleeveImagePath) {
        document.getElementById('leftSleeveBtn').style.display = window.currentProduct.leftSleeveImagePath ? '' : 'none';
        document.getElementById('rightSleeveBtn').style.display = window.currentProduct.rightSleeveImagePath ? '' : 'none';
    } else {
        document.getElementById('leftSleeveBtn').style.display = 'none';
        document.getElementById('rightSleeveBtn').style.display = 'none';
    }
    // If currently on sleeve view but product doesn't have sleeves, switch to front
    if (!(window.currentProduct.leftSleeveImagePath || window.currentProduct.rightSleeveImagePath) && (currentView === 'left-sleeve' || currentView === 'right-sleeve')) {
        switchView('front');
    }

    // Show correct options panel
    document.querySelectorAll('.product-options').forEach(p => p.style.display = 'none');
    const optionsPanel = document.getElementById('options-' + productId);
    if (optionsPanel) {
        optionsPanel.style.display = '';
        initColorSwatches(productId);
    }

    // Initialize studio color panel
    initStudioColorPanel(productId);

    // Update summary
    updateSummary();
}

function updateMockupImage() {
    const mockupImg = document.getElementById('mockupProduct');
    const placeholder = document.getElementById('mockupPlaceholder');

    if (!window.currentProduct) {
        mockupImg.style.display = 'none';
        placeholder.style.display = '';
        return;
    }

    let imagePath = '';
    if (currentView === 'front') {
        imagePath = window.currentProduct.imagePath;
    } else if (currentView === 'back') {
        imagePath = window.currentProduct.backImagePath || window.currentProduct.imagePath;
    } else if (currentView === 'left-sleeve') {
        imagePath = window.currentProduct.leftSleeveImagePath || window.currentProduct.imagePath;
    } else if (currentView === 'right-sleeve') {
        imagePath = window.currentProduct.rightSleeveImagePath || window.currentProduct.imagePath;
    }

    if (imagePath) {
        mockupImg.src = '/' + imagePath;
        mockupImg.style.display = '';
        placeholder.style.display = 'none';

        // Re-apply color tint after image loads
        mockupImg.onload = function() {
            if (currentColorHex) {
                applyColorTint(currentColorHex);
            }
        };
    } else {
        mockupImg.style.display = 'none';
        placeholder.style.display = '';
        placeholder.textContent = 'No image available';
    }

    // Update design area label
    const labels = {
        'front': 'Front Design Area',
        'back': 'Back Design Area',
        'left-sleeve': 'Left Sleeve',
        'right-sleeve': 'Right Sleeve'
    };
    document.getElementById('designAreaLabel').textContent = labels[currentView];
}

function switchView(view) {
    currentView = view;

    // Update button states
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.view === view);
    });

    // Update design area size for sleeves vs front/back
    const designArea = document.getElementById('designArea');
    if (view === 'left-sleeve' || view === 'right-sleeve') {
        designArea.classList.add('sleeve-view');
    } else {
        designArea.classList.remove('sleeve-view');
    }

    // Update mockup
    updateMockupImage();

    // Show elements for this view
    renderElements();
}

function initColorSwatches(productId) {
    const container = document.getElementById('colors-' + productId);
    if (!container) return;

    const sizeInput = document.querySelector(`input[name="size_${productId}"]:checked`);
    if (!sizeInput) return;

    const colorIds = sizeInput.dataset.colors ? sizeInput.dataset.colors.split(',') : [];
    const colorNames = sizeInput.dataset.colorNames ? sizeInput.dataset.colorNames.split(',') : [];
    const colorHexes = sizeInput.dataset.colorHexes ? sizeInput.dataset.colorHexes.split(',') : [];

    container.innerHTML = '';

    colorIds.forEach((id, index) => {
        const swatch = document.createElement('div');
        swatch.className = 'color-swatch' + (index === 0 ? ' selected' : '');
        swatch.style.backgroundColor = colorHexes[index] || '#ccc';
        swatch.title = colorNames[index] || 'Color';
        swatch.dataset.colorId = id;
        swatch.dataset.hex = colorHexes[index] || '#ffffff';

        // Add dark border for white colors
        const hex = (colorHexes[index] || '').toLowerCase();
        const name = (colorNames[index] || '').toLowerCase();
        if (hex === '#ffffff' || hex === '#fff' || name === 'white') {
            swatch.classList.add('is-white');
        }

        swatch.addEventListener('click', () => selectColor(swatch, container));
        container.appendChild(swatch);
    });

    // Apply the first color after all swatches are created
    if (colorHexes.length > 0 && colorHexes[0]) {
        // Small delay to ensure image is ready
        setTimeout(() => applyColorTint(colorHexes[0]), 50);
    }

    // Listen for size changes
    document.querySelectorAll(`input[name="size_${productId}"]`).forEach(input => {
        input.addEventListener('change', () => {
            initColorSwatches(productId);
            updateSummary();
        });
    });
}

function selectColor(swatch, container) {
    container.querySelectorAll('.color-swatch').forEach(s => s.classList.remove('selected'));
    swatch.classList.add('selected');

    // Apply color tint to the mockup
    const colorHex = swatch.dataset.hex;
    if (colorHex) {
        applyColorTint(colorHex);
    }

    updateSummary();
}

// Studio Color & Size Panel Functions
window.studioVariantsData = { variants: [], sizes: [], colors: [] };
window.studioSelectedColorId = null;

function initStudioColorPanel(productId) {
    const panel = document.getElementById('productOptionsPanel');
    if (!panel) return;

    // Show the panel
    panel.style.display = 'block';

    // Fetch variants from API
    fetch('/api/product-variants/' + productId)
        .then(response => response.json())
        .then(data => {
            window.studioVariantsData.variants = data.variants || [];
            window.studioVariantsData.sizes = data.sizes || [];
            window.studioVariantsData.colors = data.colors || [];
            renderStudioColorSwatches();
        })
        .catch(err => {
            console.error('Failed to fetch studio variants:', err);
            panel.style.display = 'none';
        });
}

function renderStudioColorSwatches() {
    const container = document.getElementById('studioColorSwatches');
    if (!container) return;

    container.innerHTML = '';

    if (window.studioVariantsData.colors.length === 0) {
        container.innerHTML = '<span style="color:#888; font-size:0.9rem;">No colors available</span>';
        return;
    }

    window.studioVariantsData.colors.forEach((color, index) => {
        const swatch = document.createElement('div');
        swatch.className = 'studio-color-swatch' + (index === 0 ? ' selected' : '');
        swatch.style.cssText = `
            width: 36px; height: 36px; border-radius: 50%; cursor: pointer;
            background: ${color.hex || '#ccc'}; border: 3px solid #ddd;
            transition: all 0.2s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        `;
        swatch.title = color.name || 'Color';
        swatch.dataset.colorId = color.id;
        swatch.dataset.hex = color.hex || '#ffffff';
        swatch.dataset.colorName = color.name || 'Color';

        // Add darker border for white/light colors
        const hex = (color.hex || '').toLowerCase();
        if (hex === '#ffffff' || hex === '#fff' || hex === 'white') {
            swatch.style.border = '3px solid #ccc';
        }

        swatch.addEventListener('mouseenter', () => {
            swatch.style.transform = 'scale(1.1)';
        });
        swatch.addEventListener('mouseleave', () => {
            swatch.style.transform = 'scale(1)';
        });
        swatch.addEventListener('click', () => selectStudioColor(swatch));

        container.appendChild(swatch);
    });

    // Auto-select first color
    if (window.studioVariantsData.colors.length > 0) {
        const firstSwatch = container.querySelector('.studio-color-swatch');
        if (firstSwatch) {
            selectStudioColor(firstSwatch);
        }
    }
}

function selectStudioColor(swatch) {
    const container = document.getElementById('studioColorSwatches');
    if (container) {
        container.querySelectorAll('.studio-color-swatch').forEach(s => {
            s.classList.remove('selected');
            s.style.border = '3px solid #ddd';
            const hex = (s.dataset.hex || '').toLowerCase();
            if (hex === '#ffffff' || hex === '#fff') {
                s.style.border = '3px solid #ccc';
            }
        });
    }

    swatch.classList.add('selected');
    swatch.style.border = '3px solid #4CAF50';

    window.studioSelectedColorId = swatch.dataset.colorId;
    const colorHex = swatch.dataset.hex;

    // Apply color tint to mockup
    if (colorHex) {
        applyColorTint(colorHex);
    }

    // Update available sizes for this color
    renderStudioSizesForColor(window.studioSelectedColorId);

    updateSummary();
}

function renderStudioSizesForColor(colorId) {
    const container = document.getElementById('studioAvailableSizes');
    if (!container) return;

    container.innerHTML = '';

    // Find all variants with this color that are available
    const availableSizes = [];
    window.studioVariantsData.variants.forEach(variant => {
        if (variant.color_id == colorId && variant.is_available) {
            // Find size name
            const size = window.studioVariantsData.sizes.find(s => s.id == variant.size_id);
            if (size && !availableSizes.some(s => s.id === size.id)) {
                availableSizes.push(size);
            }
        }
    });

    if (availableSizes.length === 0) {
        container.innerHTML = '<span style="color:#888; font-size:0.9rem;">No sizes available for this color</span>';
        return;
    }

    // Sort sizes (common order: XS, S, M, L, XL, 2XL, 3XL, etc.)
    const sizeOrder = ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL'];
    availableSizes.sort((a, b) => {
        const aIndex = sizeOrder.indexOf(a.name.toUpperCase());
        const bIndex = sizeOrder.indexOf(b.name.toUpperCase());
        if (aIndex === -1 && bIndex === -1) return a.name.localeCompare(b.name);
        if (aIndex === -1) return 1;
        if (bIndex === -1) return -1;
        return aIndex - bIndex;
    });

    availableSizes.forEach(size => {
        const badge = document.createElement('span');
        badge.style.cssText = `
            display: inline-block; padding: 6px 12px; background: #f5f5f5;
            border: 1px solid #e0e0e0; border-radius: 6px; font-size: 0.85rem;
            color: #555; font-weight: 500;
        `;
        badge.textContent = size.name;
        container.appendChild(badge);
    });
}

function handleImageUpload(e) {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(event) {
        addImageElement(event.target.result);
    };
    reader.readAsDataURL(file);

    // Reset input
    e.target.value = '';
}

function addImageElement(src) {
    const id = 'element-' + (++elementIdCounter);
    const element = {
        id: id,
        type: 'image',
        src: src,
        x: 50,
        y: 50,
        width: 80,
        height: 80,
        view: currentView
    };
    // Store original for reset
    element._original = JSON.parse(JSON.stringify(element));
    elements[currentView].push(element);
    renderElements();
    selectElementById(id);
    updateLayerList();
    updateSummary();
}

function showTextEditor() {
    // Reset editing state - this is for new text
    editingTextElementId = null;

    // Reset all form fields to defaults
    document.getElementById('textContent').value = '';
    document.getElementById('fontFamily').value = 'Arial';
    document.getElementById('fontSize').value = 24;
    document.getElementById('fontSizeDisplay').textContent = '24px';
    document.getElementById('textColor').value = '#000000';
    document.getElementById('boldBtn').classList.remove('active');
    document.getElementById('italicBtn').classList.remove('active');
    document.getElementById('underlineBtn').classList.remove('active');

    document.getElementById('textEditorTitle').textContent = 'Add Text';
    document.getElementById('textEditorModal').style.display = 'flex';
    document.getElementById('textContent').focus();
}

function hideTextEditor() {
    document.getElementById('textEditorModal').style.display = 'none';
    editingTextElementId = null;
}

// Variable to track which text element we're editing
let editingTextElementId = null;

function editTextElement(elementId) {
    // Find the element in the current view's elements
    const element = elements[currentView].find(el => el.id === elementId);
    if (!element || element.type !== 'text') return;

    // Store which element we're editing
    editingTextElementId = elementId;

    // Populate the text editor with current values
    document.getElementById('textContent').value = element.text;
    document.getElementById('fontFamily').value = element.fontFamily;
    document.getElementById('fontSize').value = element.fontSize;
    document.getElementById('fontSizeDisplay').textContent = element.fontSize + 'px';
    document.getElementById('textColor').value = element.color;

    // Set style buttons
    document.getElementById('boldBtn').classList.toggle('active', element.bold);
    document.getElementById('italicBtn').classList.toggle('active', element.italic);
    document.getElementById('underlineBtn').classList.toggle('active', element.underline);

    // Show the text editor modal
    document.getElementById('textEditorTitle').textContent = 'Edit Text';
    document.getElementById('textEditorModal').style.display = 'flex';
    document.getElementById('textContent').focus();
}

function updateFontSizeDisplay() {
    document.getElementById('fontSizeDisplay').textContent =
        document.getElementById('fontSize').value + 'px';
}

function applyText() {
    const text = document.getElementById('textContent').value.trim();
    if (!text) return;

    // Check if we're editing an existing element
    if (editingTextElementId) {
        const element = elements[currentView].find(el => el.id === editingTextElementId);
        if (element) {
            // Update the existing element
            element.text = text;
            element.fontFamily = document.getElementById('fontFamily').value;
            element.fontSize = parseInt(document.getElementById('fontSize').value);
            element.color = document.getElementById('textColor').value;
            element.bold = document.getElementById('boldBtn').classList.contains('active');
            element.italic = document.getElementById('italicBtn').classList.contains('active');
            element.underline = document.getElementById('underlineBtn').classList.contains('active');

            renderElements();
            selectElementById(editingTextElementId);
            updateLayerList();
            updateSummary();
            return;
        }
    }

    // Otherwise create a new element
    const id = 'element-' + (++elementIdCounter);
    const element = {
        id: id,
        type: 'text',
        text: text,
        fontFamily: document.getElementById('fontFamily').value,
        fontSize: parseInt(document.getElementById('fontSize').value),
        color: document.getElementById('textColor').value,
        bold: document.getElementById('boldBtn').classList.contains('active'),
        italic: document.getElementById('italicBtn').classList.contains('active'),
        underline: document.getElementById('underlineBtn').classList.contains('active'),
        x: 50,
        y: 50,
        view: currentView
    };

    elements[currentView].push(element);
    renderElements();
    selectElementById(id);
    updateLayerList();
    updateSummary();
}

function renderElements() {
    const designArea = document.getElementById('designArea');

    // Remove all elements except the label
    designArea.querySelectorAll('.design-element').forEach(el => el.remove());

    // Add elements for current view
    elements[currentView].forEach(element => {
        const div = document.createElement('div');
        div.className = 'design-element' + (selectedElement === element.id ? ' selected' : '');
        div.id = element.id;
        div.style.left = element.x + 'px';
        div.style.top = element.y + 'px';
        if (element.type === 'image') {
            div.style.width = element.width + 'px';
            div.style.height = element.height + 'px';
            div.setAttribute('data-type', 'image');
            if (selectedElement === element.id) {
                div.setAttribute('data-selected', 'true');
            } else {
                div.removeAttribute('data-selected');
            }
            let imgStyle = 'width: 100%; height: 100%; object-fit: contain;';
            // Combine rotation and flip in a single transform
            let transforms = [];
            if (element.rotation) {
                transforms.push(`rotate(${element.rotation}deg)`);
            }
            if (element.flipped) {
                transforms.push('scaleX(-1)');
            }
            if (transforms.length > 0) {
                imgStyle += ` transform: ${transforms.join(' ')};`;
            }
            let overlay = '';
            if (element.color && element.color !== '#ffffff' && element.color !== '#fff') {
                overlay = `<div style='position:absolute;top:0;left:0;width:100%;height:100%;background:${element.color};opacity:0.35;pointer-events:none;mix-blend-mode:multiply;'></div>`;
            }
            let opacity = element.bgRemoved ? 0.5 : 1;

            // Ensure image src is valid
            let imgSrc = element.src || '';
            if (imgSrc && imgSrc.startsWith('public/')) {
                imgSrc = '/' + imgSrc.substring(7); // Remove "public" but keep "/"
            } else if (imgSrc && !imgSrc.startsWith('/') && !imgSrc.startsWith('data:') && !imgSrc.startsWith('http')) {
                imgSrc = '/' + imgSrc;
            }

            div.innerHTML = `
                <div style="position:relative;width:100%;height:100%;">
                  <img src="${imgSrc}" style="${imgStyle};opacity:${opacity};" onerror="console.error('Failed to load image:', this.src); this.style.border='2px solid red';">
                  ${overlay}
                </div>
                <div class="resize-handle"></div>
                <button class="delete-btn" onclick="deleteElement('${element.id}')">x</button>
            `;
        } else if (element.type === 'text') {
            let style = `
                font-family: ${element.fontFamily};
                font-size: ${element.fontSize}px;
                color: ${element.color};
                ${element.bold ? 'font-weight: bold;' : ''}
                ${element.italic ? 'font-style: italic;' : ''}
                ${element.underline ? 'text-decoration: underline;' : ''}
            `;
            div.innerHTML = `
                <div class="text-content" style="${style}">${escapeHtml(element.text)}</div>
                <button class="edit-btn" onclick="editTextElement('${element.id}')" title="Edit text">E</button>
                <button class="delete-btn" onclick="deleteElement('${element.id}')">x</button>
            `;

            // Double-click to edit text
            div.addEventListener('dblclick', () => editTextElement(element.id));
        }

        div.addEventListener('mousedown', (e) => {
            if (!e.target.classList.contains('delete-btn')) {
                selectElementById(element.id);
            }
        });

        designArea.appendChild(div);

        // Initialize interact.js
        initInteract(div, element);
    });
}

function initInteract(div, element) {
    interact(div)
        .draggable({
            inertia: false,
            modifiers: [
                interact.modifiers.restrictRect({
                    restriction: 'parent',
                    endOnly: true
                })
            ],
            listeners: {
                move (event) {
                    let target = event.target;
                    let x = (parseFloat(target.style.left) || 0) + event.dx;
                    let y = (parseFloat(target.style.top) || 0) + event.dy;
                    // Constrain within bounds
                    const parentRect = target.parentNode.getBoundingClientRect();
                    const rect = target.getBoundingClientRect();
                    x = Math.max(0, Math.min(x, parentRect.width - rect.width));
                    y = Math.max(0, Math.min(y, parentRect.height - rect.height));
                    target.style.left = `${x}px`;
                    target.style.top = `${y}px`;
                    // Update element data
                    const elementId = target.id;
                    const el = elements[currentView].find(e => e.id === elementId);
                    if (el) {
                        el.x = x;
                        el.y = y;
                    }
                }
            }
        })
        .resizable({
            edges: { left: true, right: true, bottom: true, top: true },
            listeners: {
                move (event) {
                    let target = event.target;
                    // Update width and height
                    const newWidth = event.rect.width;
                    const newHeight = event.rect.height;
                    target.style.width = `${newWidth}px`;
                    target.style.height = `${newHeight}px`;
                    // Update element data
                    const elementId = target.id;
                    const el = elements[currentView].find(e => e.id === elementId);
                    if (el) {
                        el.width = newWidth;
                        el.height = newHeight;
                    }
                }
            }
        });
}

// Deselect all elements
function deselectAll() {
    selectedElement = null;
    ['front', 'back', 'left-sleeve', 'right-sleeve'].forEach(view => {
        elements[view].forEach(el => {
            const domEl = document.getElementById(el.id);
            if (domEl) {
                domEl.classList.remove('selected');
                domEl.removeAttribute('data-selected');
            }
        });
    });
}

// Select element by ID
function selectElementById(id) {
    selectedElement = id;
    // Remove selected class from all elements first
    document.querySelectorAll('.design-element.selected').forEach(el => {
        el.classList.remove('selected');
        el.removeAttribute('data-selected');
    });
    const elementDiv = document.getElementById(id);
    if (elementDiv) {
        elementDiv.classList.add('selected');
        // Scroll to element if it's outside the visible area
        const designArea = document.getElementById('designArea');
        const rect = elementDiv.getBoundingClientRect();
        const areaRect = designArea.getBoundingClientRect();
        if (rect.top < areaRect.top || rect.bottom > areaRect.bottom) {
            designArea.scrollTop += rect.top - areaRect.top;
        }
        if (rect.left < areaRect.left || rect.right > areaRect.right) {
            designArea.scrollLeft += rect.left - areaRect.left;
        }
    }
    const el = elements[currentView].find(e => e.id === id);
    document.getElementById('uploadEditorModal').style.display = 'none';

    if (el && el.type === 'image') {
        // Show image editor, hide text editor
        document.getElementById('textEditorModal').style.display = 'none';
        editingTextElementId = null;
        document.getElementById('imageEditorPanel').style.display = 'flex';
        showImageEditor(el);
    } else if (el && el.type === 'text') {
        // Show text editor with this element's properties, hide image editor
        document.getElementById('imageEditorPanel').style.display = 'none';
        hideImageEditor();
        editTextElement(el.id);
    } else {
        document.getElementById('textEditorModal').style.display = 'none';
        editingTextElementId = null;
        document.getElementById('imageEditorPanel').style.display = 'none';
        hideImageEditor();
    }
}

// Update the layer list in the UI
function updateLayerList() {
    const list = document.getElementById('layerList');
    list.innerHTML = '';

    elements[currentView].forEach((element, index) => {
        const div = document.createElement('div');
        div.className = 'layer-item' + (selectedElement === element.id ? ' active' : '');
        div.textContent = element.type === 'text' ? element.text : `Image ${index + 1}`;
        div.addEventListener('click', () => {
            selectElementById(element.id);
        });
        list.appendChild(div);
    });

    // Show empty message if no elements
    if (elements[currentView].length === 0) {
        list.innerHTML = '<div class="layer-empty">No elements added yet</div>';
    }
}

// Update order summary section
function updateSummary() {
    const productName = window.currentProduct ? window.currentProduct.name : '-';
    const summaryProductEl = document.getElementById('summaryProduct');
    if (summaryProductEl) {
        summaryProductEl.textContent = productName;
    }
}

// Calculate size modifier based on selected size
function calculateSizeModifier() {
    return 0;
}

// Escape HTML for text content
function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Delete an element by ID
function deleteElement(id) {
    // Remove from elements array
    elements[currentView] = elements[currentView].filter(el => el.id !== id);

    // Rerender elements
    renderElements();
    updateLayerList();
    updateSummary();

    // Deselect if the deleted element was selected
    if (selectedElement === id) {
        deselectAll();
    }
}

function saveImageEdit() {
    // Alias: saving triggers the save design modal
    openSaveDesignModal();
}
