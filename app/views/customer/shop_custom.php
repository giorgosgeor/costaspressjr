<!-- Add to Cart Modal (with size/color/quantity selection) -->
<div id="addToCartModal" style="display:none; position:fixed; z-index:35000; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.25); align-items:center; justify-content:center;" onclick="if(event.target === this) closeAddToCartModal();">
    <div style="background:#fff; border-radius:18px; max-width:520px; width:95vw; margin:auto; box-shadow:0 2px 24px rgba(0,0,0,0.16); padding:2rem; position:relative; max-height:90vh; overflow-y:auto;" onclick="event.stopPropagation();">
        <button onclick="closeAddToCartModal()" style="position:absolute; top:1rem; right:1rem; background:none; border:none; font-size:2rem; color:#888; cursor:pointer;">&times;</button>
        <h2 style="font-size:1.4rem; font-weight:700; margin-bottom:1rem; text-align:center; color:#333;"><?= t('studio.cart.title') ?></h2>
        
        <!-- Design Preview - HTML based for reliability -->
        <div id="cartDesignPreview" style="text-align:center; margin-bottom:1.5rem; background:#f5f5f5; border-radius:12px; padding:1rem; position:relative;">
            <div id="cartPreviewContainer" style="position:relative; width:200px; height:200px; margin:0 auto; overflow:hidden; border-radius:8px;">
                <img id="cartPreviewProduct" src="" alt="Product" style="width:100%; height:100%; object-fit:contain;">
                <div id="cartPreviewDesignArea" style="position:absolute; left:50%; top:25%; width:45%; height:60%; transform:translateX(-50%); overflow:hidden;"></div>
            </div>
            <div id="cartProductName" style="font-weight:600; margin-top:0.5rem; color:#333;"></div>
            <div id="cartDesignName" style="font-size:0.9rem; color:#666;"></div>
        </div>
        
        <!-- Size Selection -->
        <div style="margin-bottom:1.2rem;">
            <label style="font-weight:600; display:block; margin-bottom:0.5rem; display:flex; justify-content:space-between; align-items:center;">
                <span><?= t('studio.cart.size') ?></span>
                <a id="studioSizeGuideLink" href="#"
                   onclick="event.preventDefault(); if (window.currentProduct && window.currentProduct.sizeChartImage) openSizeGuide(window.currentProduct.sizeChartImage, window.currentProduct.name || 'Product');"
                   style="display:none; font-size:0.8rem; color:#2A4FE0; text-decoration:none; font-weight:500;">
                    📏 Size guide
                </a>
            </label>
            <div id="cartSizeOptions" style="display:flex; flex-wrap:wrap; gap:8px;"></div>
        </div>
        
        <!-- Color Selection -->
        <div style="margin-bottom:1.2rem;">
            <label style="font-weight:600; display:block; margin-bottom:0.5rem;"><?= t('studio.cart.color') ?></label>
            <div id="cartColorOptions" style="display:flex; flex-wrap:wrap; gap:8px;"></div>
        </div>
        
        <!-- Availability Grid -->
        <div id="availabilityGrid" style="margin-bottom:1.2rem; overflow-x:auto; display:none;">
            <table id="variantTable" style="border-collapse:collapse; width:100%; font-size:0.85rem;">
                <thead id="variantTableHead"></thead>
                <tbody id="variantTableBody"></tbody>
            </table>
        </div>
        
        <!-- Quantity Selector -->
        <div style="margin-bottom:1.5rem;">
            <label style="font-weight:600; display:block; margin-bottom:0.5rem;"><?= t('studio.cart.quantity') ?></label>
            <div style="display:flex; align-items:center; gap:12px;">
                <button onclick="adjustCartQuantity(-1)" style="width:36px; height:36px; background:#eee; border:1px solid #ddd; border-radius:8px; font-size:1.2rem; cursor:pointer;">−</button>
                <input id="cartQuantity" type="number" value="1" min="1" max="100" style="width:60px; text-align:center; padding:8px; border:1px solid #ddd; border-radius:8px; font-size:1rem;">
                <button onclick="adjustCartQuantity(1)" style="width:36px; height:36px; background:#eee; border:1px solid #ddd; border-radius:8px; font-size:1.2rem; cursor:pointer;">+</button>
            </div>
        </div>
        
        <!-- Price Summary -->
        <div id="cartPriceSummary" style="background:#f9f9f9; padding:1rem; border-radius:10px; margin-bottom:1.2rem;">
            <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                <span><?= t('studio.cart.base_price') ?></span>
                <span id="cartBasePrice">€0.00</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                <span><?= t('studio.cart.design_fee') ?></span>
                <span id="cartDesignFee">€0.00</span>
            </div>
            <div style="display:flex; justify-content:space-between; font-weight:700; border-top:1px solid #ddd; padding-top:0.5rem; margin-top:0.5rem;">
                <span><?= t('studio.cart.total') ?></span>
                <span id="cartTotalPrice">€0.00</span>
            </div>
        </div>
        
        <!-- Error Message -->
        <div id="cartError" style="display:none; color:#dc3545; text-align:center; margin-bottom:1rem; font-size:0.95rem;"></div>
        
        <!-- Add to Cart Button -->
        <button id="confirmAddToCartBtn" style="width:100%; background:#2d5fff; color:#fff; font-weight:600; font-size:1.1rem; padding:12px 0; border:none; border-radius:8px; cursor:pointer; margin-bottom:0.7rem;"><?= t('studio.cart.add') ?></button>

        <!-- Go to Checkout Button -->
        <button id="goToCheckoutFromCartBtn" onclick="window.location.href='/cart'" style="width:100%; background:#28a745; color:#fff; font-weight:600; font-size:1rem; padding:10px 0; border:none; border-radius:8px; cursor:pointer;"><?= t('studio.cart.checkout') ?></button>
    </div>
</div>

<script>
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
                <strong>${window.I18N.t('studio.cart.success_title')}</strong><br>
                <small>${window.I18N.t('studio.cart.success_note')}</small>
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
</script>

<!-- Design Saved Success Modal -->
<div id="designSavedModal" style="display:none; position:fixed; z-index:30000; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18); align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:18px; max-width:420px; width:92vw; margin:auto; box-shadow:0 2px 24px rgba(0,0,0,0.13); padding:2.2rem 2.2rem 1.5rem 2.2rem; position:relative; text-align:center;">
        <button onclick="closeDesignSavedModal()" style="position:absolute; top:1.1rem; right:1.1rem; background:none; border:none; font-size:2rem; color:#888; cursor:pointer;">&times;</button>
        <h2 style="font-size:1.5rem; font-weight:700; margin-bottom:0.7rem; color:#2d5fff;"><?= t('studio.saved.title') ?></h2>
        <div style="font-size:1.08rem; color:#444; margin-bottom:1.2rem;"><?= t('studio.saved.lead') ?></div>
        <button id="addToCartNowBtn" style="background:#2d5fff; color:#fff; font-weight:600; font-size:1.1rem; padding:12px 32px; border-radius:8px; border:none; margin-bottom:0.7rem; cursor:pointer; width:100%;"><?= t('studio.saved.add_to_cart') ?></button>
        <button onclick="closeDesignSavedModal(); window.location.href='/';" style="background:#eee; color:#666; font-weight:600; font-size:1rem; padding:10px 24px; border-radius:8px; border:none; cursor:pointer; width:100%;"><?= t('studio.saved.exit') ?></button>
    </div>
</div>
<!-- Save Design Modal -->
<div id="saveDesignModal" style="display:none; position:fixed; z-index:20000; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18); align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:18px; max-width:420px; width:95vw; margin:auto; box-shadow:0 2px 24px rgba(0,0,0,0.13); padding:2.2rem 2.2rem 1.5rem 2.2rem; position:relative;">
        <button onclick="closeSaveDesignModal()" style="position:absolute; top:1.1rem; right:1.1rem; background:none; border:none; font-size:2rem; color:#888; cursor:pointer;">&times;</button>
        <h2 id="saveModalTitle" style="font-size:2rem; font-weight:700; margin-bottom:0.5rem; text-align:center;"><?= t('studio.save_modal.title') ?></h2>
        <div style="text-align:center; color:#444; font-size:1.08rem; margin-bottom:1.2rem;"><?= t('studio.save_modal.subtitle') ?></div>
        
        <!-- Editing mode: Show update options -->
        <div id="saveEditingMode" style="display:none; margin-bottom:1.5rem;">
            <div style="background:#f0f4ff; border-radius:10px; padding:1rem; margin-bottom:1rem;">
                <div style="font-weight:600; color:#333; margin-bottom:0.3rem;"><?= t('studio.save_modal.editing_prefix') ?> <span id="editingDesignName"></span></div>
                <div style="font-size:0.9rem; color:#666;"><?= t('studio.save_modal.editing_note') ?></div>
            </div>
            <button id="updateDesignBtn" type="button" style="width:100%;background:#2d5fff;color:#fff;font-size:1.13rem;font-weight:600;padding:12px 0;border:none;border-radius:8px;cursor:pointer;margin-bottom:0.8rem;"><?= t('studio.save_modal.update') ?></button>
            <button id="deleteDesignBtn" type="button" style="width:100%;background:#dc3545;color:#fff;font-size:1rem;font-weight:600;padding:10px 0;border:none;border-radius:8px;cursor:pointer;margin-bottom:0.8rem;"><?= t('studio.save_modal.delete') ?></button>
            <div style="text-align:center; color:#888; font-size:0.9rem; margin-bottom:0.8rem;"><?= t('studio.save_modal.or') ?></div>
        </div>
        
        <!-- Login required notice (shown when guest tries to save) -->
        <div id="saveLoginNotice" style="display:none; background:#fff3cd; border:1.5px solid #ffc107; border-radius:10px; padding:1rem 1.2rem; margin-bottom:1.2rem; text-align:center;">
            <div style="font-size:1rem; font-weight:600; color:#856404; margin-bottom:0.5rem;"><?= t('studio.save_modal.login_title') ?></div>
            <div style="font-size:0.9rem; color:#856404; margin-bottom:0.8rem;"><?= t('studio.save_modal.login_note') ?></div>
            <a href="/login" target="_blank" onclick="this.closest('#saveLoginNotice').querySelector('.retry-hint').style.display='block'" style="display:inline-block; background:#2d5fff; color:#fff; font-weight:600; padding:8px 24px; border-radius:7px; text-decoration:none; font-size:1rem;"><?= t('studio.save_modal.login_btn') ?></a>
            <div class="retry-hint" style="display:none; margin-top:0.7rem; font-size:0.88rem; color:#555;"><?= t('studio.save_modal.login_retry', false) ?></div>
        </div>

        <div id="saveNewMode">
            <div style="margin-bottom:1.1rem;">
                <label for="saveDesignName" style="font-weight:500;"><?= t('studio.save_modal.name_label') ?></label>
                <input id="saveDesignName" maxlength="25" placeholder="<?= t('studio.save_modal.name_placeholder') ?>" style="width:100%;margin-top:6px;padding:8px 10px;font-size:1rem;border:1.5px solid #ddd;border-radius:7px;">
                <div style="font-size:0.92rem;color:#888;margin-top:2px;"><?= t('studio.save_modal.name_hint') ?></div>
            </div>
            <div style="margin-bottom:1.1rem;">
                <label for="saveDesignEmail" style="font-weight:500;"><?= t('studio.save_modal.email_label') ?></label>
                <input id="saveDesignEmail" type="email" placeholder="<?= t('studio.save_modal.email_placeholder') ?>" style="width:100%;margin-top:6px;padding:8px 10px;font-size:1rem;border:1.5px solid #ddd;border-radius:7px;">
            </div>
            <div style="margin-bottom:1.1rem;display:flex;align-items:center;gap:8px;">
                <input id="saveDesignPrivacy" type="checkbox" style="width:18px;height:18px;">
                <label for="saveDesignPrivacy" style="font-size:0.98rem;"><?= t('studio.save_modal.privacy', false, ['link' => '<a href="/info/privacy" style="color:#2d5fff;" target="_blank">' . t('studio.save_modal.privacy_link', false) . '</a>']) ?></label>
            </div>
            <button id="saveDesignModalBtn" type="button" style="width:100%;background:#eee;color:#aaa;font-size:1.13rem;font-weight:600;padding:12px 0;border:none;border-radius:8px;cursor:not-allowed;margin-bottom:1.2rem;"><?= t('studio.save_modal.save_new') ?></button>
        </div>
    </div>
</div>
<script>
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
        
        const tintOverride = window.CostasTint && window.CostasTint.getOverride(hex);
        if (tintOverride) return tintOverride;

        if (isWhite) return 'saturate(0) brightness(2) contrast(0.8)';
        if (isBlack) return 'saturate(0) brightness(0.65) contrast(1.1)';
        if (isGray) {
            const brightness = 0.2 + (l / 100) * 1.5;
            return `saturate(0) brightness(${brightness})`;
        }
        
        const hueRotate = h - 38; // sepia base hue ≈ 38°
        const isReddish   = h <= 20 || h >= 340;
        const isYellowish = h >= 45 && h <= 80;
        let saturate = (s / 100) * 3 + 0.8;
        if (isReddish)   saturate = (s / 100) * 6 + 2.0;
        if (isYellowish) saturate = (s / 100) * 4 + 1.0;

        let brightness;
        if (l < 30) brightness = 0.3 + (l / 100) * 0.7;
        else if (l < 50) brightness = 0.5 + (l / 100) * 0.6;
        else brightness = 0.6 + (l / 100) * 0.5;
        if (isYellowish && l >= 45) brightness = Math.min(brightness * 1.25, 1.5);

        return `grayscale(1) sepia(1) saturate(${saturate}) hue-rotate(${hueRotate}deg) brightness(${brightness})`;
    }
    
    // Generates preview images for each view using Canvas API
    // options: { colorHex: string, cartItemId: number|null }
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

        // Read the editor mockup image dimensions NOW (before any async work or modal transitions).
        // Element coordinates (el.x/y/width/height) are stored in CSS pixels relative to the
        // design area, which itself is da_*% of the mockup image's rendered dimensions.
        // The correct scale is simply: canvas_image_size / editor_image_size.
        const editorMockupImg = document.getElementById('mockupProduct');
        const editorImgW = editorMockupImg ? editorMockupImg.offsetWidth  : 0;
        const editorImgH = editorMockupImg ? editorMockupImg.offsetHeight : 0;
        
        // Prefer the shared elements exposed by shop_custom.js (JS handlers fire first and populate it)
        const _els = window.designElements || elements;

        for (const view of views) {
            if (!_els[view] || _els[view].length === 0) continue;

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
                
                // Fill background (white, matching the mockup container)
                ctx.fillStyle = '#ffffff';
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
                
                // Calculate design area position on canvas using the same da_* values
                // as applyDesignArea() in the editor — percentages of the product image.
                const p = window.currentProduct;
                const daDefaults = {
                    'front':        { x: 27.5, y: 25,   w: 45,   h: 60   },
                    'back':         { x: 27.5, y: 25,   w: 45,   h: 60   },
                    'left-sleeve':  { x: 46,   y: 27,   w: 13,   h: 16   },
                    'right-sleeve': { x: 46,   y: 27,   w: 13,   h: 16   },
                };
                const daMap = {
                    'front':        { x: p.da_front_x,   y: p.da_front_y,   w: p.da_front_w,   h: p.da_front_h   },
                    'back':         { x: p.da_back_x,    y: p.da_back_y,    w: p.da_back_w,    h: p.da_back_h    },
                    'left-sleeve':  { x: p.da_lsleeve_x, y: p.da_lsleeve_y, w: p.da_lsleeve_w, h: p.da_lsleeve_h },
                    'right-sleeve': { x: p.da_rsleeve_x, y: p.da_rsleeve_y, w: p.da_rsleeve_w, h: p.da_rsleeve_h },
                };
                const def = daDefaults[view] || daDefaults['front'];
                const dm  = daMap[view]      || {};
                const daXpct = (dm.x != null ? dm.x : def.x);
                const daYpct = (dm.y != null ? dm.y : def.y);
                const daWpct = (dm.w != null ? dm.w : def.w);
                const daHpct = (dm.h != null ? dm.h : def.h);

                // Canvas design area (pixels on the 800px canvas)
                const daLeft   = imgX + (daXpct / 100) * imgW;
                const daTop    = imgY + (daYpct / 100) * imgH;
                const daWidth  = (daWpct / 100) * imgW;
                const daHeight = (daHpct / 100) * imgH;

                // Editor design area for THIS view (pixels in the browser).
                // applyDesignArea() sets: designArea.style.width = (da_w% * mockupImg.offsetWidth)
                // So the correct per-view editor DA width = da_*% * editorImgW.
                // Elements are stored in this space regardless of which view is currently displayed.
                const editorDAWidth  = (daWpct / 100) * (editorImgW || 1);
                const editorDAHeight = (daHpct / 100) * (editorImgH || 1);

                // Scale: canvas_image_px / editor_image_px  (same ratio for both axes for square images)
                const scaleX = editorImgW > 0 ? imgW / editorImgW : 1;
                const scaleY = editorImgH > 0 ? imgH / editorImgH : 1;
                
                // Clip to design area (elements shouldn't overflow)
                ctx.save();
                ctx.beginPath();
                ctx.rect(daLeft, daTop, daWidth, daHeight);
                ctx.clip();
                
                // Sort elements by layer order
                const sortedElements = [..._els[view]].sort((a, b) => {
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
                            
                            ctx.globalAlpha = 1;
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

                // Design-only transparent PNG (front view only) — used for color-swap overlay in cart modal
                if (view === 'front') {
                    const dCanvas = document.createElement('canvas');
                    dCanvas.width = canvasSize;
                    dCanvas.height = canvasSize;
                    const dCtx = dCanvas.getContext('2d');
                    // No background fill — transparent by default
                    dCtx.save();
                    dCtx.beginPath();
                    dCtx.rect(daLeft, daTop, daWidth, daHeight);
                    dCtx.clip();
                    for (const el of sortedElements) {
                        const elX = daLeft + (el.x || 0) * scaleX;
                        const elY = daTop  + (el.y || 0) * scaleY;
                        if (el.type === 'image' && el.src) {
                            try {
                                const elImg = await loadImageAsync(
                                    el.src.startsWith('data:') || el.src.startsWith('http') || el.src.startsWith('/')
                                        ? el.src : '/' + el.src
                                );
                                const elW = (el.width  || 80) * scaleX;
                                const elH = (el.height || 80) * scaleY;
                                dCtx.save();
                                if (el.rotation) {
                                    dCtx.translate(elX + elW/2, elY + elH/2);
                                    dCtx.rotate((el.rotation * Math.PI) / 180);
                                    dCtx.translate(-(elX + elW/2), -(elY + elH/2));
                                }
                                if (el.flipped) {
                                    dCtx.translate(elX + elW, 0);
                                    dCtx.scale(-1, 1);
                                    dCtx.translate(-elX, 0);
                                }
                                dCtx.drawImage(elImg, elX, elY, elW, elH);
                                if (el.color && el.color !== '#ffffff' && el.color !== '#fff') {
                                    dCtx.globalCompositeOperation = 'multiply';
                                    dCtx.globalAlpha = 0.35;
                                    dCtx.fillStyle = el.color;
                                    dCtx.fillRect(elX, elY, elW, elH);
                                    dCtx.globalCompositeOperation = 'source-over';
                                    dCtx.globalAlpha = 1;
                                }
                                dCtx.restore();
                            } catch(e) {}
                        } else if (el.type === 'text' && el.text) {
                            dCtx.save();
                            const fontSize = Math.max(8, (el.fontSize || 24) * scaleX);
                            dCtx.font = `${el.italic?'italic':'normal'} ${el.bold?'bold':'normal'} ${fontSize}px ${el.fontFamily||'Arial, sans-serif'}`;
                            dCtx.fillStyle = el.color || '#000000';
                            dCtx.textBaseline = 'top';
                            if (el.rotation) {
                                const m = dCtx.measureText(el.text);
                                dCtx.translate(elX + m.width/2, elY + fontSize/2);
                                dCtx.rotate((el.rotation * Math.PI) / 180);
                                dCtx.translate(-(elX + m.width/2), -(elY + fontSize/2));
                            }
                            dCtx.fillText(el.text, elX, elY);
                            if (el.underline) {
                                const m = dCtx.measureText(el.text);
                                dCtx.beginPath();
                                dCtx.strokeStyle = el.color || '#000000';
                                dCtx.lineWidth = Math.max(1, fontSize/15);
                                dCtx.moveTo(elX, elY + fontSize + 2);
                                dCtx.lineTo(elX + m.width, elY + fontSize + 2);
                                dCtx.stroke();
                            }
                            dCtx.restore();
                        }
                    }
                    dCtx.restore();
                    previews['front_design'] = dCanvas.toDataURL('image/png');
                }
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
        
        const _editorDA_upd = document.getElementById('designArea');
        const designData = {
            design_id: window.loadedDesignId,
            email: window.loadedDesignEmail || '',
            product_id: window.currentProduct.id,
            elements: allElements,
            color_hex: currentColorHex,
            editorDAWidth:  _editorDA_upd ? _editorDA_upd.offsetWidth  : 225,
            editorDAHeight: _editorDA_upd ? _editorDA_upd.offsetHeight : 300
        };
        
        this.disabled = true;
        this.textContent = 'Updating...';
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/custom-design/update', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                document.getElementById('updateDesignBtn').disabled = false;
                document.getElementById('updateDesignBtn').textContent = 'Update Design';
                let parsed = null;
                try { parsed = JSON.parse(xhr.responseText); } catch(e){}
                if (parsed && parsed.requireLogin) {
                    document.getElementById('saveLoginNotice').style.display = 'block';
                    document.getElementById('saveNewMode').style.display = 'none';
                    document.getElementById('saveEditingMode').style.display = 'none';
                    return;
                }
                if (parsed && parsed.id) {
                    window.savedDesignId = parsed.id;
                    closeSaveDesignModal();
                    document.getElementById('designSavedModal').style.display = 'flex';
                    generateAndSavePreviews(parsed.id);
                } else {
                    alert('Session expired. Please log in and try again.');
                }
            }
        };
        xhr.send(JSON.stringify(designData));
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
                let parsed = null;
                try { parsed = JSON.parse(xhr.responseText); } catch(e){}
                if (parsed && parsed.requireLogin) {
                    window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
                    return;
                }
                if (parsed && parsed.success) {
                    window.location.href = '/account';
                } else {
                    alert('Failed to delete design. Please try again.');
                }
            }
        };
        xhr.send(JSON.stringify({ design_id: window.loadedDesignId }));
    });

    // Save as New Design button handler
    document.getElementById('saveDesignModalBtn').addEventListener('click', function() {
        if (this.disabled) return;
        const btn = this;
        btn.disabled = true;
        btn.textContent = 'Saving...';

        let allElements = [];
        Object.keys(elements).forEach(view => {
            allElements = allElements.concat(elements[view].map(el => ({...el, view})));
        });
        const name = document.getElementById('saveDesignName').value.trim();
        const email = document.getElementById('saveDesignEmail').value.trim();
        const privacyChecked = document.getElementById('saveDesignPrivacy').checked;
        if (!name || !email || !privacyChecked) { btn.disabled = false; btn.textContent = window.I18N.t('studio.save_modal.save_new'); return; }
        if (!window.currentProduct) {
            alert(window.I18N.t('studio.not_saved'));
            btn.disabled = false; btn.textContent = window.I18N.t('studio.save_modal.save_new'); return;
        }
        const _editorDA_el = document.getElementById('designArea');
        const designData = {
            name: name, email: email,
            product_id: window.currentProduct.id,
            size_id: null, color_id: null,
            elements: allElements,
            color_hex: currentColorHex,
            editorDAWidth:  _editorDA_el ? _editorDA_el.offsetWidth  : 225,
            editorDAHeight: _editorDA_el ? _editorDA_el.offsetHeight : 300
        };

        (async () => {
            try {
                const resp = await fetch('/custom-design/save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(designData)
                });
                const parsed = await resp.json();

                if (parsed && parsed.requireLogin) {
                    // Capture front preview while editor is still loaded, then redirect
                    let frontPreview = null;
                    if (typeof captureCurrentFrontPreview === 'function') {
                        frontPreview = await captureCurrentFrontPreview();
                    }
                    const payload = { designData: designData, frontPreview: frontPreview };
                    let stored = false;
                    try { sessionStorage.setItem('pendingDesignSave', JSON.stringify(payload)); stored = true; } catch(e) {}
                    if (stored) {
                        closeSaveDesignModal();
                        window.location.href = '/login?redirect=/shop/custom';
                    } else {
                        document.getElementById('saveLoginNotice').style.display = 'block';
                        document.getElementById('saveNewMode').style.display = 'none';
                        document.getElementById('saveEditingMode').style.display = 'none';
                        btn.disabled = false; btn.textContent = window.I18N.t('studio.save_modal.save_new');
                    }
                    return;
                }

                if (parsed && parsed.id) {
                    window.savedDesignId = parsed.id;
                    window.loadedDesignId = null;
                    window.loadedDesignName = null;
                    btn.textContent = window.I18N.t('studio.cart.adding');
                    await generateAndSavePreviews(parsed.id);
                    closeSaveDesignModal();
                    document.getElementById('designSavedModal').style.display = 'flex';
                } else {
                    alert(window.I18N.t('checkout.errors.generic'));
                    btn.disabled = false; btn.textContent = window.I18N.t('studio.save_modal.save_new');
                }
            } catch(e) {
                alert(window.I18N.t('studio.cart.error_generic'));
                btn.disabled = false; btn.textContent = window.I18N.t('studio.save_modal.save_new');
            }
        })();
    });

    // Add to Cart button handler - opens Add to Cart modal with size/color selection
    document.getElementById('addToCartNowBtn').addEventListener('click', function() {
        if (!window.savedDesignId) {
            alert(window.I18N.t('studio.not_saved'));
            return;
        }
        
        // Calculate design fee
        const frontCount = (typeof elements !== 'undefined' && elements['front']) ? elements['front'].length : 0;
        const backCount = (typeof elements !== 'undefined' && elements['back']) ? elements['back'].length : 0;
        const leftSleeveCount = (typeof elements !== 'undefined' && elements['left-sleeve']) ? elements['left-sleeve'].length : 0;
        const rightSleeveCount = (typeof elements !== 'undefined' && elements['right-sleeve']) ? elements['right-sleeve'].length : 0;
        // Raw pre-margin print add-ons (front+back = €3, each sleeve = €1). These
        // are marked up through the margin in updateCartPrices().
        let designFee = 0;
        if (frontCount > 0 && backCount > 0) {
            designFee += 3.0;
        }
        if (leftSleeveCount > 0) {
            designFee += 1.0;
        }
        if (rightSleeveCount > 0) {
            designFee += 1.0;
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
</script>
    </div>
</div>
<!-- Move Image Editor Panel inside Whats Next panel -->
                
                        
                    <!-- ...rest of whats-next-panel... -->

                <style>
                .img-edit-btn {
                    background: #f7f7f7;
                    border: 1px solid #ddd;
                    border-radius: 6px;
                    padding: 6px 14px;
                    font-size: 14px;
                    cursor: pointer;
                    transition: background 0.15s, color 0.15s;
                }
                .img-edit-btn:hover {
                    background: #e6eaff;
                    color: #2d5fff;
                }
                </style>
                
                <!-- What's next for you? Panel will be moved to the correct location below -->
<style>
/* Modern Upload Modal Buttons */
.upload-editor-close {
    position: absolute;
    top: 0.4rem;
    right: 0.4rem;
    background: #f3f6fa;
    border: none;
    border-radius: 50%;
    width: 38px;
    height: 38px;
    font-size: 1.7rem;
    color: #15130E;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(102,126,234,0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s, color 0.15s;
}
.upload-editor-close:hover {
    background: #e0e7ff;
    color: #3346d3;
}
.upload-browse-btn {
    background: var(--ink);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 0.6rem 1.4rem;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    margin-bottom: 0.6rem;
    box-shadow: 0 2px 8px rgba(102,126,234,0.08);
    letter-spacing: 0.01em;
    transition: background 0.15s, box-shadow 0.15s, transform 0.12s;
    width: 100%;
}
.upload-browse-btn:hover {
    background: var(--ink);
    box-shadow: 0 4px 16px rgba(102,126,234,0.13);
    transform: translateY(-1px);
}
.upload-recent-list {
    display: flex;
    flex-direction: row;
    gap: 0.5rem;
    overflow-x: auto;
    overflow-y: hidden;
    padding: 0.3rem 0 0.4rem 0;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    scrollbar-width: thin;
    scrollbar-color: #c7d2fe transparent;
}
.upload-recent-list::-webkit-scrollbar { height: 5px; }
.upload-recent-list::-webkit-scrollbar-thumb { background: #c7d2fe; border-radius: 3px; }
.upload-recent-item {
    flex-shrink: 0;
    width: 52px;
    height: 52px;
    border-radius: 7px;
    border: 2px solid #e5e7ff;
    object-fit: cover;
    cursor: pointer;
    transition: border-color 0.15s, transform 0.12s;
}
.upload-recent-item:hover {
    border-color: #15130E;
    transform: scale(1.07);
}
.whats-next-panel {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    padding: 1.2rem 1rem 1rem 1rem;
    margin-top: 1rem;
    text-align: center;
    min-width: 0;
    width: 100%;
    box-sizing: border-box;
}
.upload-editor-modal {
    width: 100%;
    box-sizing: border-box;
    overflow: hidden;
}
.upload-editor-content {
    width: 100%;
    box-sizing: border-box;
    overflow: hidden;
    position: relative;
}
.whats-next-title {
    font-size: 1.3rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: #222;
}
.whats-next-actions {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.8rem;
    margin-bottom: 1rem;
    margin-top: 1rem;
}
.whats-next-action {
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    transition: transform 0.12s, box-shadow 0.12s;
    min-width: 100px;
    padding: 0.7rem 0.5rem 0.5rem 0.5rem;
    border-radius: 10px;
    background: #f8fafd;
    box-shadow: 0 1px 4px rgba(102,126,234,0.04);
    margin-bottom: 0;
}
.whats-next-action:hover {
    background: #f0f4ff;
    transform: translateY(-2px) scale(1.04);
    box-shadow: 0 4px 16px rgba(102,126,234,0.10);
}
.whats-next-icon {
    margin-bottom: 0.3rem;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 36px;
    width: 36px;
}
.whats-next-icon svg {
    width: 36px;
    height: 36px;
}
.whats-next-label {
    font-size: 0.95rem;
    color: #334;
    font-weight: 500;
    margin-top: 0.1rem;
    letter-spacing: 0.01em;
}
.whats-next-upload-hint {
    margin-top: 1rem;
    background: #f8fafd;
    border-radius: 8px;
    padding: 0.8rem 0.8rem;
    display: inline-block;
    text-align: left;
    font-size: 0.9rem;
}
.hint-title {
    font-weight: 600;
    margin-bottom: 0.4rem;
    color: #222;
    font-size: 0.95rem;
}
.hint-row {
    font-size: 0.85rem;
    margin-bottom: 0.15rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.hint-icon {
    font-size: 1rem;
}
</style>
<?php $title = t('studio.title', false); ?>
<?php require __DIR__ . '/../layouts/customer_header.php'; ?>

<!-- Interact.js for drag & resize -->
<script src="https://cdn.jsdelivr.net/npm/interactjs/dist/interact.min.js"></script>

<section class="section custom-design-section">
    <div class="container">
        <nav class="breadcrumb">
            <a href="/">Home</a> &gt;
            <a href="/shop">Shop</a> &gt;
            <span><?= t('studio.title') ?></span>
        </nav>

        <div class="shop-header">
            <h1><?= t('studio.title') ?></h1>
            <p class="shop-subtitle"><?= t('studio.subtitle') ?></p>
        </div>

        <?php if (empty($products)): ?>
            <div class="no-products-message">
                <p><?= t('studio.no_products') ?></p>
            </div>
        <?php else: ?>

        <div class="custom-studio-layout">
            <!-- Preview Area (visually on right via CSS order) -->
            <div class="studio-preview">
                <!-- View Toggle (Front/Back/Sleeves) -->
                <div class="view-toggle" id="viewToggle">
                    <button type="button" class="view-btn active" data-view="front"><?= t('studio.view.front') ?></button>
                    <button type="button" class="view-btn" data-view="back"><?= t('studio.view.back') ?></button>
                    <button type="button" class="view-btn" data-view="left-sleeve" id="leftSleeveBtn" style="display: none;"><?= t('studio.view.left_sleeve') ?></button>
                    <button type="button" class="view-btn" data-view="right-sleeve" id="rightSleeveBtn" style="display: none;"><?= t('studio.view.right_sleeve') ?></button>
                </div>

                <div class="mockup-container" id="mockupContainer">
                    <!-- Product image -->
                    <img src="" alt="Product" class="mockup-product" id="mockupProduct">
                    <div class="mockup-placeholder" id="mockupPlaceholder"><?= t('studio.placeholder') ?></div>
                    
                    <!-- Design area -->
                    <div class="design-area" id="designArea">
                        <div class="design-area-label" id="designAreaLabel"><?= t('studio.design_area') ?></div>
                        <!-- Design elements will be added here dynamically -->
                    </div>
                </div>

                <!-- Product Color & Size Selection -->
                <div class="product-options-panel" id="productOptionsPanel" style="display:none; background:#fff; border-radius:12px; padding:1rem; margin-top:1rem; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
                    <h4 style="margin:0 0 0.75rem 0; font-size:1rem; color:#333;"><?= t('studio.panel.color') ?></h4>
                    <div id="studioColorSwatches" style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:1rem;"></div>

                    <h4 style="margin:0 0 0.5rem 0; font-size:1rem; color:#333;"><?= t('studio.panel.sizes') ?></h4>
                    <div id="studioAvailableSizes" style="display:flex; flex-wrap:wrap; gap:6px;">
                        <span style="color:#888; font-size:0.9rem;"><?= t('studio.panel.select_color_first') ?></span>
                    </div>
                </div>

                <!-- Layer Controls -->
                <div class="layer-controls">
                    <h4><?= t('studio.layers.title') ?></h4>
                    <div class="layer-list" id="layerList">
                        <div class="layer-empty"><?= t('studio.layers.empty') ?></div>
                    </div>
                </div>
            </div>

            <!-- Controls Panel (visually on left via CSS order) -->
            <div class="studio-controls">
               
               

                <!-- Move Text Editor Modal inside Whats Next panel -->
                <div class="whats-next-panel">
                    <!-- Upload Editor Modal -->
                    <div id="uploadEditorModal" class="upload-editor-modal" style="display:none;">
                        <div class="upload-editor-content">
                            <button class="upload-editor-close" onclick="closeUploadEditor()">&times;</button>
                            <h2 style="font-size:1.1rem;margin-bottom:0.7rem;padding-right:2.5rem;"><?= t('studio.upload.title') ?></h2>
                            <div id="uploadDropArea" class="upload-drop-area" style="padding:0.7rem;" ondrop="handleUploadDrop(event)" ondragover="event.preventDefault();this.classList.add('dragover');" ondragleave="this.classList.remove('dragover');">
                                <button type="button" class="upload-browse-btn" onclick="document.getElementById('uploadFileInput').click()"><?= t('studio.upload.browse') ?></button>
                                <div class="upload-or" style="font-size:0.85rem;margin:0.3rem 0;"><?= t('studio.upload.drag', false) ?></div>
                                <input type="file" id="uploadFileInput" accept="image/*" style="display:none;" onchange="handleUploadFile(this.files)">
                            </div>
                            <div class="upload-hint" style="font-size:0.78rem;color:#999;margin-top:0.4rem;text-align:center;"><?= t('studio.upload.hint') ?></div>
                            <div id="uploadRecentList" style="display:none;margin-top:0.7rem;width:100%;min-width:0;box-sizing:border-box;">
                                <div style="font-size:0.8rem;font-weight:600;color:#555;margin-bottom:0.3rem;"><?= t('studio.upload.recent') ?></div>
                                <div class="upload-recent-list" id="uploadRecentStrip"></div>
                            </div>
                        </div>
                    </div>
                    <!-- Text Editor Modal (styled like upload) -->
                    <div id="textEditorModal" class="upload-editor-modal" style="display:none;">
                        <div class="upload-editor-content">
                            <button class="upload-editor-close" onclick="hideTextEditor()">&times;</button>
                            <h2 id="textEditorTitle" style="margin-bottom:18px;"><?= t('studio.text.title') ?></h2>
                            <div class="option-group" style="margin-bottom:14px;">
                                <label for="textContent" style="font-weight:500;"><?= t('studio.text.label') ?>:</label>
                                <input type="text" id="textContent" placeholder="<?= t('studio.text.placeholder') ?>" maxlength="50" style="width:100%;margin-top:6px;">
                            </div>
                            <div class="option-group" style="margin-bottom:14px;">
                                <label for="fontFamily" style="font-weight:500;"><?= t('studio.text.font') ?>:</label>
                                <select id="fontFamily" style="width:100%;margin-top:6px;">
                                    <option value="Arial, sans-serif" selected>Arial</option>
                                    <option value="'Times New Roman', serif">Times New Roman</option>
                                    <option value="'Courier New', monospace">Courier New</option>
                                    <option value="Georgia, serif">Georgia</option>
                                    <option value="Verdana, sans-serif">Verdana</option>
                                    <option value="'Trebuchet MS', sans-serif">Trebuchet MS</option>
                                    <option value="Impact, sans-serif">Impact</option>
                                    <option value="'Lucida Console', monospace">Lucida Console</option>
                                </select>
                            </div>
                            <div style="display:flex; gap:16px; margin-bottom:14px;">
                                <div style="flex:1;">
                                    <label for="fontSize" style="font-weight:500;"><?= t('studio.text.size') ?>:</label>
                                    <input type="range" id="fontSize" min="12" max="200" value="24" style="width:100%;margin-top:6px;">
                                    <span id="fontSizeDisplay">24px</span>
                                </div>
                                <div style="flex:1;">
                                    <label for="textColor" style="font-weight:500;"><?= t('studio.text.color') ?>:</label>
                                    <input type="color" id="textColor" value="#000000" style="width:100%;margin-top:6px;">
                                </div>
                            </div>
                            <div class="option-group" style="margin-bottom:14px;">
                                <label style="font-weight:500;"><?= t('studio.text.style') ?>:</label>
                                <div class="style-buttons" style="margin-top:6px;display:flex;gap:10px;">
                                    <button type="button" class="img-edit-btn" id="boldBtn" title="Bold"><b>B</b></button>
                                    <button type="button" class="img-edit-btn" id="italicBtn" title="Italic"><i>/</i></button>
                                    <button type="button" class="img-edit-btn" id="underlineBtn" title="Underline"><u>U</u></button>
                                </div>
                            </div>
                            <div style="display:flex; gap:10px; margin-top:18px;">
                                <button type="button" class="img-edit-btn" style="flex:1; background:#2d5fff; color:#fff;" id="applyTextBtn"><?= t('studio.text.apply') ?></button>
                                <button type="button" class="img-edit-btn" style="flex:1; background:#eee; color:#888;" id="cancelTextBtn"><?= t('studio.text.cancel') ?></button>
                            </div>
                        </div>
                    </div>
                    <h2 class="whats-next-title"><?= t('studio.whats_next') ?></h2>
                    <div class="whats-next-actions">
                        <div id="imageEditorPanel" style="display:none;">
                            <div class="upload-editor-content">
                                <button class="upload-editor-close" onclick="hideImageEditor()">&times;</button>
                                <h2 style="margin-bottom:18px;"><?= t('studio.image_edit.title') ?></h2>
                                <div style="margin-bottom:12px;">
                                    <div style="font-size:13px; color:#888;"><?= t('studio.image_edit.size') ?></div>
                                    <div style="display:flex; gap:8px; align-items:center; margin-top:2px;">
                                        <input id="imgEditWidth" type="number" min="0.1" step="0.01" style="width:60px;" onchange="updateImageSize('width')"> in ×
                                        <input id="imgEditHeight" type="number" min="0.1" step="0.01" style="width:60px;" onchange="updateImageSize('height')"> in
                                    </div>
                                </div>
                                <div style="margin-bottom:10px; display:flex; align-items:center; gap:10px;">
                                    <label style="font-size:13px;"><?= t('studio.image_edit.color') ?></label>
                                    <input id="imgEditColor" type="color" onchange="updateImageColor()">
                                </div>
                                <div style="margin-bottom:10px; display:flex; align-items:center; gap:10px;">
                                    <!-- Background remover removed for customers -->
                                </div>
                                <script>
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
// --- Utility functions moved to global scope ---
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
                                </script>
                                <hr style="margin:12px 0;">
                                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:10px;">
                                    <button class="img-edit-btn" onclick="centerImage()"><?= t('studio.image_edit.center') ?></button>
                                    <button class="img-edit-btn" onclick="layerImage('up')"><?= t('studio.image_edit.layer') ?></button>
                                    <button class="img-edit-btn" onclick="flipImage()"><?= t('studio.image_edit.flip') ?></button>
                                    <button class="img-edit-btn" onclick="duplicateImage()"><?= t('studio.image_edit.duplicate') ?></button>
                                    <button class="img-edit-btn" onclick="cropImage()"><?= t('studio.image_edit.crop') ?></button>
                                </div>
                                <!-- Save Design button moved to main panel below -->
                                <div style="margin-bottom:10px;">
                                    <label style="font-size:13px;"><?= t('studio.image_edit.rotation') ?></label>
                                    <input id="imgEditRotation" type="range" min="0" max="360" value="0" style="width:140px; vertical-align:middle;" oninput="updateImageRotation()">
                                    <input id="imgEditRotationVal" type="number" min="0" max="360" value="0" style="width:48px;" oninput="updateImageRotation()">
                                </div>
                                <div style="display:flex; gap:10px; margin-top:10px;">
                                    <button class="img-edit-btn" style="flex:1; background:#eee; color:#888;" onclick="resetImageEdit()"><?= t('studio.image_edit.reset') ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                        <div class="whats-next-action" onclick="openUploadEditor()">
                            <div class="whats-next-icon">
                                <!-- Upload Icon -->
                                <svg width="48" height="48" fill="none" stroke="#15130E" stroke-width="2" viewBox="0 0 48 48"><path d="M24 34V14M24 14l-8 8M24 14l8 8"/><rect x="8" y="36" width="32" height="6" rx="3"/></svg>
                            </div>
                            <div class="whats-next-label"><?= t('studio.action.uploads') ?></div>
                        </div>
                        <div class="whats-next-action" onclick="triggerWhatsNextAddText()">
                            <!-- Hidden input for uploads (for Whats Next panel) -->
                            <div class="whats-next-icon">
                                <!-- Text Icon -->
                                <svg width="48" height="48" fill="none" stroke="#15130E" stroke-width="2" viewBox="0 0 48 48"><text x="8" y="36" font-size="28" font-family="Arial" fill="#15130E">abc</text></svg>
                            </div>
                            <div class="whats-next-label"><?= t('studio.action.add_text') ?></div>
                        </div>
                        <div class="whats-next-action" onclick="openChangeColorModal()">
                            <div class="whats-next-icon">
                                <!-- Palette Icon -->
                                <svg width="48" height="48" fill="none" stroke="#15130E" stroke-width="2" viewBox="0 0 48 48"><circle cx="24" cy="24" r="20" fill="#f8fafd" stroke="#15130E"/><circle cx="16" cy="20" r="3" fill="#15130E"/><circle cx="32" cy="20" r="3" fill="#15130E"/><circle cx="24" cy="32" r="3" fill="#15130E"/></svg>
                            </div>
                            <div class="whats-next-label"><?= t('studio.action.change_color') ?></div>
                        </div>
                        <div class="whats-next-action" onclick="window.location.href='/shop/select_product'">
                            <div class="whats-next-icon">
                                <!-- Change Products Icon -->
                                <svg width="48" height="48" fill="none" stroke="#15130E" stroke-width="2" viewBox="0 0 48 48"><rect x="10" y="16" width="28" height="20" rx="4"/><path d="M14 16V12a4 4 0 014-4h12a4 4 0 014 4v4"/><circle cx="24" cy="26" r="4"/></svg>
                            </div>
                            <div class="whats-next-label"><?= t('studio.action.change_product') ?></div>
                        </div>
                    </div>
                    <!-- Save Design Button -->
                    <div style="display:flex; justify-content:center; margin-top:18px; margin-bottom:18px;">
                        <button id="saveDesignBtn" class="img-edit-btn" style="background:#2d5fff; color:#fff; font-weight:600; font-size:16px; padding:10px 32px; border-radius:8px;" onclick="openSaveDesignModal()"><?= t('studio.save_design') ?></button>
                    </div>
                    <!-- Change Color Modal -->
                    <div id="changeColorModal" class="change-color-modal" style="display:none;" onclick="if(event.target === this) closeChangeColorModal();">
                        <div class="change-color-modal-content" onclick="event.stopPropagation();">
                            <button class="change-color-modal-close" onclick="closeChangeColorModal()">&times;</button>
                            <h2><?= t('studio.color_modal.title') ?></h2>
                            <div id="changeColorOptions"></div>
                        </div>
                    </div>
                    
                    <script>
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
                            saveModalTitle.textContent = window.I18N.t('studio.save_modal.title_edit');
                            saveBtn.textContent = window.I18N.t('studio.save_modal.save_new');
                        } else {
                            // Hide editing mode - show only new save
                            editingModeDiv.style.display = 'none';
                            saveModalTitle.textContent = window.I18N.t('studio.save_modal.title');
                            saveBtn.textContent = window.I18N.t('studio.save_modal.save');
                        }
                        
                        document.getElementById('saveDesignModal').style.display = 'flex';
                    }
                    function closeSaveDesignModal() {
                        document.getElementById('saveDesignModal').style.display = 'none';
                        // Reset login notice so it's hidden next time
                        const notice = document.getElementById('saveLoginNotice');
                        if (notice) notice.style.display = 'none';
                        const saveNewMode = document.getElementById('saveNewMode');
                        if (saveNewMode) saveNewMode.style.display = '';
                    }
                    
                    // --- Upload Editor Modal Logic ---
                    function openUploadEditor() {
                        hideTextEditor();
                        hideImageEditor();
                        closeChangeColorModal();
                        document.getElementById('uploadEditorModal').style.display = 'flex';
                    }
                    function closeUploadEditor() {
                        document.getElementById('uploadEditorModal').style.display = 'none';
                    }
                    function handleUploadFile(files) {
                        if (!files || !files.length) return;
                        const file = files[0];
                        if (!file.type.startsWith('image/')) {
                            alert(window.I18N.t('studio.error.image_only'));
                            return;
                        }
                        if (file.size > 20 * 1024 * 1024) {
                            alert(window.I18N.t('studio.error.file_too_large'));
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
                    function _uploadsKey() {
                        return currentUserId ? 'recentUploads_' + currentUserId : null;
                    }
                    function saveRecentUpload(dataUrl) {
                        const key = _uploadsKey();
                        if (!key) return; // guests: don't persist uploads
                        let recents = JSON.parse(localStorage.getItem(key) || '[]');
                        // Deduplicate — remove existing copy, add to front
                        recents = recents.filter(u => u !== dataUrl);
                        recents.unshift(dataUrl);
                        if (recents.length > 20) recents = recents.slice(0, 20);
                        localStorage.setItem(key, JSON.stringify(recents));
                        renderRecentUploads();
                    }
                    function renderRecentUploads() {
                        const key = _uploadsKey();
                        let recents = key ? JSON.parse(localStorage.getItem(key) || '[]') : [];
                        const wrapper = document.getElementById('uploadRecentList');
                        const strip = document.getElementById('uploadRecentStrip');
                        if (!wrapper || !strip) return;
                        if (!recents.length) { wrapper.style.display = 'none'; return; }
                        wrapper.style.display = '';
                        strip.innerHTML = '';
                        recents.forEach(url => {
                            const img = document.createElement('img');
                            img.src = url;
                            img.className = 'upload-recent-item';
                            img.title = 'Click to add to design';
                            img.addEventListener('click', () => {
                                addImageElement(url);
                                closeUploadEditor();
                            });
                            strip.appendChild(img);
                        });
                    }
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
                        hideImageEditor();
                        closeChangeColorModal();
                        showTextEditor();
                    }
                    
                    // --- Change Color Modal Logic ---
                    function openChangeColorModal() {
                        closeUploadEditor();
                        hideTextEditor();
                        hideImageEditor();
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
                    </script>
                    <style>
                    .change-color-modal {
                        position: fixed;
                        z-index: 30000;
                        left: 0;
                        top: 0;
                        width: 100vw;
                        height: 100vh;
                        background: rgba(0,0,0,0.3);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    .change-color-modal-content {
                        background: #fff;
                        border-radius: 16px;
                        padding: 1.5rem;
                        max-width: 400px;
                        width: 90vw;
                        position: relative;
                        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                    }
                    .change-color-modal-close {
                        position: absolute;
                        top: 0.8rem;
                        right: 0.8rem;
                        background: none;
                        border: none;
                        font-size: 1.8rem;
                        color: #888;
                        cursor: pointer;
                    }
                    .modal-color-swatches {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 0.4rem;
                        margin-top: 0.5rem;
                    }
                    .modal-color-swatch {
                        width: 28px; height: 28px;
                        border-radius: 6px;
                        border: 2px solid #eee;
                        cursor: pointer;
                        position: relative;
                        box-sizing: border-box;
                        display: flex; align-items: center; justify-content: center;
                    }
                    .modal-color-selected {
                        border: 2.5px solid #15130E;
                        box-shadow: 0 0 0 2px #e0e7ff;
                    }
                    .modal-color-check {
                        color: #fff;
                        font-size: 1.2rem;
                        background: #15130E;
                        border-radius: 50%;
                        width: 18px; height: 18px;
                        display: flex; align-items: center; justify-content: center;
                        position: absolute; right: -8px; top: -8px;
                        box-shadow: 0 1px 4px rgba(0,0,0,0.13);
                    }
                    .modal-size-list {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 0.5rem;
                        margin-top: 0.5rem;
                    }
                    .modal-size-item {
                        padding: 0.3rem 0.7rem;
                        border-radius: 6px;
                        border: 1.5px solid #eee;
                        background: #f8fafd;
                        font-size: 1rem;
                        cursor: pointer;
                        transition: background 0.13s, border 0.13s;
                    }
                    .modal-size-selected {
                        border: 2px solid #15130E;
                        background: #e0e7ff;
                        font-weight: 600;
                        color: #222;
                    }
                    </style>
                </div>
 
            </div>
        </div>

        <?php endif; ?>
    </div>
</section>

<style>
.custom-design-section {
    padding: 2rem 0;
}

.custom-studio-layout {
    display: grid;
    grid-template-columns: 220px 1fr;
    gap: 0.5rem;
    align-items: start;
}

/* Preview Area - appears on the right */
.studio-preview {
    order: 2;
    position: sticky;
    top: 100px;
    width: 100%;
    max-width: none;
    margin-top: -10px;
}

/* Controls Panel - appears on the left */
.studio-controls {
    order: 1;
    width: 220px;
    max-width: 220px;
}

.view-toggle {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.view-btn {
    padding: 0.5rem 1rem;
    border: 2px solid #ddd;
    background: #fff;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s;
}

.view-btn:hover {
    border-color: #15130E;
}

.view-btn.active {
    background: #15130E;
    color: #fff;
    border-color: #15130E;
}

.mockup-container {
    position: relative;
    background: #ffffff;
    border-radius: 12px;
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    transition: background-color 0.3s;
}

.mockup-container.dark-bg {
    background: #2a2a2a;
}

.mockup-product {
    max-width: 98%;
    max-height: 98%;
    object-fit: contain;
}

.mockup-placeholder {
    color: #999;
    font-size: 1.2rem;
}

.design-area {
    position: absolute;
    border: 2px dashed transparent;
    background: transparent;
    border-radius: 4px;
    overflow: hidden;
    transition: border-color 0.2s ease, background 0.2s ease;
}

.design-area.da-active {
    border-color: rgba(125, 128, 218, 0.5);
    background: rgba(125, 128, 218, 0.05);
}

.design-area-label {
    position: absolute;
    top: -22px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(125, 128, 218, 0.9);
    color: #fff;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    white-space: nowrap;
    opacity: 0;
    transition: opacity 0.2s ease;
    pointer-events: none;
}

.design-area.da-active .design-area-label {
    opacity: 1;
}

/* Design Elements */
.design-element {
    position: absolute;
    cursor: move;
    touch-action: none;
    user-select: none;
    -webkit-user-select: none;
}

.design-element img {
    max-width: 100%;
    max-height: 100%;
    pointer-events: none;
    user-select: none;
    -webkit-user-drag: none;
}

.design-element .text-content {
    white-space: nowrap;
    pointer-events: none;
    padding: 6px 10px;
    display: inline-block;
    min-width: 20px;
    min-height: 1em;
    border-radius: 3px;
}

.design-element.text-inline-editing .text-content {
    pointer-events: auto;
    cursor: text;
    outline: none;
    background: rgba(125, 128, 218, 0.07);
    border: 1px dashed rgba(125, 128, 218, 0.6);
}

.design-element.selected {
    outline: 2px solid #15130E;
    outline-offset: 2px;
}

.design-element .resize-handle {
    position: absolute;
    bottom: -6px;
    right: -6px;
    width: 12px;
    height: 12px;
    background: #15130E;
    border-radius: 50%;
    cursor: se-resize;
    display: none;
}

.design-element.selected .resize-handle {
    display: block;
}

.design-element .delete-btn {
    position: absolute;
    top: -10px;
    right: -10px;
    width: 20px;
    height: 20px;
    background: #dc3545;
    color: #fff;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    font-size: 12px;
    line-height: 1;
    display: none;
    align-items: center;
    justify-content: center;
}

.design-element .edit-btn {
    position: absolute;
    top: -10px;
    left: -10px;
    width: 20px;
    height: 20px;
    background: #15130E;
    color: #fff;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    font-size: 10px;
    line-height: 1;
    display: none;
    align-items: center;
    justify-content: center;
}

.design-element.selected .delete-btn,
.design-element.selected .edit-btn {
    display: flex;
}

/* Layer Controls */
.layer-controls {
    margin-top: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1rem;
}

.layer-controls h4 {
    margin: 0 0 0.5rem 0;
    font-size: 0.9rem;
    color: #666;
}

.layer-list {
    max-height: 150px;
    overflow-y: auto;
}

.layer-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem;
    background: #fff;
    border-radius: 4px;
    margin-bottom: 4px;
    cursor: pointer;
    font-size: 0.85rem;
}

.layer-item:hover {
    background: #e9ecef;
}

.layer-item.active {
    background: #15130E;
    color: #fff;
}

.layer-empty {
    color: #999;
    font-size: 0.85rem;
    text-align: center;
    padding: 1rem;
}

/* Control Panel */
.studio-controls {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.control-section {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    padding: 1.5rem;
}

.control-section h3 {
    margin: 0 0 1rem 0;
    font-size: 1rem;
    color: #333;
}

/* Product Grid */
.product-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}

.product-choice {
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    padding: 0.75rem;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}

.product-choice:hover {
    border-color: #15130E;
}

.product-choice.selected {
    border-color: #15130E;
    background: rgba(125, 128, 218, 0.05);
}

.product-choice-img {
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.5rem;
}

.product-choice-img img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.product-choice-img .no-img {
    color: #999;
    font-size: 0.8rem;
}

.product-choice-info .product-name {
    display: block;
    font-weight: 600;
    font-size: 0.85rem;
    margin-bottom: 2px;
}

.product-choice-info .product-price {
    color: #15130E;
    font-weight: 600;
    font-size: 0.9rem;
}

/* Size & Color Options */
.option-group {
    margin-bottom: 1rem;
}

.option-group label {
    display: block;
    font-weight: 500;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.size-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.size-btn {
    cursor: pointer;
}

.size-btn input {
    display: none;
}

.size-btn span {
    display: inline-block;
    padding: 0.5rem 1rem;
    border: 2px solid #ddd;
    border-radius: 6px;
    font-size: 0.85rem;
    transition: all 0.2s;
}

.size-btn input:checked + span {
    border-color: #15130E;
    background: #15130E;
    color: #fff;
}

.color-swatches {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.color-swatch {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 3px solid transparent;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: inset 0 0 0 1px rgba(0,0,0,0.1);
}

.color-swatch:hover {
    transform: scale(1.1);
}

.color-swatch.selected {
    border-color: #15130E;
}

.color-swatch.is-white {
    box-shadow: inset 0 0 0 2px #333;
}

/* Add Element Buttons */
.add-element-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}

.add-btn {
    padding: 1rem;
    border: 2px dashed #ddd;
    background: #f8f9fa;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
    font-weight: 500;
}

.add-btn:hover {
    border-color: #15130E;
    background: rgba(125, 128, 218, 0.05);
}

.add-btn .add-icon {
    display: block;
    font-size: 1.5rem;
    margin-bottom: 0.25rem;
}

/* Text Editor Panel */
.text-editor-panel {
    background: #f8f9fa;
    border-color: #15130E;
}

.option-row {
    display: flex;
    gap: 1rem;
}

.option-group.half {
    flex: 1;
}

.option-group input[type="text"],
.option-group select {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 0.9rem;
}

.option-group input[type="range"] {
    width: 100%;
}

.option-group input[type="color"] {
    width: 100%;
    height: 36px;
    border: 1px solid #ddd;
    border-radius: 6px;
    cursor: pointer;
}

.style-buttons {
    display: flex;
    gap: 0.5rem;
}

.style-btn {
    width: 36px;
    height: 36px;
    border: 1px solid #ddd;
    background: #fff;
    border-radius: 6px;
    cursor: pointer;
    font-size: 1rem;
}

.style-btn.active {
    background: #15130E;
    color: #fff;
    border-color: #15130E;
}

.text-editor-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}

/* Order Summary */
.order-summary {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    font-size: 0.9rem;
}

.summary-row.total {
    border-top: 2px solid #ddd;
    margin-top: 0.5rem;
    padding-top: 0.75rem;
    font-weight: 700;
    font-size: 1.1rem;
}

.quantity-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.quantity-control {
    display: flex;
    align-items: center;
    border: 1px solid #ddd;
    border-radius: 6px;
    overflow: hidden;
}

.qty-btn {
    width: 36px;
    height: 36px;
    border: none;
    background: #f8f9fa;
    cursor: pointer;
    font-size: 1.2rem;
}

.qty-btn:hover {
    background: #e9ecef;
}

.quantity-control input {
    width: 50px;
    height: 36px;
    border: none;
    text-align: center;
    font-weight: 600;
}

.btn-block {
    width: 100%;
}

.btn-lg {
    padding: 1rem 2rem;
    font-size: 1.1rem;
}

.no-options {
    color: #666;
    font-style: italic;
}

/* Responsive */
@media (max-width: 900px) {
    .custom-studio-layout {
        grid-template-columns: 1fr;
        }
    .studio-preview {
        position: relative;
        top: 0;
    }
}

/* Sleeve Design Areas - Forced positions */
.sleeve-area#designAreaLeftSleeve {
    left: 60% !important;
    top: 30% !important;
}
.sleeve-area#designAreaRightSleeve {
    left: 65% !important;
}
</style>

<script>
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

// Example: call startEditing on drag/resize start, finishEditing on apply
// You may need to hook these into your interact.js listeners:
// interact(div).on('dragstart', startEditing);
// interact(div).on('resizestart', startEditing);
// document.getElementById('applyChangesBtn').addEventListener('click', finishEditing);

// For text editing, call startEditing when user starts typing/editing
// and finishEditing when they click 'Apply Changes'
// State — `var` at script top-level becomes a window property, so the
// external studio_state.js can read/restore these.
window.currentProduct = null;
var currentView = 'front';
var currentColorHex = '#ffffff';
let editingTextElement = null;
var elements = {
    front: [],
    back: [],
    'left-sleeve': [],
    'right-sleeve': []
};
// Alias so persistence code that looks for window.designElements finds the same array refs.
window.designElements = elements;
let selectedElement = null;
var elementIdCounter = 0;
const CUSTOM_DESIGN_FEE = 5.00;

// Products data from PHP
const productsData = <?= json_encode($products) ?>;

// Current logged-in user ID (null when guest) — used to scope upload history
const currentUserId = <?= Auth::check() ? (int)Auth::userId() : 'null' ?>;

// Remove any legacy unscoped 'recentUploads' key that may contain another user's data
localStorage.removeItem('recentUploads');

// ── Pending design save replay ─────────────────────────────────────────────
// If the user was redirected to login while trying to save, the payload was
// stored in sessionStorage. Now that they're back and logged in, auto-save it
// and redirect to /account so they can see the saved design.
(async function replayPendingDesignSave() {
    if (!currentUserId) return; // still not logged in
    const raw = sessionStorage.getItem('pendingDesignSave');
    if (!raw) return;
    sessionStorage.removeItem('pendingDesignSave');

    let stored;
    try { stored = JSON.parse(raw); } catch(e) { return; }

    const designData   = stored.designData   || stored; // backwards compat
    const frontPreview = stored.frontPreview || null;

    try {
        const r = await fetch('/custom-design/save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(designData)
        });
        const data = await r.json();
        if (data.requireLogin || !data.id) return;
        const previews = frontPreview ? { front: frontPreview } : {};
        try {
            await fetch('/custom-design/save-previews', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ design_id: data.id, previews: previews })
            });
        } catch(e) {}
        window.location.href = '/account?tab=designs';
    } catch(e) {}
})();

// Design to load (if editing existing design)
const loadDesignData = <?= isset($loadDesign) && $loadDesign ? json_encode($loadDesign) : 'null' ?>;

// --- Apply Changes Button Logic ---
let lastAppliedItem = null;
// 'Apply Changes' button is always enabled
// Removed: applyChangesBtn logic

// Upload and text buttons are enabled by default
// Removed: addImageBtn/addTextBtn enable logic

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

    // Update image size (width or height) for the selected image

    // Update image color for the selected image



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
    
    // Apply color filter to product image
    // Base image is ORANGE (~30deg hue) with transparent background
    const tintOverride = window.CostasTint && window.CostasTint.getOverride(hex);
    if (tintOverride) {
        mockupImg.style.filter = tintOverride;
    } else if (isWhite) {
        // White product - desaturate completely and brighten significantly
        mockupImg.style.filter = 'saturate(0) brightness(2) contrast(0.8)';
    } else if (isBlack) {
        // Black product - desaturate and darken, but keep visibility
        mockupImg.style.filter = 'saturate(0) brightness(0.65) contrast(1.1)';
    } else if (isGray) {
        // Gray - desaturate and adjust brightness based on lightness
        const brightness = 0.2 + (hsl.l / 100) * 1.5;
        mockupImg.style.filter = `saturate(0) brightness(${brightness})`;
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
        if (hsl.l < 30)      brightness = 0.3 + (hsl.l / 100) * 0.7;
        else if (hsl.l < 50) brightness = 0.5 + (hsl.l / 100) * 0.6;
        else                 brightness = 0.6 + (hsl.l / 100) * 0.5;
        if (isYellowish && hsl.l >= 45) brightness = Math.min(brightness * 1.25, 1.5);

        mockupImg.style.filter = `grayscale(1) sepia(1) saturate(${saturate}) hue-rotate(${hueRotate}deg) brightness(${brightness})`;
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
        rightSleeveImagePath: product.right_sleeve_image_path || '',
        da_front_x: product.da_front_x, da_front_y: product.da_front_y,
        da_front_w: product.da_front_w, da_front_h: product.da_front_h,
        da_back_x:  product.da_back_x,  da_back_y:  product.da_back_y,
        da_back_w:  product.da_back_w,  da_back_h:  product.da_back_h,
        da_lsleeve_x: product.da_lsleeve_x, da_lsleeve_y: product.da_lsleeve_y,
        da_lsleeve_w: product.da_lsleeve_w, da_lsleeve_h: product.da_lsleeve_h,
        da_rsleeve_x: product.da_rsleeve_x, da_rsleeve_y: product.da_rsleeve_y,
        da_rsleeve_w: product.da_rsleeve_w, da_rsleeve_h: product.da_rsleeve_h,
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
    
    // Update mockup, design area, and summary
    updateMockupImage();
    applyDesignArea();
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
        element.fontFamily = document.getElementById('fontFamily').value;
        element.fontSize = parseInt(document.getElementById('fontSize').value);
        element.color = document.getElementById('textColor').value;
        element.bold = document.getElementById('boldBtn').classList.contains('active');
        element.italic = document.getElementById('italicBtn').classList.contains('active');
        element.underline = document.getElementById('underlineBtn').classList.contains('active');

        // If inline editing is active, update the DOM style directly without re-rendering
        const div = document.getElementById(editingTextElementId);
        if (div && div.classList.contains('text-inline-editing')) {
            const tc = div.querySelector('.text-content');
            if (tc) {
                tc.style.fontFamily = element.fontFamily;
                tc.style.fontSize = element.fontSize + 'px';
                tc.style.color = element.color;
                tc.style.fontWeight = element.bold ? 'bold' : 'normal';
                tc.style.fontStyle = element.italic ? 'italic' : 'normal';
                tc.style.textDecoration = element.underline ? 'underline' : 'none';
            }
        } else {
            element.text = text;
            renderElements();
            updateLayerList();
        }
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
    const designElement    = e.target.closest('.design-element');
    const imageEditorPanel = e.target.closest('#imageEditorPanel');
    const textEditorModal  = e.target.closest('#textEditorModal');
    const uploadEditorModal = e.target.closest('#uploadEditorModal');
    const changeColorModal = e.target.closest('#changeColorModal');
    const whatsNextAction  = e.target.closest('.whats-next-action');
    // If click is inside a design element, any editor panel, or a whats-next action, do not auto-close
    if (designElement || imageEditorPanel || textEditorModal || uploadEditorModal || changeColorModal || whatsNextAction) {
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
    // The product is what matters here; colour and size are optional refinements.
    // Requiring all three meant a missing size silently dropped the whole handoff
    // and the studio fell back to the first product in the list.
    if (prodId) {
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
                rightSleeveImagePath: product.right_sleeve_image_path || '',
                da_front_x: product.da_front_x, da_front_y: product.da_front_y,
                da_front_w: product.da_front_w, da_front_h: product.da_front_h,
                da_back_x:  product.da_back_x,  da_back_y:  product.da_back_y,
                da_back_w:  product.da_back_w,  da_back_h:  product.da_back_h,
                da_lsleeve_x: product.da_lsleeve_x, da_lsleeve_y: product.da_lsleeve_y,
                da_lsleeve_w: product.da_lsleeve_w, da_lsleeve_h: product.da_lsleeve_h,
                da_rsleeve_x: product.da_rsleeve_x, da_rsleeve_y: product.da_rsleeve_y,
                da_rsleeve_w: product.da_rsleeve_w, da_rsleeve_h: product.da_rsleeve_h,
            };
            // Show sleeve buttons if any sleeve image exists
            if (window.currentProduct.leftSleeveImagePath || window.currentProduct.rightSleeveImagePath) {
                document.getElementById('leftSleeveBtn').style.display = window.currentProduct.leftSleeveImagePath ? '' : 'none';
                document.getElementById('rightSleeveBtn').style.display = window.currentProduct.rightSleeveImagePath ? '' : 'none';
            } else {
                document.getElementById('leftSleeveBtn').style.display = 'none';
                document.getElementById('rightSleeveBtn').style.display = 'none';
            }
            // Init color panel and pre-select the color chosen on the product page
            if (colorId) window.pendingColorId = colorId;
            initStudioColorPanel(product.id);
            // Update mockup, design area, and summary
            updateMockupImage();
            applyDesignArea();
            updateSummary();
        }
        // A deliberate pick from the product page must beat any saved studio
        // snapshot. restoreStudioState() runs later (on a timer, from a separate
        // DOMContentLoaded handler) and guards on these sessionStorage keys - but
        // we clear them just below, so by the time it looks they are already gone
        // and it happily restores the previous session's product AND its uploads
        // over the top. Flag it in memory instead, and drop the stale snapshot.
        window.studioFreshPick = true;
        if (typeof window.clearStudioState === 'function') window.clearStudioState();

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
    
    const productData = productsData.find(p => String(p.id) === String(productId)) || {};
    window.currentProduct = {
        id: productId,
        name: productName,
        basePrice: basePrice,
        imagePath: imagePath,
        backImagePath: backImagePath,
        leftSleeveImagePath: el.dataset.leftSleeveImage || '',
        rightSleeveImagePath: el.dataset.rightSleeveImage || '',
        sizeChartImage: productData.size_chart_image || el.dataset.sizeChart || '',
        da_front_x: productData.da_front_x, da_front_y: productData.da_front_y,
        da_front_w: productData.da_front_w, da_front_h: productData.da_front_h,
        da_back_x:  productData.da_back_x,  da_back_y:  productData.da_back_y,
        da_back_w:  productData.da_back_w,  da_back_h:  productData.da_back_h,
        da_lsleeve_x: productData.da_lsleeve_x, da_lsleeve_y: productData.da_lsleeve_y,
        da_lsleeve_w: productData.da_lsleeve_w, da_lsleeve_h: productData.da_lsleeve_h,
        da_rsleeve_x: productData.da_rsleeve_x, da_rsleeve_y: productData.da_rsleeve_y,
        da_rsleeve_w: productData.da_rsleeve_w, da_rsleeve_h: productData.da_rsleeve_h,
    };
    // Toggle the size guide link visibility based on whether this product has a chart
    var sgLink = document.getElementById('studioSizeGuideLink');
    if (sgLink) sgLink.style.display = window.currentProduct.sizeChartImage ? '' : 'none';
    // Update mockup image
    updateMockupImage();
    applyDesignArea();
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
        
        // Re-apply color tint and design area after image loads
        mockupImg.onload = function() {
            if (currentColorHex) applyColorTint(currentColorHex);
            applyDesignArea();
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

    // Update mockup
    updateMockupImage();

    // Apply per-product design area for this view
    applyDesignArea();

    // Show elements for this view
    renderElements();
}

// Apply the stored design area position/size for the current product + view.
// Percentages are stored relative to the product image dimensions (same as the admin editor).
// We convert to pixel positions relative to .mockup-container using the image's offsetLeft/Top.
function applyDesignArea() {
    const designArea  = document.getElementById('designArea');
    const mockupImg   = document.getElementById('mockupProduct');
    if (!designArea || !mockupImg || !window.currentProduct) return;

    // Wait until the image has real dimensions
    if (!mockupImg.offsetWidth || !mockupImg.offsetHeight) {
        mockupImg.addEventListener('load', applyDesignArea, { once: true });
        return;
    }

    const p = window.currentProduct;
    const viewMap = {
        'front':        { x: p.da_front_x,   y: p.da_front_y,   w: p.da_front_w,   h: p.da_front_h   },
        'back':         { x: p.da_back_x,    y: p.da_back_y,    w: p.da_back_w,    h: p.da_back_h    },
        'left-sleeve':  { x: p.da_lsleeve_x, y: p.da_lsleeve_y, w: p.da_lsleeve_w, h: p.da_lsleeve_h },
        'right-sleeve': { x: p.da_rsleeve_x, y: p.da_rsleeve_y, w: p.da_rsleeve_w, h: p.da_rsleeve_h },
    };
    const defaults = {
        'front':        { x: 27.5, y: 25, w: 45, h: 60 },
        'back':         { x: 27.5, y: 25, w: 45, h: 60 },
        'left-sleeve':  { x: 46,   y: 27, w: 13, h: 16 },
        'right-sleeve': { x: 46,   y: 27, w: 13, h: 16 },
    };

    const d   = viewMap[currentView] || defaults[currentView];
    const def = defaults[currentView];
    const x   = (d.x != null ? d.x : def.x);
    const y   = (d.y != null ? d.y : def.y);
    const w   = (d.w != null ? d.w : def.w);
    const h   = (d.h != null ? d.h : def.h);

    // Convert % relative to image → absolute px relative to container
    const imgLeft = mockupImg.offsetLeft;
    const imgTop  = mockupImg.offsetTop;
    const imgW    = mockupImg.offsetWidth;
    const imgH    = mockupImg.offsetHeight;

    designArea.style.transform = '';
    designArea.style.left   = (imgLeft + x / 100 * imgW) + 'px';
    designArea.style.top    = (imgTop  + y / 100 * imgH) + 'px';
    designArea.style.width  = (w / 100 * imgW) + 'px';
    designArea.style.height = (h / 100 * imgH) + 'px';
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
    
    // Auto-select: use pending color from sessionStorage if set, otherwise first color
    if (window.studioVariantsData.colors.length > 0) {
        let targetSwatch = null;
        if (window.pendingColorId) {
            targetSwatch = container.querySelector(`.studio-color-swatch[data-color-id='${window.pendingColorId}']`);
            window.pendingColorId = null;
        }
        if (!targetSwatch) {
            targetSwatch = container.querySelector('.studio-color-swatch');
        }
        if (targetSwatch) {
            selectStudioColor(targetSwatch);
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

function enterInlineTextEdit(elementId) {
    const el = elements[currentView].find(e => e.id === elementId);
    if (!el || el.type !== 'text') return;

    const div = document.getElementById(elementId);
    if (!div) return;
    const textContent = div.querySelector('.text-content');
    if (!textContent) return;

    // Already editing inline
    if (div.classList.contains('text-inline-editing')) return;

    // Also open styling panel (without stealing focus away)
    editTextElement(elementId);

    div.classList.add('text-inline-editing');
    textContent.contentEditable = 'true';

    // Disable interact.js drag while editing
    interact(div).unset();

    // Place cursor at end
    textContent.focus();
    const range = document.createRange();
    range.selectNodeContents(textContent);
    range.collapse(false);
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);

    function syncToPanel() {
        el.text = textContent.textContent;
        const panelInput = document.getElementById('textContent');
        if (panelInput) panelInput.value = el.text;
    }

    function exitInlineEdit() {
        if (!div.classList.contains('text-inline-editing')) return;
        div.classList.remove('text-inline-editing');
        textContent.contentEditable = 'false';
        el.text = (textContent.textContent || '').trim();
        if (!el.text) el.text = 'Text';
        textContent.removeEventListener('input', syncToPanel);
        textContent.removeEventListener('blur', onBlur);
        textContent.removeEventListener('keydown', onKeydown);
        // Re-enable drag
        initInteract(div, el);
        renderElements();
    }

    function onBlur() {
        // Small delay so clicking the panel doesn't exit immediately
        setTimeout(exitInlineEdit, 150);
    }

    function onKeydown(e) {
        if (e.key === 'Escape') { e.preventDefault(); exitInlineEdit(); }
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); exitInlineEdit(); }
        e.stopPropagation();
    }

    textContent.addEventListener('input', syncToPanel);
    textContent.addEventListener('blur', onBlur);
    textContent.addEventListener('keydown', onKeydown);
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
                <div class="text-content" style="${style}" data-element-id="${element.id}">${escapeHtml(element.text)}</div>
                <button class="delete-btn" onclick="deleteElement('${element.id}')">x</button>
            `;

            // Distinguish click (inline edit) from drag (move)
            let _mdX = 0, _mdY = 0, _mdT = 0;
            div.addEventListener('mousedown', (e) => { _mdX = e.clientX; _mdY = e.clientY; _mdT = Date.now(); }, true);
            div.addEventListener('mouseup', (e) => {
                if (e.target.classList.contains('delete-btn')) return;
                const moved = Math.abs(e.clientX - _mdX) > 5 || Math.abs(e.clientY - _mdY) > 5;
                const held = Date.now() - _mdT > 250;
                if (!moved && !held) {
                    enterInlineTextEdit(element.id);
                }
            });
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
    // Hide design area box when nothing is selected
    const _da = document.getElementById('designArea');
    if (_da) _da.classList.remove('da-active');
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
    // Show design area box when an element is active
    const _da = document.getElementById('designArea');
    if (_da) _da.classList.add('da-active');
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
        // Just select visually — don't open editor on single click so drag still works
        // Editor opens on double-click or the E button
        document.getElementById('imageEditorPanel').style.display = 'none';
        hideImageEditor();
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
    
    // Close image/text editor panel and deselect
    hideImageEditor();
    hideTextEditor();
    if (selectedElement === id) {
        deselectAll();
    }
}

/* ============================================================
   STUDIO STATE PERSISTENCE — inline so it shares scope with the
   state vars (`elements`, `currentView`, `currentColorHex`, etc.)
   declared above. Survives refresh and the /lang/ redirect.
   ============================================================ */
(function () {
    var KEY = 'studio_state_v1';
    var saveTimer = null;
    var ready = false;

    function deepClone(obj) {
        try { return JSON.parse(JSON.stringify(obj)); } catch (e) { return null; }
    }
    function dbg() { /* no-op (debug logs removed) */ }

    function getSelectedSize() {
        if (!window.currentProduct) return null;
        var panel = document.getElementById('options-' + window.currentProduct.id);
        if (!panel) return null;
        var radio = panel.querySelector('input[type=radio][name^="size_"]:checked');
        return radio ? radio.value : null;
    }
    function setSelectedSize(sizeId) {
        if (!sizeId || !window.currentProduct) return;
        var panel = document.getElementById('options-' + window.currentProduct.id);
        if (!panel) return;
        var radio = panel.querySelector('input[type=radio][name^="size_"][value="' + sizeId + '"]');
        if (radio) {
            radio.checked = true;
            try { radio.dispatchEvent(new Event('change', { bubbles: true })); } catch (e) {}
        }
    }

    function trySetItem(value) {
        try { localStorage.setItem(KEY, value); return true; } catch (e) { return false; }
    }

    window.saveStudioState = function () {
        if (!ready) return;
        if (!window.currentProduct) return;
        var snapshot = {
            v: 2,
            ts: Date.now(),
            // Save the WHOLE product object (this page sets currentProduct
            // programmatically; there's no `.product-choice` card to click).
            product: deepClone(window.currentProduct),
            view: currentView,
            colorHex: currentColorHex,
            studioColorId: window.studioSelectedColorId || null,
            sizeId: getSelectedSize(),
            elements: deepClone(elements),
            counter: elementIdCounter
        };
        var payload = JSON.stringify(snapshot);
        if (trySetItem(payload)) { dbg('saved', snapshot.product && snapshot.product.id, snapshot.view); return; }
        // Quota exceeded — strip image data URLs and retry
        if (snapshot.elements) {
            Object.keys(snapshot.elements).forEach(function (k) {
                (snapshot.elements[k] || []).forEach(function (el) {
                    if (el && el.type === 'image' && el.src && String(el.src).indexOf('data:') === 0) {
                        el._stripped = true;
                        delete el.src;
                    }
                });
            });
        }
        trySetItem(JSON.stringify(snapshot));
        dbg('saved (stripped)', snapshot.product && snapshot.product.id);
    };

    window.scheduleStudioStateSave = function () {
        if (saveTimer) clearTimeout(saveTimer);
        saveTimer = setTimeout(window.saveStudioState, 250);
    };

    window.clearStudioState = function () {
        try { localStorage.removeItem(KEY); dbg('cleared'); } catch (e) {}
    };

    function restoreStudioState() {
        // If the user just arrived from /shop/select_product with a fresh
        // pick, sessionStorage holds the selection. Don't overwrite that
        // with an older localStorage snapshot — they wanted a fresh start.
        // The in-memory flag is authoritative: the handoff handler runs first
        // and clears the sessionStorage keys before we ever get here.
        if (window.studioFreshPick) {
            dbg('fresh product pick consumed this load, skipping restore');
            return false;
        }
        try {
            if (sessionStorage.getItem('custom_product_id')) {
                dbg('fresh session-pick present, skipping restore');
                return false;
            }
        } catch (e) {}

        var raw;
        try { raw = localStorage.getItem(KEY); } catch (e) { dbg('no localStorage'); return false; }
        if (!raw) { dbg('no saved state'); return false; }
        var s;
        try { s = JSON.parse(raw); } catch (e) { dbg('parse fail'); return false; }
        if (!s || !s.product || !s.product.id) { dbg('invalid state', s); return false; }

        dbg('restoring', s);

        // Set the product directly — there are no `.product-choice` cards on this page.
        window.currentProduct = s.product;

        // Toggle sleeve buttons based on whether the product has sleeve images
        try {
            var lsBtn = document.getElementById('leftSleeveBtn');
            var rsBtn = document.getElementById('rightSleeveBtn');
            if (lsBtn) lsBtn.style.display = s.product.leftSleeveImagePath ? '' : 'none';
            if (rsBtn) rsBtn.style.display = s.product.rightSleeveImagePath ? '' : 'none';
        } catch (e) {}

        // Restore primitives BEFORE calling render functions that read them
        if (s.view) currentView = s.view;
        if (s.colorHex) currentColorHex = s.colorHex;

        // Restore design elements per view
        if (s.elements) {
            ['front', 'back', 'left-sleeve', 'right-sleeve'].forEach(function (k) {
                elements[k] = Array.isArray(s.elements[k]) ? s.elements[k] : [];
            });
            var maxId = 0;
            Object.keys(elements).forEach(function (k) {
                (elements[k] || []).forEach(function (el) {
                    var n = parseInt(String(el && el.id || '').replace(/[^0-9]/g, ''), 10);
                    if (!isNaN(n) && n > maxId) maxId = n;
                });
            });
            elementIdCounter = Math.max(elementIdCounter || 0, s.counter || 0, maxId + 1);
        }

        // Initialise color panel (it'll pick up the pending color)
        if (s.studioColorId) window.pendingColorId = s.studioColorId;
        try {
            if (typeof initStudioColorPanel === 'function') initStudioColorPanel(s.product.id);
        } catch (e) { dbg('initStudioColorPanel failed', e); }

        // Refresh mockup + design area + summary
        try { if (typeof updateMockupImage === 'function') updateMockupImage(); } catch (e) {}
        try { if (typeof applyDesignArea === 'function') applyDesignArea(); } catch (e) {}
        try {
            if (s.colorHex && typeof applyColorTint === 'function') applyColorTint(s.colorHex);
        } catch (e) {}
        try { if (typeof renderElements === 'function') renderElements(); } catch (e) {}
        try { if (typeof updateLayerList === 'function') updateLayerList(); } catch (e) {}
        try { if (typeof updateSummary === 'function') updateSummary(); } catch (e) {}

        // Set size radio if present
        if (s.sizeId) setSelectedSize(s.sizeId);

        // After the variants fetch resolves, also click the right color swatch
        if (s.studioColorId) {
            setTimeout(function () {
                var sw = document.querySelector('.color-swatch[data-color-id="' + s.studioColorId + '"]');
                if (sw) sw.click();
                else setTimeout(function () {
                    var sw2 = document.querySelector('.color-swatch[data-color-id="' + s.studioColorId + '"]');
                    if (sw2) sw2.click();
                }, 700);
            }, 350);
        }

        dbg('restore done');
        return true;
    }

    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(function () {
            ready = true;
            dbg('ready, attempting restore');
            try { restoreStudioState(); } catch (e) { dbg('restore threw', e); }
            var root = document.querySelector('.custom-studio-layout') || document.body;
            root.addEventListener('click', window.scheduleStudioStateSave, true);
            root.addEventListener('change', window.scheduleStudioStateSave, true);
            root.addEventListener('input', window.scheduleStudioStateSave, true);
            document.addEventListener('mouseup', window.scheduleStudioStateSave, true);
            document.addEventListener('touchend', window.scheduleStudioStateSave, true);
        }, 60);
    });

    window.addEventListener('beforeunload', function () {
        try { window.saveStudioState(); } catch (e) {}
    });
    window.addEventListener('pagehide', function () {
        try { window.saveStudioState(); } catch (e) {}
    });

    // Also intercept clicks on the language switcher to save immediately
    // (some browsers throttle beforeunload during navigations).
    document.addEventListener('click', function (e) {
        var a = e.target && e.target.closest ? e.target.closest('a[href^="/lang/"]') : null;
        if (a) {
            try { window.saveStudioState(); } catch (err) {}
        }
    }, true);
})();
</script>

<?php require __DIR__ . '/../partials/size_guide_modal.php'; ?>
<?php require __DIR__ . '/../layouts/customer_footer.php'; ?>
