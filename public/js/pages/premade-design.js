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

        // Reset product color filter
        document.getElementById('mockupProduct').style.filter = 'none';
        document.getElementById('mockupContainer').classList.remove('dark-bg');

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
        colorContainer.innerHTML = '<p class="no-variants">No colors available for this size</p>';
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
    const unitRetail = window.Pricing
        ? Pricing.unitPrice(selectedBasePrice, category, qty)
        : selectedBasePrice;

    document.getElementById('basePrice').textContent = '€' + unitRetail.toFixed(2);
    // Second design cost (front + back print)
    let secondDesignCost = 0;
    if (document.getElementById('addSecondDesign').checked) {
        secondDesignCost = designPrice;
        document.getElementById('secondDesignRow').style.display = 'flex';
    } else {
        document.getElementById('secondDesignRow').style.display = 'none';
    }
    const total = unitRetail + designPrice + secondDesignCost;
    document.getElementById('totalPrice').textContent = '€' + total.toFixed(2);
}

// ==================== CART ====================
function changeQuantity(delta) {
    const input = document.getElementById('quantity');
    let value = parseInt(input.value) + delta;
    if (value < 1) value = 1;
    if (value > 99) value = 99;
    input.value = value;
    // Quantity drives the pricing tier — refresh the displayed price.
    if (typeof updatePrice === 'function') updatePrice();
}

// ==================== CONFIRM CART MODAL (shop_custom style) ====================
let confirmModalState = {
    productId: null,
    premadeDesignId: premadeDesignData.designId,
    designName: premadeDesignData.designName,
    designFee: premadeDesignData.designPrice,
    basePrice: 0,
    selectedColorId: null,
    selectedColorHex: null,
    selectedSizeId: null,
    quantity: 1,
    variants: [],
    sizes: [],
    colors: []
};

function addToCart() {
    if (!selectedProductId) { UI.error('Please select a product.'); return; }

    // Get the currently previewed color
    const previewColorRadio = document.querySelector('input[name="preview_color_' + selectedProductId + '"]:checked');
    const previewColorHex   = previewColorRadio ? previewColorRadio.dataset.hex  : null;
    const previewColorId    = previewColorRadio ? previewColorRadio.value         : null;

    confirmModalState.productId      = selectedProductId;
    confirmModalState.basePrice      = selectedBasePrice;
    confirmModalState.selectedColorId  = null;
    confirmModalState.selectedSizeId   = null;
    confirmModalState.selectedColorHex = null;
    confirmModalState.quantity         = parseInt(document.getElementById('quantity').value) || 1;

    // Set preview in modal
    const productImg = document.getElementById('mockupProduct');
    document.getElementById('confirmProductImg').src = productImg ? productImg.src : '';
    document.getElementById('confirmProductImg').style.filter = productImg ? productImg.style.filter : '';

    // Set design overlay in modal preview
    const designEl  = document.getElementById('designElement');
    const designImg = document.getElementById('mockupDesign');
    const mockup    = document.getElementById('mockupContainer');
    const cDesignImg = document.getElementById('confirmDesignImg');
    if (designEl && designImg && designImg.src && mockup) {
        // Express the design's placement as a RATIO of the mockup, then apply
        // those ratios to the modal's own box. The previous version scaled by a
        // hardcoded 130px while .confirm-mockup-wrap is 110px, so the design
        // came out ~18% too big and pushed down-right; percentages can't drift
        // out of sync with the CSS that way. Measuring the live element (rather
        // than the saved position) also keeps this correct for designs the
        // shopper is allowed to drag.
        const mRect  = mockup.getBoundingClientRect();
        const elRect = designEl.getBoundingClientRect();
        if (mRect.width > 0 && mRect.height > 0) {
            const leftPct = ((elRect.left - mRect.left) + elRect.width  / 2) / mRect.width  * 100;
            const topPct  = ((elRect.top  - mRect.top)  + elRect.height / 2) / mRect.height * 100;
            const widthPct = (elRect.width / mRect.width) * 100;

            cDesignImg.src = designImg.src;
            cDesignImg.style.display = 'block';
            cDesignImg.style.width = widthPct + '%';
            cDesignImg.style.left  = leftPct + '%';
            cDesignImg.style.top   = topPct + '%';
        } else {
            cDesignImg.style.display = 'none';
        }
    } else {
        cDesignImg.style.display = 'none';
    }

    const productOption = document.querySelector('.product-option.selected');
    document.getElementById('confirmProductName').textContent = productOption ? productOption.dataset.productName : '';
    document.getElementById('confirmDesignLine').textContent  = 'Design: ' + confirmModalState.designName;
    document.getElementById('confirmQty').value = confirmModalState.quantity;
    document.getElementById('confirmError').style.display = 'none';
    document.getElementById('confirmSizeLine').textContent  = '';
    document.getElementById('confirmColorLine').textContent = '';

    updateConfirmPrices();

    // Fetch variants for this product
    fetchConfirmVariants(selectedProductId, previewColorId);

    document.getElementById('confirmCartModal').classList.add('active');
}

function fetchConfirmVariants(productId, preSelectColorId) {
    fetch('/api/product-variants/' + productId)
        .then(r => r.json())
        .then(data => {
            confirmModalState.variants = data.variants || [];
            confirmModalState.sizes    = data.sizes    || [];
            confirmModalState.colors   = data.colors   || [];
            renderConfirmColors(preSelectColorId);
            renderConfirmSizes();
        })
        .catch(() => {
            document.getElementById('confirmError').textContent = 'Failed to load product options.';
            document.getElementById('confirmError').style.display = 'block';
        });
}

function renderConfirmColors(preSelectColorId) {
    const container = document.getElementById('cartColorOptions');
    container.innerHTML = '';
    confirmModalState.colors.forEach(color => {
        const hasVariant = confirmModalState.variants.some(v => v.color_id == color.id && v.is_available);
        const hex = color.hex || '#ccc';
        const isWhite = hex.toLowerCase() === '#ffffff' || hex.toLowerCase() === '#fff';
        const btn = document.createElement('button');
        btn.className = 'cart-color-btn';
        btn.dataset.colorId = color.id;
        btn.dataset.colorHex = hex;
        btn.title = color.name;
        btn.style.cssText = `width:36px;height:36px;border-radius:50%;border:3px solid #ddd;cursor:pointer;background:${hex};transition:all 0.2s;box-shadow:${isWhite ? 'inset 0 0 0 1px #ccc' : 'none'};`;
        if (!hasVariant) { btn.style.opacity = '0.35'; btn.disabled = true; btn.style.cursor = 'not-allowed'; }
        btn.onclick = function() { if (!this.disabled) selectConfirmColor(color.id, hex, color.name); };
        container.appendChild(btn);
    });
    // Pre-select preview color if available
    if (preSelectColorId) {
        const match = confirmModalState.colors.find(c => c.id == preSelectColorId);
        if (match) {
            const hasVariant = confirmModalState.variants.some(v => v.color_id == match.id && v.is_available);
            if (hasVariant) {
                selectConfirmColor(match.id, match.hex, match.name);
                return;
            }
        }
    }
    // Pre-select first available color
    const first = confirmModalState.colors.find(c => confirmModalState.variants.some(v => v.color_id == c.id && v.is_available));
    if (first) selectConfirmColor(first.id, first.hex, first.name);
}

function renderConfirmSizes() {
    const container = document.getElementById('cartSizeOptions');
    container.innerHTML = '';
    confirmModalState.sizes.forEach(size => {
        const hasVariant = confirmModalState.variants.some(v => v.size_id == size.id &&
            (!confirmModalState.selectedColorId || v.color_id == confirmModalState.selectedColorId) && v.is_available);
        const btn = document.createElement('button');
        btn.className = 'cart-size-btn';
        btn.textContent = size.name + (size.modifier > 0 ? ' (+$' + parseFloat(size.modifier).toFixed(2) + ')' : '');
        btn.dataset.sizeId = size.id;
        btn.dataset.modifier = size.modifier || 0;
        btn.style.cssText = 'padding:8px 16px;border:2px solid #ddd;border-radius:20px;background:#fff;cursor:pointer;font-weight:500;font-size:0.85rem;transition:all 0.2s;';
        if (!hasVariant) { btn.style.opacity = '0.35'; btn.disabled = true; btn.style.cursor = 'not-allowed'; }
        btn.onclick = function() { if (!this.disabled) selectConfirmSize(size.id, parseFloat(this.dataset.modifier)); };
        container.appendChild(btn);
    });
}

function selectConfirmColor(colorId, colorHex, colorName) {
    confirmModalState.selectedColorId  = colorId;
    confirmModalState.selectedColorHex = colorHex;

    // Update swatch borders
    document.querySelectorAll('.cart-color-btn').forEach(btn => {
        btn.style.borderColor  = btn.dataset.colorId == colorId ? '#333' : '#ddd';
        btn.style.boxShadow    = btn.dataset.colorId == colorId ? '0 0 0 2px #15130E' : (btn.dataset.colorHex?.toLowerCase() === '#ffffff' ? 'inset 0 0 0 1px #ccc' : 'none');
        btn.style.transform    = btn.dataset.colorId == colorId ? 'scale(1.15)' : 'scale(1)';
    });

    // Apply tint to modal preview
    document.getElementById('confirmProductImg').style.filter = buildColorFilter(colorHex);

    // Apply tint to main mockup too
    applyColorTint(colorHex);

    // Update color line
    document.getElementById('confirmColorLine').textContent = 'Color: ' + colorName;

    // Update size availability
    updateConfirmSizeAvailability();
}

function selectConfirmSize(sizeId, modifier) {
    confirmModalState.selectedSizeId = sizeId;

    document.querySelectorAll('.cart-size-btn').forEach(btn => {
        const sel = btn.dataset.sizeId == sizeId;
        btn.style.borderColor = sel ? '#15130E' : '#ddd';
        btn.style.background  = sel ? 'var(--ink)' : '#fff';
        btn.style.color       = sel ? '#fff' : '#333';
    });

    const sizeBtn = document.querySelector('.cart-size-btn[data-size-id="' + sizeId + '"]');
    document.getElementById('confirmSizeLine').textContent = 'Size: ' + (sizeBtn ? sizeBtn.textContent : '');

    updateConfirmPrices(modifier);
    updateConfirmColorAvailability();
}

function updateConfirmSizeAvailability() {
    document.querySelectorAll('.cart-size-btn').forEach(btn => {
        const available = confirmModalState.variants.some(v =>
            v.size_id == btn.dataset.sizeId &&
            (!confirmModalState.selectedColorId || v.color_id == confirmModalState.selectedColorId) &&
            v.is_available
        );
        btn.style.opacity = available ? '1' : '0.35';
        btn.disabled      = !available;
        btn.style.cursor  = available ? 'pointer' : 'not-allowed';
        // Deselect if current size no longer available
        if (!available && confirmModalState.selectedSizeId == btn.dataset.sizeId) {
            confirmModalState.selectedSizeId = null;
            document.getElementById('confirmSizeLine').textContent = '';
        }
    });
    // Auto-select first available size
    if (!confirmModalState.selectedSizeId) {
        const firstAvail = document.querySelector('.cart-size-btn:not([disabled])');
        if (firstAvail) selectConfirmSize(firstAvail.dataset.sizeId, parseFloat(firstAvail.dataset.modifier) || 0);
    }
}

function updateConfirmColorAvailability() {
    document.querySelectorAll('.cart-color-btn').forEach(btn => {
        const available = confirmModalState.variants.some(v =>
            v.color_id == btn.dataset.colorId &&
            (!confirmModalState.selectedSizeId || v.size_id == confirmModalState.selectedSizeId) &&
            v.is_available
        );
        btn.style.opacity = available ? '1' : '0.35';
        btn.disabled      = !available;
        btn.style.cursor  = available ? 'pointer' : 'not-allowed';
    });
}

function closeConfirmCart() {
    document.getElementById('confirmCartModal').classList.remove('active');
}

function adjustConfirmQty(delta) {
    const input = document.getElementById('confirmQty');
    let v = parseInt(input.value) + delta;
    if (v < 1) v = 1;
    if (v > 99) v = 99;
    input.value = v;
    confirmModalState.quantity = v;
    updateConfirmPrices();
}

function updateConfirmPrices(sizeModifier) {
    const mod       = sizeModifier !== undefined ? sizeModifier : 0;
    const qty       = parseInt(document.getElementById('confirmQty').value) || 1;
    // basePrice + size modifier is the SUPPLIER cost; apply the quantity-tiered
    // margin to match the server. The premade design fee is added per unit.
    const selectedOpt = document.querySelector('.product-option.selected');
    const productName = selectedOpt ? (selectedOpt.dataset.productName || '') : '';
    const category  = window.Pricing ? Pricing.categoryFor('', productName) : 'tshirt';
    const supplier  = (confirmModalState.basePrice || 0) + mod;
    const unitPrice = window.Pricing ? Pricing.unitPrice(supplier, category, qty) : supplier;
    const designFee = confirmModalState.designFee || 0;
    const total     = (unitPrice + designFee) * qty;
    document.getElementById('confirmBase').textContent      = '€' + (unitPrice * qty).toFixed(2);
    document.getElementById('confirmDesignFee').textContent = '+€' + designFee.toFixed(2);
    document.getElementById('confirmTotal').textContent     = '€' + total.toFixed(2);
}

function doAddToCart() {
    const errorEl = document.getElementById('confirmError');
    errorEl.style.display = 'none';

    if (!confirmModalState.selectedColorId) {
        errorEl.textContent = 'Please select a color.';
        errorEl.style.display = 'block';
        return;
    }
    if (!confirmModalState.selectedSizeId) {
        errorEl.textContent = 'Please select a size.';
        errorEl.style.display = 'block';
        return;
    }

    // Spinner rather than swapping the label to "Adding…": the text change
    // resized the button mid-click and shifted the dialog under the cursor.
    const btn = document.getElementById('doAddToCartBtn');
    UI.loading(btn, true);

    const cartData = {
        premade_design_id: confirmModalState.premadeDesignId,
        product_id:  confirmModalState.productId,
        size_id:     confirmModalState.selectedSizeId,
        color_id:    confirmModalState.selectedColorId,
        quantity:    parseInt(document.getElementById('confirmQty').value) || 1,
        design_positions: designPositions
    };

    fetch('/cart/add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(cartData)
    })
    .then(r => r.json())
    .then(data => {
        if (data.requireLogin) { redirectToLoginWithPendingCart(cartData); return; }
        UI.loading(btn, false);
        if (data.success || data.cart_item_id) {
            closeConfirmCart();
            showCartSuccessNotification();
        } else {
            throw new Error(data.error || window.I18N.t('studio.cart.error_generic'));
        }
    })
    .catch(err => {
        UI.loading(btn, false);
        errorEl.textContent = err.message;
        errorEl.style.display = 'block';
    });
}

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
    // Color → available size IDs per product
    const previewColorSizes = premadeDesignData.previewColorSizes;

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
        });
    });

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
