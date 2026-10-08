/* premade-design.js — from views/shop/premade_design.php, loaded where the inline script used to run. */
const premadeDesignData = JSON.parse(document.getElementById('premade-design-data').textContent);

const designPrice = premadeDesignData.designPrice;
const isFixedDesign = premadeDesignData.isFixed;
// Placement is per garment — these track the SELECTED product and are rewritten
// when the shopper picks a different one (see the .product-option handler).
// $availableProducts already carries the link-row position, or the design's own
// as a fallback.
let savedDesignPos = {
    x: premadeDesignData.posFront.x,
    y: premadeDesignData.posFront.y,
    size: premadeDesignData.posFront.size
};
let savedDesignPosBack = {
    x: premadeDesignData.posBack.x,
    y: premadeDesignData.posBack.y,
    size: premadeDesignData.posBack.size
};
const frontDesignImage = premadeDesignData.frontDesignImage;
const backDesignImage = premadeDesignData.backDesignImage;
let selectedProductId = premadeDesignData.productId;
let selectedBasePrice = premadeDesignData.basePrice;
let currentSide = 'front';
let currentProductFrontImage = premadeDesignData.productFrontImage;
let currentProductBackImage = premadeDesignData.productBackImage;
let currentProductLeftSleeveImage = premadeDesignData.productLeftSleeveImage;
let currentProductRightSleeveImage = premadeDesignData.productRightSleeveImage;

// Design position tracking (for front and back)
let designPositions = {
    front: { x: 0, y: 0, width: 160, height: 160 },
    back: { x: 0, y: 0, width: 160, height: 160 }
};

// ==================== PREVIEW SIZE CHIPS ====================
// Colour → available size ids per product, for the size chips under each
// product's preview colours. At top level so the product picker below can
// call it: it used to sit inside the DOMContentLoaded handler, where the
// picker's call threw and skipped the rest of the picker (the price box kept
// the previous product's price).
const previewColorSizes = premadeDesignData.previewColorSizes;
// Cheapest supplier cost of each preview colour (prodId → colorId → cost): a
// coloured tee costs more than the white base price, so the price box follows
// the colour on show. The exact size is priced in the add-to-cart pop-up.
const previewColorCosts = premadeDesignData.previewColorCosts || {};

function previewedCost() {
    const radio = document.querySelector('input[name="preview_color_' + selectedProductId + '"]:checked');
    const cost  = radio && previewColorCosts[selectedProductId] ? previewColorCosts[selectedProductId][radio.value] : undefined;
    return cost != null ? parseFloat(cost) : selectedBasePrice;
}

function updatePreviewSizeChips(productId, colorId) {
    const container = document.getElementById('preview-sizes-' + productId);
    if (!container) return;
    const available = (previewColorSizes[productId] && previewColorSizes[productId][colorId]) || [];
    container.querySelectorAll('.preview-size-chip').forEach(chip => {
        const sid = chip.dataset.sizeId;
        if (available.includes(parseInt(sid)) || available.includes(sid)) {
            chip.classList.remove('unavailable');
            chip.classList.add('available');
        } else {
            chip.classList.remove('available');
            chip.classList.add('unavailable');
        }
    });
}

// ==================== PRODUCT SELECTION ====================
document.querySelectorAll('.product-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.product-option').forEach(o => o.classList.remove('selected'));
        this.classList.add('selected');

        selectedProductId = parseInt(this.dataset.productId);
        selectedBasePrice = parseFloat(this.dataset.basePrice);
        currentProductFrontImage = this.dataset.productImage || '';
        currentProductBackImage = this.dataset.productBackImage || '';
        currentProductLeftSleeveImage = this.dataset.leftSleeveImage || '';
        currentProductRightSleeveImage = this.dataset.rightSleeveImage || '';

        // The design's placement belongs to the design/product pair, so it moves
        // with the product the shopper just picked.
        const num = (v, fb) => { const n = parseFloat(v); return isNaN(n) ? fb : n; };
        savedDesignPos = {
            x:    num(this.dataset.posX, 0),
            y:    num(this.dataset.posY, 0),
            size: num(this.dataset.posSize, 55)
        };
        savedDesignPosBack = {
            x:    num(this.dataset.posBackX, 0),
            y:    num(this.dataset.posBackY, 0),
            size: num(this.dataset.posBackSize, 55)
        };

        // Update mockup
        updateMockupProduct();
        updateViewButtons();
        if (isFixedDesign) applyFixedDesignForSide(currentSide);

        // Clear the previous product's colour, then show this product's
        // preview colour (if one is picked) and its sizes. The reset used to
        // come after the new tint and would have wiped it.
        document.getElementById('mockupProduct').style.filter = 'none';
        document.getElementById('mockupContainer').classList.remove('dark-bg');

        // Show correct customization panel
        document.querySelectorAll('.product-customization').forEach(panel => {
            panel.style.display = 'none';
        });
        const customPanel = document.getElementById('customization-' + selectedProductId);
        if (customPanel) {
            customPanel.style.display = 'block';
            const firstPreviewColorRadio = customPanel.querySelector('input[name="preview_color_' + selectedProductId + '"]:checked');
            if (firstPreviewColorRadio && firstPreviewColorRadio.dataset.hex) {
                applyColorTint(firstPreviewColorRadio.dataset.hex);
                updatePreviewSizeChips(selectedProductId, firstPreviewColorRadio.value);
            }
        }

        updatePrice();
    });
});

// ==================== FRONT/BACK TOGGLE ====================
document.querySelectorAll('.side-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        if (this.classList.contains('disabled')) return;

        document.querySelectorAll('.side-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        currentSide = this.dataset.side;
        updateMockupProduct();

        if (isFixedDesign) {
            applyFixedDesignForSide(currentSide);
        } else {
            restoreDesignPosition();
        }
    });
});

// Dots + swipe. The label follows .active on its own, so the handler above
// needed no changes. Swipes starting on the design element are ignored so
// dragging the artwork still works.
// Deferred inside DOMContentLoaded: lib/view-switcher.js is loaded with `defer`,
// so it has not executed yet while this inline script is being parsed.
document.addEventListener('DOMContentLoaded', function () {
    if (!window.ViewSwitcher) return;
    window.ViewSwitcher.init({
        dots: '#sideDots',
        dotSelector: '.side-btn',
        label: '#sideCurrentLabel',
        surface: '#mockupContainer',
        ignore: '.design-element'
    });
});

function updateViewButtons() {
    const backBtn = document.getElementById('chooseBackBtn');
    const leftSleeveBtn = document.getElementById('chooseLeftSleeveBtn');
    const rightSleeveBtn = document.getElementById('chooseRightSleeveBtn');

    backBtn.style.display = currentProductBackImage ? '' : 'none';
    leftSleeveBtn.style.display = currentProductLeftSleeveImage ? '' : 'none';
    rightSleeveBtn.style.display = currentProductRightSleeveImage ? '' : 'none';

    // If currently on a view that's no longer available, switch to front
    if (currentSide === 'back' && !currentProductBackImage) {
        document.querySelector('.side-btn[data-side="front"]').click();
    } else if (currentSide === 'left-sleeve' && !currentProductLeftSleeveImage) {
        document.querySelector('.side-btn[data-side="front"]').click();
    } else if (currentSide === 'right-sleeve' && !currentProductRightSleeveImage) {
        document.querySelector('.side-btn[data-side="front"]').click();
    }
}

function updateMockupProduct() {
    const mockupProduct = document.getElementById('mockupProduct');
    const designArea = document.getElementById('designArea');
    let imagePath = '';
    if (currentSide === 'front') imagePath = currentProductFrontImage;
    else if (currentSide === 'back') imagePath = currentProductBackImage;
    else if (currentSide === 'left-sleeve') imagePath = currentProductLeftSleeveImage;
    else if (currentSide === 'right-sleeve') imagePath = currentProductRightSleeveImage;

    // Toggle sleeve design area size
    if (currentSide === 'left-sleeve' || currentSide === 'right-sleeve') {
        designArea.classList.add('sleeve-view');
    } else {
        designArea.classList.remove('sleeve-view');
    }

    if (mockupProduct && imagePath) {
        mockupProduct.src = '/' + imagePath;
        mockupProduct.style.display = 'block';

        // Re-apply current color tint to the new image
        const selectedColor = document.querySelector('input[name^="color_"]:checked');
        if (selectedColor && selectedColor.dataset.colorHex) {
            setTimeout(() => {
                applyColorTint(selectedColor.dataset.colorHex);
            }, 50);
        }
    } else if (mockupProduct) {
        mockupProduct.style.display = 'none';
    }
}

// ==================== COLOR SELECTION & TINTING ====================
document.querySelectorAll('.size-option input').forEach(radio => {
    radio.addEventListener('change', function() {
        updateColors(this);
        updatePrice();
    });
});

function updateColors(sizeInput) {
    const productId = sizeInput.name.replace('size_', '');
    const colorContainer = document.getElementById('colors-' + productId);

    const colorIds = sizeInput.dataset.colors ? sizeInput.dataset.colors.split(',') : [];
    const colorNames = sizeInput.dataset.colorNames ? sizeInput.dataset.colorNames.split(',') : [];
    const colorHexes = sizeInput.dataset.colorHexes ? sizeInput.dataset.colorHexes.split(',') : [];

    if (colorIds.length === 0 || !colorIds[0]) {
        colorContainer.innerHTML = '<p class="no-variants">' + I18N.t('view_design.no_colors_for_size') + '</p>';
        document.getElementById('mockupProduct').style.filter = 'none';
        document.getElementById('mockupContainer').classList.remove('dark-bg');
        return;
    }

    let html = '';
    for (let i = 0; i < colorIds.length; i++) {
        const checked = i === 0 ? 'checked' : '';
        const hex = (colorHexes[i] || '').toLowerCase();
        const name = (colorNames[i] || '').toLowerCase();
        const isWhite = hex === '#ffffff' || hex === '#fff' || name === 'white';
        html += `
            <label class="color-option">
                <input type="radio" name="color_${productId}" value="${colorIds[i]}" 
                       data-color-hex="${colorHexes[i]}" data-color-name="${colorNames[i]}" ${checked}
                       onchange="applyColorTint('${colorHexes[i]}')">
                <span class="color-swatch${isWhite ? ' is-white' : ''}" style="background-color: ${colorHexes[i]}"></span>
                <span class="color-name">${colorNames[i]}</span>
            </label>
        `;
    }
    colorContainer.innerHTML = html;

    // Apply first color tint
    if (colorHexes[0]) {
        applyColorTint(colorHexes[0]);
    }
}

function buildColorFilter(hexColor) {
    if (!hexColor) return 'none';
    hexColor = hexColor.trim();
    if (!hexColor.startsWith('#')) hexColor = '#' + hexColor;
    const hsl = hexToHSL(hexColor);
    const isWhite = hexColor.toLowerCase() === '#ffffff' || hsl.l > 95;
    const isBlack = hsl.l < 10;
    const isGray  = hsl.s < 10;
    const tintOverride = window.CostasTint && window.CostasTint.getOverride(hexColor);
    if (tintOverride) return tintOverride;

    if (isWhite) return 'grayscale(1) brightness(2.2) contrast(0.85)';
    if (isBlack) return 'grayscale(1) brightness(0.45) contrast(1.2)';
    if (isGray)  return `grayscale(1) brightness(${0.2 + (hsl.l / 100) * 1.5})`;
    const hueRotate = hsl.h - 38; // sepia base hue ≈ 38°
    const isReddish  = hsl.h <= 20 || hsl.h >= 340;
    const isYellowish = hsl.h >= 45 && hsl.h <= 80;
    let saturate = (hsl.s / 100) * 3 + 0.8;
    if (isReddish)   saturate = (hsl.s / 100) * 6 + 2.0;
    if (isYellowish) saturate = (hsl.s / 100) * 4 + 1.0;
    let brightness;
    if (hsl.l < 30)      brightness = 0.3 + (hsl.l / 100) * 0.7;
    else if (hsl.l < 50) brightness = 0.5 + (hsl.l / 100) * 0.6;
    else                 brightness = 0.6 + (hsl.l / 100) * 0.5;
    if (isYellowish && hsl.l >= 45) brightness = Math.min(brightness * 1.25, 1.5);
    return `grayscale(1) sepia(1) saturate(${saturate}) hue-rotate(${hueRotate}deg) brightness(${brightness})`;
}

function applyColorTint(hexColor) {
    if (!hexColor) return;

    // Ensure hex has # prefix
    hexColor = hexColor.trim();
    if (!hexColor.startsWith('#')) {
        hexColor = '#' + hexColor;
    }

    const mockupProduct = document.getElementById('mockupProduct');
    const mockupContainer = document.getElementById('mockupContainer');
    if (!mockupProduct || !mockupContainer) return;

    // Convert hex to HSL for the hue-rotate filter
    const hsl = hexToHSL(hexColor);

    // Check if it's white or very light
    const isWhite = hexColor.toLowerCase() === '#ffffff' || hexColor.toLowerCase() === '#fff' || hsl.l > 95;
    const isVeryLight = hsl.l > 85;
    const isBlack = hexColor.toLowerCase() === '#000000' || hexColor.toLowerCase() === '#000' || hsl.l < 10;
    const isGray = hsl.s < 10; // Low saturation = gray

    // Set background: white by default, dark for white/light products
    if (isWhite || isVeryLight) {
        mockupContainer.classList.add('dark-bg');
    } else {
        mockupContainer.classList.remove('dark-bg');
    }

    // Apply color filter to product image
    // Base image is ORANGE (~30deg hue) with transparent background
    const tintOverride = window.CostasTint && window.CostasTint.getOverride(hexColor);
    if (tintOverride) {
        mockupProduct.style.filter = tintOverride;
    } else if (isWhite) {
        // White product - desaturate completely and brighten significantly
        mockupProduct.style.filter = 'grayscale(1) brightness(2.2) contrast(0.85)';
    } else if (isBlack) {
        // Black product - desaturate and darken significantly
        mockupProduct.style.filter = 'grayscale(1) brightness(0.45) contrast(1.2)';
    } else if (isGray) {
        // Gray - desaturate and adjust brightness based on lightness
        const brightness = 0.2 + (hsl.l / 100) * 1.5;
        mockupProduct.style.filter = `grayscale(1) brightness(${brightness})`;
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
        if (hsl.l < 30) {
            brightness = 0.3 + (hsl.l / 100) * 0.7;
        } else if (hsl.l < 50) {
            brightness = 0.5 + (hsl.l / 100) * 0.6;
        } else {
            brightness = 0.6 + (hsl.l / 100) * 0.5;
        }
        if (isYellowish && hsl.l >= 45) brightness = Math.min(brightness * 1.25, 1.5);

        mockupProduct.style.filter = `grayscale(1) sepia(1) saturate(${saturate}) hue-rotate(${hueRotate}deg) brightness(${brightness})`;
    }
}

function hexToHSL(hex) {
    // Remove # if present
    hex = hex.replace('#', '');

    // Parse RGB
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

    return {
        h: Math.round(h * 360),
        s: Math.round(s * 100),
        l: Math.round(l * 100)
    };
}

// ==================== DRAG & RESIZE DESIGN ====================
function initDesignInteraction() {
    const designElement = document.getElementById('designElement');
    const designArea = document.getElementById('designArea');
    if (!designElement || !designArea || typeof interact === 'undefined') return;

    interact(designElement)
        .draggable({
            inertia: false,
            modifiers: [
                interact.modifiers.restrict({
                    restriction: designArea,
                    elementRect: { top: 0, left: 0, bottom: 1, right: 1 }
                })
            ],
            listeners: {
                move: dragMoveListener
            }
        })
        .resizable({
            edges: { right: '.resize-handle', bottom: '.resize-handle' },
            modifiers: [
                interact.modifiers.restrictSize({
                    min: { width: 40, height: 40 },
                    max: { width: 450, height: 450 }
                }),
                interact.modifiers.restrict({
                    restriction: designArea
                })
            ],
            listeners: {
                move: resizeMoveListener
            }
        });
}

function dragMoveListener(event) {
    const target = event.target;
    const x = (parseFloat(target.getAttribute('data-x')) || 0) + event.dx;
    const y = (parseFloat(target.getAttribute('data-y')) || 0) + event.dy;

    target.style.transform = `translate(calc(-50% + ${x}px), calc(-50% + ${y}px))`;
    target.setAttribute('data-x', x);
    target.setAttribute('data-y', y);

    // Save position for current side
    designPositions[currentSide].x = x;
    designPositions[currentSide].y = y;
}

function resizeMoveListener(event) {
    const target = event.target;

    // Keep it square for consistent sizing
    const size = Math.max(event.rect.width, event.rect.height);

    target.style.width = size + 'px';
    target.style.height = size + 'px';

    // Save size for current side
    designPositions[currentSide].width = size;
    designPositions[currentSide].height = size;
}

function restoreDesignPosition() {
    const designElement = document.getElementById('designElement');
    if (!designElement) return;

    // No design overlay for sleeve views
    if (currentSide === 'left-sleeve' || currentSide === 'right-sleeve') {
        designElement.style.display = 'none';
        return;
    }
    designElement.style.display = 'flex';

    const pos = designPositions[currentSide] || { x: 0, y: 0, width: 160, height: 160 };
    const x = pos.x || 0;
    const y = pos.y || 0;
    const width = pos.width || 160;
    const height = pos.height || 160;

    designElement.style.transform = `translate(calc(-50% + ${x}px), calc(-50% + ${y}px))`;
    designElement.setAttribute('data-x', x);
    designElement.setAttribute('data-y', y);
    designElement.style.width = width + 'px';
    designElement.style.height = height + 'px';
}

function resetDesignPosition() {
    const designElement = document.getElementById('designElement');
    if (!designElement) return;

    designElement.style.transform = 'translate(-50%, -50%)';
    designElement.style.width = '160px';
    designElement.style.height = '160px';
    designElement.setAttribute('data-x', 0);
    designElement.setAttribute('data-y', 0);

    designPositions[currentSide] = { x: 0, y: 0, width: 160, height: 160 };

    // Reset slider
    document.getElementById('designSizeSlider').value = 160;
}

function centerDesign() {
    const designElement = document.getElementById('designElement');
    if (!designElement) return;

    designElement.style.transform = 'translate(-50%, -50%)';
    designElement.setAttribute('data-x', 0);
    designElement.setAttribute('data-y', 0);

    designPositions[currentSide].x = 0;
    designPositions[currentSide].y = 0;
}

function updateDesignSize(value) {
    const designElement = document.getElementById('designElement');
    if (!designElement) return;

    // Value is slider value (30-200), use as pixel size
    const size = parseInt(value);
    designElement.style.width = size + 'px';
    designElement.style.height = size + 'px';

    designPositions[currentSide].width = size;
    designPositions[currentSide].height = size;
}

function applyFixedDesignForSide(side) {
    const designElement = document.getElementById('designElement');
    const designArea = document.getElementById('designArea');
    const mockupDesign = document.getElementById('mockupDesign');
    if (!designElement || !designArea || !mockupDesign) return;

    // No design overlay for sleeve views
    if (side === 'left-sleeve' || side === 'right-sleeve') {
        designElement.style.display = 'none';
        return;
    }

    const pos = side === 'front' ? savedDesignPos : savedDesignPosBack;
    const imgSrc = side === 'front' ? frontDesignImage : backDesignImage;

    if (!imgSrc) {
        designElement.style.display = 'none';
        return;
    }
    mockupDesign.src = '/' + imgSrc;
    designElement.style.display = 'flex';

    setTimeout(() => {
        const areaW = designArea.offsetWidth;
        const areaH = designArea.offsetHeight;
        if (areaW === 0) return;
        const px = (pos.x / 100) * (areaW / 2);
        const py = (pos.y / 100) * (areaH / 2);
        const ps = (pos.size / 100) * areaW;
        designElement.style.width = ps + 'px';
        designElement.style.height = ps + 'px';
        designElement.style.transform = `translate(calc(-50% + ${px}px), calc(-50% + ${py}px))`;
        designElement.setAttribute('data-x', px);
        designElement.setAttribute('data-y', py);
    }, 20);
}

// ==================== PRICE CALCULATION ====================
function updatePrice() {
    // selectedBasePrice is the SUPPLIER cost; apply the quantity-tiered margin so
    // the preview matches what the server charges.
    const selectedOpt = document.querySelector('.product-option.selected');
    const productName = selectedOpt ? (selectedOpt.dataset.productName || '') : '';
    const qtyInput = document.getElementById('quantity');
    const qty = qtyInput ? (parseInt(qtyInput.value) || 1) : 1;
    const category = window.Pricing ? Pricing.categoryFor('', productName) : 'tshirt';
    const supplier = previewedCost();
    const unitRetail = window.Pricing
        ? Pricing.unitPrice(supplier, category, qty)
        : supplier;

    // Lines are per item; the total is for the whole quantity, the way the
    // cart charges it (it used to show one item's price as the total).
    document.getElementById('basePrice').textContent = priceEach(unitRetail, qty);
    document.getElementById('designPriceValue').textContent = '+' + priceEach(designPrice, qty);
    // Second design cost (front + back print)
    let secondDesignCost = 0;
    if (document.getElementById('addSecondDesign').checked) {
        secondDesignCost = designPrice;
        document.getElementById('secondDesignCost').textContent = '+' + priceEach(designPrice, qty);
        document.getElementById('secondDesignRow').style.display = 'flex';
    } else {
        document.getElementById('secondDesignRow').style.display = 'none';
    }
    const total = (unitRetail + designPrice + secondDesignCost) * qty;
    document.getElementById('totalLabel').textContent = totalLabel(qty, 'view_design.price.total');
    document.getElementById('totalPrice').textContent = '€' + total.toFixed(2);
}

// "€27.14", or "€27.14 each" once there is more than one.
function priceEach(amount, qty) {
    const each = window.I18N ? I18N.t('view_design.price.each') : 'each';
    return '€' + amount.toFixed(2) + (qty > 1 ? ' ' + each : '');
}

// "Total:", or "Total (14 items):" once there is more than one.
function totalLabel(qty, key) {
    if (!window.I18N) return qty > 1 ? 'Total (' + qty + ' items)' : 'Total';
    return qty > 1 ? I18N.t('view_design.price.total_qty', { qty: qty }) : I18N.t(key);
}

// ==================== ADD-TO-CART POP-UP ====================
// The colour is chosen on the page and locked once the pop-up opens. The
// pop-up lists that colour's sizes, each with its own quantity, and adds one
// cart line per size — a small and a medium are different items.
let confirmModalState = {
    productId: null,
    premadeDesignId: premadeDesignData.designId,
    designName: premadeDesignData.designName,
    designFee: premadeDesignData.designPrice,
    basePrice: 0,
    colorId: null,
    variants: [],
    sizes: [],
    qty: {}            // size id → quantity
};

const MAX_QTY = 99;

function tr(key, params, fallback) {
    if (!window.I18N) return fallback;
    const s = I18N.t(key, params);
    return s && s !== key ? s : fallback;
}

function addToCart() {
    if (!selectedProductId) { UI.error(tr('view_design.modal.pick_product', null, 'Please select a product.')); return; }

    const colorRadio = document.querySelector('input[name="preview_color_' + selectedProductId + '"]:checked');
    if (!colorRadio) { UI.error(tr('view_design.modal.pick_color', null, 'Please choose a colour.')); return; }

    confirmModalState.productId = selectedProductId;
    confirmModalState.basePrice = selectedBasePrice;
    confirmModalState.colorId   = colorRadio.value;
    confirmModalState.variants  = [];
    confirmModalState.sizes     = [];
    confirmModalState.qty       = {};

    // Preview: the garment in the chosen colour, with the design where it sits
    // on the page (as ratios of the mockup, so it can't drift from the CSS).
    const productImg = document.getElementById('mockupProduct');
    document.getElementById('confirmProductImg').src = productImg ? productImg.src : '';
    document.getElementById('confirmProductImg').style.filter = productImg ? productImg.style.filter : '';
    const designEl   = document.getElementById('designElement');
    const designImg  = document.getElementById('mockupDesign');
    const mockup     = document.getElementById('mockupContainer');
    const cDesignImg = document.getElementById('confirmDesignImg');
    cDesignImg.style.display = 'none';
    if (designEl && designImg && designImg.src && mockup) {
        const mRect  = mockup.getBoundingClientRect();
        const elRect = designEl.getBoundingClientRect();
        if (mRect.width > 0 && mRect.height > 0) {
            cDesignImg.src = designImg.src;
            cDesignImg.style.display = 'block';
            cDesignImg.style.width = (elRect.width / mRect.width) * 100 + '%';
            cDesignImg.style.left  = ((elRect.left - mRect.left) + elRect.width  / 2) / mRect.width  * 100 + '%';
            cDesignImg.style.top   = ((elRect.top  - mRect.top)  + elRect.height / 2) / mRect.height * 100 + '%';
        }
    }

    const productOption = document.querySelector('.product-option.selected');
    document.getElementById('confirmProductName').textContent = productOption ? productOption.dataset.productName : '';
    document.getElementById('confirmDesignLine').textContent  = tr('view_design.modal.design_line', { name: confirmModalState.designName }, 'Design: ' + confirmModalState.designName);

    // The locked colour: a swatch and its name.
    const colorLine = document.getElementById('confirmColorLine');
    colorLine.textContent = '';
    const dot = document.createElement('span');
    dot.className = 'confirm-color-dot';
    dot.style.backgroundColor = colorRadio.dataset.hex || '#fff';
    colorLine.appendChild(dot);
    colorLine.appendChild(document.createTextNode((window.I18N ? I18N.t('cart.item.color') : 'Color') + ': ' + (colorRadio.dataset.name || '')));

    document.getElementById('confirmError').style.display = 'none';
    document.getElementById('sizeQtyGrid').innerHTML = '';
    updateConfirmPrices();
    fetchConfirmVariants(selectedProductId);

    document.getElementById('confirmCartModal').classList.add('active');
}

function fetchConfirmVariants(productId) {
    fetch('/api/product-variants/' + productId)
        .then(r => r.json())
        .then(data => {
            confirmModalState.variants = data.variants || [];
            confirmModalState.sizes    = data.sizes    || [];
            renderSizeQty();
            updateConfirmPrices();
        })
        .catch(() => showConfirmError(tr('view_design.modal.load_failed', null, 'Failed to load product options.')));
}

// The variant for a size in the locked colour, if it can be ordered.
function sizeVariant(sizeId) {
    return confirmModalState.variants.find(v =>
        v.size_id == sizeId && v.color_id == confirmModalState.colorId && v.is_available == 1) || null;
}

function renderSizeQty() {
    const grid = document.getElementById('sizeQtyGrid');
    grid.innerHTML = '';
    confirmModalState.sizes.forEach(size => {
        const available = !!sizeVariant(size.id);
        const qty = confirmModalState.qty[size.id] || 0;
        const cell = document.createElement('div');
        cell.className = 'size-qty-cell' + (available ? '' : ' is-unavailable') + (qty > 0 ? ' has-qty' : '');
        cell.dataset.sizeId = size.id;

        const label = document.createElement('span');
        label.className = 'size-qty-label';
        label.textContent = size.name;
        cell.appendChild(label);

        const stepper = document.createElement('div');
        stepper.className = 'qty-stepper';
        const minus = document.createElement('button');
        minus.type = 'button';
        minus.dataset.step = '-1';
        minus.textContent = '−';
        minus.setAttribute('aria-label', tr('view_design.modal.fewer', { size: size.name }, 'Fewer ' + size.name));
        minus.disabled = !available || qty <= 0;
        const input = document.createElement('input');
        input.type = 'number';
        input.min = '0';
        input.max = String(MAX_QTY);
        input.inputMode = 'numeric';
        input.value = String(qty);
        input.disabled = !available;
        input.setAttribute('aria-label', tr('view_design.modal.qty_for', { size: size.name }, size.name + ' quantity'));
        const plus = document.createElement('button');
        plus.type = 'button';
        plus.dataset.step = '1';
        plus.textContent = '+';
        plus.setAttribute('aria-label', tr('view_design.modal.more', { size: size.name }, 'More ' + size.name));
        plus.disabled = !available || qty >= MAX_QTY;
        stepper.append(minus, input, plus);
        cell.appendChild(stepper);

        if (!available) {
            const na = document.createElement('span');
            na.className = 'size-qty-na';
            na.textContent = tr('view_design.modal.unavailable', null, 'Not available');
            cell.appendChild(na);
        }
        grid.appendChild(cell);
    });
}

function setSizeQty(sizeId, qty) {
    qty = Math.max(0, Math.min(MAX_QTY, parseInt(qty, 10) || 0));
    confirmModalState.qty[sizeId] = qty;
    const cell = document.querySelector('.size-qty-cell[data-size-id="' + sizeId + '"]');
    if (cell) {
        cell.classList.toggle('has-qty', qty > 0);
        const input = cell.querySelector('input');
        if (input && document.activeElement !== input) input.value = String(qty);
        cell.querySelector('[data-step="-1"]').disabled = qty <= 0;
        cell.querySelector('[data-step="1"]').disabled  = qty >= MAX_QTY;
    }
    document.getElementById('confirmError').style.display = 'none';
    updateConfirmPrices();
}

// The sizes with a quantity, in size order, each priced the way the cart
// prices its line: the variant's cost at that line's quantity tier, plus the
// design fee.
function chosenLines() {
    const selectedOpt = document.querySelector('.product-option.selected');
    const productName = selectedOpt ? (selectedOpt.dataset.productName || '') : '';
    const category    = window.Pricing ? Pricing.categoryFor('', productName) : 'tshirt';
    const fee         = confirmModalState.designFee || 0;
    return confirmModalState.sizes
        .filter(size => (confirmModalState.qty[size.id] || 0) > 0 && sizeVariant(size.id))
        .map(size => {
            const qty  = confirmModalState.qty[size.id];
            const cost = window.Pricing
                ? Pricing.variantCost(confirmModalState.variants, size.id, confirmModalState.colorId, confirmModalState.basePrice)
                : confirmModalState.basePrice;
            const unit = (window.Pricing ? Pricing.unitPrice(cost, category, qty) : cost) + fee;
            return { sizeId: size.id, name: size.name, qty: qty, unit: unit, total: unit * qty };
        });
}

function updateConfirmPrices() {
    const lines = chosenLines();
    const box = document.getElementById('confirmLines');
    box.innerHTML = '';
    if (!lines.length) {
        const empty = document.createElement('p');
        empty.className = 'confirm-lines-empty';
        empty.textContent = tr('view_design.modal.pick_sizes', null, 'Choose how many of each size you want.');
        box.appendChild(empty);
    }
    lines.forEach(line => {
        const row = document.createElement('div');
        row.className = 'popup-price-row';
        const left = document.createElement('span');
        left.textContent = tr('view_design.modal.line', { size: line.name, qty: line.qty, price: '€' + line.unit.toFixed(2) },
            line.name + ' × ' + line.qty + ' · €' + line.unit.toFixed(2) + ' each');
        const right = document.createElement('span');
        right.textContent = '€' + line.total.toFixed(2);
        row.append(left, right);
        box.appendChild(row);
    });
    const items = lines.reduce((n, l) => n + l.qty, 0);
    const total = lines.reduce((sum, l) => sum + l.total, 0);
    document.getElementById('confirmTotalLabel').textContent = totalLabel(items, 'view_design.modal.total');
    document.getElementById('confirmTotal').textContent = '€' + total.toFixed(2);
    document.getElementById('doAddToCartBtn').disabled = items === 0;
}

function showConfirmError(msg) {
    const el = document.getElementById('confirmError');
    el.textContent = msg;
    el.style.display = 'block';
}

function closeConfirmCart() {
    document.getElementById('confirmCartModal').classList.remove('active');
}

// One cart line per size. They go one after another so a failure is
// reported against its size; sizes already added are cleared from the grid,
// so pressing the button again only retries what didn't go in.
async function doAddToCart() {
    const lines = chosenLines();
    if (!lines.length) { showConfirmError(tr('view_design.modal.pick_sizes', null, 'Choose how many of each size you want.')); return; }

    // Spinner rather than swapping the label: the text change resized the
    // button mid-click and shifted the dialog under the cursor.
    const btn = document.getElementById('doAddToCartBtn');
    UI.loading(btn, true);

    const failed = [];
    for (const line of lines) {
        const payload = {
            premade_design_id: confirmModalState.premadeDesignId,
            product_id:  confirmModalState.productId,
            size_id:     line.sizeId,
            color_id:    confirmModalState.colorId,
            quantity:    line.qty,
            design_positions: designPositions
        };
        try {
            const r = await fetch('/cart/add', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            });
            const data = await r.json().catch(() => ({}));
            if (data.requireLogin) { redirectToLoginWithPendingCart(payload); return; }
            if (r.ok && (data.success || data.cart_item_id)) {
                confirmModalState.qty[line.sizeId] = 0;
            } else {
                failed.push(line.name + ': ' + (data.error || tr('studio.cart.error_generic', null, 'Failed to add to cart.')));
            }
        } catch (e) {
            failed.push(line.name + ': ' + tr('studio.cart.error_generic', null, 'Failed to add to cart.'));
        }
    }

    UI.loading(btn, false);
    if (!failed.length) {
        closeConfirmCart();
        showCartSuccessNotification();
        return;
    }
    renderSizeQty();
    updateConfirmPrices();
    const addedSome = failed.length < lines.length;
    showConfirmError((addedSome ? tr('view_design.modal.partial', null, 'The other sizes were added. Not added:') + ' ' : '') + failed.join(' · '));
}

// Steppers and typed quantities in the size grid (bound once the page has
// loaded: the pop-up is printed after this script, see $overlays).
document.addEventListener('DOMContentLoaded', function () {
    const grid = document.getElementById('sizeQtyGrid');
    grid.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-step]');
        if (!btn || btn.disabled) return;
        const sizeId = btn.closest('.size-qty-cell').dataset.sizeId;
        setSizeQty(sizeId, (confirmModalState.qty[sizeId] || 0) + parseInt(btn.dataset.step, 10));
    });
    grid.addEventListener('input', function (e) {
        if (e.target.tagName !== 'INPUT') return;
        setSizeQty(e.target.closest('.size-qty-cell').dataset.sizeId, e.target.value);
    });
    grid.addEventListener('change', function (e) {
        if (e.target.tagName !== 'INPUT') return;
        const sizeId = e.target.closest('.size-qty-cell').dataset.sizeId;
        e.target.value = String(confirmModalState.qty[sizeId] || 0);
    });
});

// Was a hand-rolled div with inline styles and its own timer; the shared toast
// keeps the "go to cart" follow-up and matches every other message on the site.
function showCartSuccessNotification() {
    UI.success(window.I18N.t('studio.cart.added') || 'Added to cart', {
        action: { label: window.I18N.t('studio.cart.go_to_cart') || 'Go to cart', href: '/cart' }
    });
}

// ==================== INITIALIZATION ====================
document.addEventListener('DOMContentLoaded', function() {
    // Initialize design element
    const designElement = document.getElementById('designElement');
    const designAreaEl = document.getElementById('designArea');
    if (designElement) {
        if (isFixedDesign && designAreaEl) {
            setTimeout(() => {
                const areaW = designAreaEl.offsetWidth;
                const areaH = designAreaEl.offsetHeight;
                if (areaW > 0) {
                    const px = (savedDesignPos.x / 100) * (areaW / 2);
                    const py = (savedDesignPos.y / 100) * (areaH / 2);
                    const ps = (savedDesignPos.size / 100) * areaW;
                    designElement.style.width = ps + 'px';
                    designElement.style.height = ps + 'px';
                    designElement.style.transform = `translate(calc(-50% + ${px}px), calc(-50% + ${py}px))`;
                    designElement.setAttribute('data-x', px);
                    designElement.setAttribute('data-y', py);
                }
            }, 50);
        } else {
            designElement.style.width = '160px';
            designElement.style.height = '160px';
        }
    }
    // Initialize preview color tint for first product
    const firstProductId = premadeDesignData.productId;
    const firstPreviewColor = document.querySelector('input[name="preview_color_' + firstProductId + '"]:checked');
    if (firstPreviewColor && firstPreviewColor.dataset.hex) {
        applyColorTint(firstPreviewColor.dataset.hex);
        updatePreviewSizeChips(firstProductId, firstPreviewColor.value);
    }
    // Preview color swatch change handler
    document.querySelectorAll('input[name^="preview_color_"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.dataset.hex) applyColorTint(this.dataset.hex);
            const prodId = this.name.replace('preview_color_', '');
            updatePreviewSizeChips(prodId, this.value);
            updatePrice();
        });
    });
    updatePrice();

    // Initialize drag & resize (only for non-fixed designs)
    if (!isFixedDesign) {
        initDesignInteraction();
    }
    // Update view buttons based on available product images
    updateViewButtons();
    // Click away to hide design controls for cleaner preview
    const designArea = document.getElementById('designArea');
    if (designArea) {
        designArea.addEventListener('click', function(e) {
            designArea.classList.remove('inactive');
        });
    }
    document.addEventListener('click', function(e) {
        if (designArea && !designArea.contains(e.target) && 
            !e.target.closest('.design-controls') && 
            !e.target.closest('.side-toggle')) {
            designArea.classList.add('inactive');
        }
    });

    // --- Second design logic ---
    const addSecondDesign = document.getElementById('addSecondDesign');
    const secondDesignUpload = document.getElementById('secondDesignUpload');
    const oppositeSideLabel = document.getElementById('oppositeSideLabel');
    const secondSideUploadLabel = document.getElementById('secondSideUploadLabel');
    let mainSide = 'front';
    function updateOppositeLabel() {
        if (mainSide === 'front') {
            oppositeSideLabel.textContent = window.I18N.t('view_design.side.back');
            secondSideUploadLabel.textContent = window.I18N.t('view_design.side.back_cap');
        } else {
            oppositeSideLabel.textContent = window.I18N.t('view_design.side.front');
            secondSideUploadLabel.textContent = window.I18N.t('view_design.side.front_cap');
        }
    }
    document.getElementById('chooseFrontBtn').addEventListener('click', function() {
        mainSide = 'front';
        this.classList.add('active');
        document.getElementById('chooseBackBtn').classList.remove('active');
        updateOppositeLabel();
    });
    document.getElementById('chooseBackBtn').addEventListener('click', function() {
        mainSide = 'back';
        this.classList.add('active');
        document.getElementById('chooseFrontBtn').classList.remove('active');
        updateOppositeLabel();
    });
    addSecondDesign.addEventListener('change', function() {
        if (this.checked) {
            secondDesignUpload.style.display = '';
        } else {
            secondDesignUpload.style.display = 'none';
            document.getElementById('secondDesignFile').value = '';
            document.getElementById('secondDesignPreview').innerHTML = '';
        }
        updatePrice();
    });
    document.getElementById('secondDesignFile').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const preview = document.getElementById('secondDesignPreview');
        if (file) {
            const reader = new FileReader();
            reader.onload = function(evt) {
                preview.innerHTML = '<img src="' + evt.target.result + '" style="max-width:120px;max-height:120px;border:1px solid #ccc;border-radius:6px;">';
            };
            reader.readAsDataURL(file);
        } else {
            preview.innerHTML = '';
        }
    });
});
