/* cart-modal.js — from views/shop/designer.php, loaded where the inline script used to run. */

// The add-to-cart pop-up. The colour is the one picked in the studio, locked
// here; the shared size × quantity picker (lib/size-qty.js) adds one cart
// line per size — the same pop-up as premade designs and the account page.
let cartModalState = {
    designId: null,
    productId: null,
    productName: '',
    designName: '',
    designFee: 0,        // print extras (front+back, sleeves), flat euros
    colorId: null,
    currentColorHex: '#ffffff'
};
let studioSizePicker = null;

function openAddToCartModal(designId, productId, productName, designName, basePrice, designFee) {
    const colorId = window.studioSelectedColorId;
    const color = (window.studioVariantsData.colors || []).find(c => c.id == colorId);
    if (!colorId || !color) {
        UI.error(window.I18N.t('view_design.modal.pick_color'));
        return;
    }
    cartModalState.designId = designId;
    cartModalState.productId = productId;
    cartModalState.productName = productName || 'Custom Product';
    cartModalState.designName = designName || 'Your Design';
    cartModalState.designFee = parseFloat(designFee) || 0;
    cartModalState.colorId = colorId;
    cartModalState.currentColorHex = color.hex || currentColorHex || '#ffffff';

    document.getElementById('cartProductName').textContent = cartModalState.productName;
    document.getElementById('cartDesignName').textContent = cartModalState.designName;
    SizeQty.colorLine(document.getElementById('cartColorLine'), color.hex, color.name);
    const guide = document.getElementById('studioSizeGuideLink');
    if (guide) guide.style.display = (window.currentProduct && window.currentProduct.sizeChartImage) ? '' : 'none';

    captureDesignPreview();

    if (!studioSizePicker) {
        studioSizePicker = SizeQty.create(document.getElementById('addToCartModal'), {
            button: document.getElementById('confirmAddToCartBtn'),
            error:  document.getElementById('cartError')
        });
    }
    const fee = cartModalState.designFee;
    studioSizePicker.configure({
        productName: cartModalState.productName,
        basePrice: basePrice,
        extra: fee,
        note: fee > 0 ? window.I18N.t('size_qty.print_note', { fee: '€' + fee.toFixed(2) }) : ''
    });
    studioSizePicker.setColor(colorId);
    fetch('/api/product-variants/' + productId)
        .then(r => r.json())
        .then(data => studioSizePicker.setData(data))
        .catch(() => studioSizePicker.showError(window.I18N.t('size_qty.load_failed')));

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

function closeAddToCartModal() {
    document.getElementById('addToCartModal').style.display = 'none';
}

// One cart line per size. Each line gets its own preview image in the
// chosen colour, made one after another (they share the studio canvas).
document.addEventListener('DOMContentLoaded', function() {
    const confirmBtn = document.getElementById('confirmAddToCartBtn');
    if (!confirmBtn) return;
    confirmBtn.addEventListener('click', async function() {
        const state = Object.assign({}, cartModalState);
        let previews = Promise.resolve();
        const ok = await studioSizePicker.addToCart(line => ({
            custom: true,
            design_id: state.designId,
            product_id: state.productId,
            size_id: line.sizeId,
            color_id: state.colorId,
            quantity: line.qty,
            custom_design_fee: state.designFee
        }), (line, data) => {
            if (!data.cart_item_id) return;
            previews = previews.then(() => generateAndSavePreviews(state.designId, {
                colorHex: state.currentColorHex,
                cartItemId: data.cart_item_id
            })).catch(() => {});
        });
        if (ok) {
            showCartSuccessNotification();
            closeAddToCartModal();
        }
    });
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
