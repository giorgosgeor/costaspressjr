/* custom-product.js — from views/shop/custom_product.php, loaded where the inline script used to run. */
const customProductData = JSON.parse(document.getElementById('custom-product-data').textContent);

const product = customProductData.product;
const colors = customProductData.colors;
const sizes = customProductData.sizes;
const colorSizeMatrix = customProductData.colorSizeMatrix; // { color_id: [size_id, ...] }
const variantCosts = customProductData.variantCosts || {};  // { color_id: { size_id: supplier cost } }
const thumbnails = customProductData.thumbnails;

let selectedColor = null;
let selectedSize = null;

document.addEventListener('DOMContentLoaded', function() {
    renderColors();
    // Preselect first color and render sizes
    if (colors && colors.length > 0) {
        selectedColor = colors[0].id;
        renderSizes(selectedColor);
        // Highlight first swatch
        if (typeof setSelectedSwatch === 'function') {
            setSelectedSwatch(selectedColor);
        }
        syncVariantPrice();
    }

    // Color selection logic
    const colorSwatches = document.getElementById('colorSwatches');
    if (colorSwatches) {
        colorSwatches.addEventListener('click', function(e) {
            if (e.target.classList.contains('color-swatch')) {
                selectedColor = e.target.getAttribute('data-color-id');
                renderSizes(selectedColor);
                setSelectedSwatch(selectedColor);
                syncVariantPrice();
                // Optionally, apply color tint if needed
                const color = colors.find(c => c.id == selectedColor);
                if (color && typeof applyColorTint === 'function') {
                    applyColorTint(color.hex);
                }
            }
        });
        colorSwatches.addEventListener('mouseover', function(e) {
            if (e.target.classList.contains('color-swatch')) {
                const hoverColor = e.target.getAttribute('data-color-id');
                renderSizes(hoverColor);
            }
        });
        colorSwatches.addEventListener('mouseout', function(e) {
            if (e.target.classList.contains('color-swatch')) {
                renderSizes(selectedColor);
                syncVariantPrice();
            }
        });
    }

    // Modal logic (fix: ensure listeners are attached)
    const checkSizesLink = document.getElementById('checkSizesLink');
    if (checkSizesLink) {
        checkSizesLink.addEventListener('click', function(e) {
            e.preventDefault();
            renderSizeMatrixTable();
            const sizeMatrixModal = document.getElementById('sizeMatrixModal');
            if (sizeMatrixModal) sizeMatrixModal.style.display = 'flex';
        });
    }
    const closeMatrixModal = document.getElementById('closeMatrixModal');
    if (closeMatrixModal) {
        closeMatrixModal.addEventListener('click', function() {
            const sizeMatrixModal = document.getElementById('sizeMatrixModal');
            if (sizeMatrixModal) sizeMatrixModal.style.display = 'none';
        });
    }

    // --- Thumbnails: show vertically to the left, hover to preview, click to select ---
    const thumbContainer = document.getElementById('productThumbnails');
    if (thumbContainer) {
        let thumbHtml = '';
        // Main/front image
        if (product.image_path) {
            thumbHtml += `<img src="/${product.image_path}" data-view="front" style="width:54px;height:54px;object-fit:cover;cursor:pointer;border-radius:6px;border:2px solid #eee;margin-bottom:8px;" title="Front">`;
        }
        // Back image
        if (product.back_image_path) {
            thumbHtml += `<img src="/${product.back_image_path}" data-view="back" style="width:54px;height:54px;object-fit:cover;cursor:pointer;border-radius:6px;border:2px solid #eee;margin-bottom:8px;" title="Back">`;
        }
        // Left sleeve
        if (product.left_sleeve_image_path) {
            thumbHtml += `<img src="/${product.left_sleeve_image_path}" data-view="left-sleeve" style="width:54px;height:54px;object-fit:cover;cursor:pointer;border-radius:6px;border:2px solid #eee;margin-bottom:8px;" title="Left Sleeve">`;
        }
        // Right sleeve
        if (product.right_sleeve_image_path) {
            thumbHtml += `<img src="/${product.right_sleeve_image_path}" data-view="right-sleeve" style="width:54px;height:54px;object-fit:cover;cursor:pointer;border-radius:6px;border:2px solid #eee;margin-bottom:8px;" title="Right Sleeve">`;
        }
        thumbContainer.innerHTML = thumbHtml;
        // Thumbnail hover/preview and click/select logic
        let lastSelectedThumb = null;
        let lastMainImageSrc = document.getElementById('mainProductImage').src;
        document.querySelectorAll('#productThumbnails img').forEach(img => {
            img.addEventListener('mouseover', function() {
                document.getElementById('mainProductImage').src = this.src;
                // Reapply color tint after changing image
                const color = colors.find(c => c.id == selectedColor);
                if (color) applyColorTint(color.hex);
            });
            img.addEventListener('mouseout', function() {
                if (lastSelectedThumb) {
                    document.getElementById('mainProductImage').src = lastSelectedThumb.src;
                    const color = colors.find(c => c.id == selectedColor);
                    if (color) applyColorTint(color.hex);
                } else {
                    document.getElementById('mainProductImage').src = lastMainImageSrc;
                }
            });
            img.addEventListener('click', function() {
                lastSelectedThumb = this;
                lastMainImageSrc = this.src;
                document.getElementById('mainProductImage').src = this.src;
                // Reapply color tint after changing image
                const color = colors.find(c => c.id == selectedColor);
                if (color) applyColorTint(color.hex);
            });
        });
    }
});


function setSelectedSwatch(colorId) {
    // Selection is a class, so the ring is described once in CSS instead of
    // being an inline outline this function has to keep in sync with the
    // palette. aria-pressed makes the state audible to a screen reader.
    document.querySelectorAll('.color-swatch').forEach(swatch => {
        const on = swatch.getAttribute('data-color-id') == colorId;
        swatch.classList.toggle('is-selected', on);
        swatch.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
}

function renderColors() {
    // Buttons, not spans: they were unreachable by keyboard and had no role.
    // Only the colour itself stays inline — every dimension moved to CSS so
    // the swatch can carry a 44px touch target on a phone without this
    // function knowing anything about viewport size.
    let html = '';
    colors.forEach(color => {
        html += `<button type="button" class="color-swatch" data-color-id="${color.id}"`
             +  ` title="${color.name}" aria-label="${color.name}" aria-pressed="false"`
             +  ` style="--chip:${color.hex}"></button>`;
    });
    document.getElementById('colorSwatches').innerHTML = html;
}

function renderSizes(colorId) {
    // Sizes were <span>s that nothing ever selected: no click handler added
    // the .selected class the submit path looked for, so tapping a size did
    // nothing and the first available one was always used. They are buttons
    // now, with a real selection that survives a colour change when the size
    // is still available in the new colour.
    let html = '';
    const availableSizes = colorSizeMatrix[colorId] || [];
    if (!availableSizes.includes(selectedSize)) { selectedSize = null; }
    sizes.forEach(size => {
        const enabled = availableSizes.includes(size.id);
        if (enabled && selectedSize === null) { selectedSize = size.id; }
        const on = enabled && selectedSize === size.id;
        html += `<button type="button" class="size-chip${on ? ' is-selected' : ''}"`
             +  ` data-size-id="${size.id}" data-size-name="${size.size_name}"`
             +  ` aria-pressed="${on ? 'true' : 'false'}"${enabled ? '' : ' disabled'}>`
             +  `${size.size_name}</button>`;
    });
    document.getElementById('sizeOptions').innerHTML = html;
}

// NOTE: selectedSize is already declared with `let` at the top of this
// script, so it is deliberately NOT redeclared here — doing so threw
// "Identifier 'selectedSize' has already been declared", which is a
// SyntaxError and therefore killed the ENTIRE script before it ran. That
// is why the colour swatches and size chips rendered as empty containers.
document.addEventListener('click', function (e) {
    const chip = e.target.closest('#sizeOptions .size-chip');
    if (!chip || chip.disabled) return;
    selectedSize = parseInt(chip.getAttribute('data-size-id'), 10);
    document.querySelectorAll('#sizeOptions .size-chip').forEach(function (c) {
        const on = c === chip;
        c.classList.toggle('is-selected', on);
        c.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    syncVariantPrice();
});

// The price follows the colour and size picked: a black or 3XL tee costs more
// than the white one the page opens with, and the cart charges the variant's
// cost. lib/price-tiers.js re-renders with it (and the phone action bar
// mirrors that).
function syncVariantPrice() {
    const box  = document.getElementById('productPriceTiers');
    const cost = (variantCosts[selectedColor] || {})[selectedSize];
    if (box && window.PriceTiers && cost != null) {
        window.PriceTiers.update(box, { supplierCost: cost });
    }
}

// Move modal logic inside DOMContentLoaded
// (No extra closing brace here)

function renderSizeMatrixTable() {
    // Build a table like the screenshot
    let html = '<table style="width:100%;border-collapse:collapse;text-align:center;">';
    html += `<tr><th style="text-align:left;padding:6px 8px;">${window.I18N.t('custom_product.matrix.color_col')}</th>`;
    sizes.forEach(size => {
        html += `<th style="padding:6px 8px;">${size.size_name}</th>`;
    });
    html += '</tr>';
    colors.forEach(color => {
        html += `<tr><td style="text-align:left;padding:6px 8px;">${color.name}</td>`;
        sizes.forEach(size => {
            const available = (colorSizeMatrix[color.id] || []).includes(size.id);
            html += `<td>${available ? '✓' : '<span style=\'color:#bbb;\'>N/A</span>'}</td>`;
        });
        html += '</tr>';
    });
    html += '</table>';
    document.getElementById('sizeMatrixTable').innerHTML = html;
}

function applyColorTint(hex) {
    if (!hex) return;
    hex = hex.trim();
    if (!hex.startsWith('#')) hex = '#' + hex;
    const img = document.getElementById('mainProductImage');
    if (!img) return;
    const hsl = hexToHSL(hex);
    const hexLower = hex.toLowerCase();
    const isWhite = hexLower === '#ffffff' || hexLower === '#fff' || hsl.l > 95;
    const isVeryLight = hsl.l > 85;
    const isBlack = hexLower === '#000000' || hexLower === '#000' || hsl.l < 10;
    const isGray = hsl.s < 10;
    const tintOverride = window.CostasTint && window.CostasTint.getOverride(hex);
    if (tintOverride) {
        img.style.filter = tintOverride;
    } else if (isWhite) {
        img.style.filter = 'saturate(0) brightness(2) contrast(0.8)';
    } else if (isBlack) {
        img.style.filter = 'saturate(0) brightness(0.65) contrast(1.1)';
    } else if (isGray) {
        const brightness = 0.2 + (hsl.l / 100) * 1.5;
        img.style.filter = `saturate(0) brightness(${brightness})`;
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
        img.style.filter = `sepia(1) saturate(${saturate}) hue-rotate(${hueRotate}deg) brightness(${brightness})`;
    }
}

function hexToHSL(H) {
    // Convert hex to RGB first
    let r = 0, g = 0, b = 0;
    if (H.length == 4) {
        r = '0x' + H[1] + H[1];
        g = '0x' + H[2] + H[2];
        b = '0x' + H[3] + H[3];
    } else if (H.length == 7) {
        r = '0x' + H[1] + H[2];
        g = '0x' + H[3] + H[4];
        b = '0x' + H[5] + H[6];
    }
    r /= 255; g /= 255; b /= 255;
    const max = Math.max(r, g, b), min = Math.min(r, g, b);
    let h, s, l = (max + min) / 2;
    if (max == min) {
        h = s = 0; // achromatic
    } else {
        const d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        switch (max) {
            case r: h = (g - b) / d + (g < b ? 6 : 0); break;
            case g: h = (b - r) / d + 2; break;
            case b: h = (r - g) / d + 4; break;
        }
        h /= 6;
    }
    return { h: Math.round(h * 360), s: Math.round(s * 100), l: Math.round(l * 100) };
}

// Patch event handlers for color swatches to update image color
const origDOMContentLoaded = document.onreadystatechange;
document.addEventListener('DOMContentLoaded', function() {
    renderColors();
    if (colors.length > 0) {
        selectedColor = colors[0].id;
        renderSizes(selectedColor);
        setSelectedSwatch(selectedColor);
    }
    // After setSelectedSwatch(selectedColor);
    if (colors.length > 0) {
        applyColorTint(colors[0].hex);
    }
    document.getElementById('colorSwatches').addEventListener('click', function(e) {
        if (e.target.classList.contains('color-swatch')) {
            selectedColor = e.target.getAttribute('data-color-id');
            renderSizes(selectedColor);
            setSelectedSwatch(selectedColor);
            const color = colors.find(c => c.id == selectedColor);
            if (color) applyColorTint(color.hex);
        }
    });
    document.getElementById('colorSwatches').addEventListener('mouseover', function(e) {
        if (e.target.classList.contains('color-swatch')) {
            const color = colors.find(c => c.id == e.target.getAttribute('data-color-id'));
            if (color) applyColorTint(color.hex);
            renderSizes(e.target.getAttribute('data-color-id'));
        }
    });
    document.getElementById('colorSwatches').addEventListener('mouseout', function(e) {
        if (e.target.classList.contains('color-swatch')) {
            const color = colors.find(c => c.id == selectedColor);
            if (color) applyColorTint(color.hex);
            renderSizes(selectedColor);
        }
    });
    document.getElementById('startDesigningBtn').addEventListener('click', function() {
        // Get selected color and size
        let color = selectedColor;
        // Was: compare each label's inline style.color against the string
        // 'rgb(34, 34, 34)' and take the first match. That broke the moment a
        // stylesheet set the colour instead, and it could not represent an
        // actual choice. The selected chip is now tracked directly.
        let size = null;
        const chosen = document.querySelector('#sizeOptions .size-chip.is-selected');
        if (chosen) { size = chosen.getAttribute('data-size-name'); }
        // Hand the chosen product/colour/size to the studio. Only write keys we
        // actually resolved - storing a null here lands the string "null" in
        // sessionStorage, which reads as truthy on the other side.
        sessionStorage.setItem('custom_product_id', product.id);
        if (color) { sessionStorage.setItem('custom_color', color); }
        else       { sessionStorage.removeItem('custom_color'); }
        if (size)  { sessionStorage.setItem('custom_size', size); }
        else       { sessionStorage.removeItem('custom_size'); }
        window.location.href = '/shop/custom';
    });
});

// ── Volume-pricing widget ────────────────────────────────────────────────
// Drives the live unit price / line total / tier ladder from the quantity
// stepper. All arithmetic happens in lib/price-tiers.js via window.Pricing, so
// the figures match what checkout charges.
document.addEventListener('DOMContentLoaded', function () {
    var box   = document.getElementById('productPriceTiers');
    var input = document.getElementById('tierQty');
    if (!box || !input || !window.PriceTiers) return;

    function apply() {
        var q = Math.max(1, Math.min(1000, parseInt(input.value, 10) || 1));
        input.value = q;
        window.PriceTiers.update(box, { quantity: q });
    }
    input.addEventListener('input', apply);
    input.addEventListener('change', apply);
    var minus = document.getElementById('tierQtyMinus');
    var plus  = document.getElementById('tierQtyPlus');
    if (minus) minus.addEventListener('click', function () {
        input.value = Math.max(1, (parseInt(input.value, 10) || 1) - 1); apply();
    });
    if (plus) plus.addEventListener('click', function () {
        input.value = Math.min(1000, (parseInt(input.value, 10) || 1) + 1); apply();
    });
    apply();
});
