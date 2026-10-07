<?php $title = $product['name'] ?? 'Customize Product'; ?>
<?php require __DIR__ . '/../layouts/customer_header.php'; ?>


<style>
.custom-product-grid {
  display: flex;
  gap: 40px;
  align-items: flex-start;
  margin-left: 40px; /* shift everything to the right */
}
.custom-product-gallery {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
}
#productThumbnails {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-right: 12px;
}
.main-image {
  margin-bottom: 0;
  background: var(--stock);
  border: 1.5px solid var(--ink);
  border-radius: var(--radius);
  padding: 12px;
}
#mainProductImage {
  max-width: 700px;
  max-height: 1100px;
  width: 100%;
  height: auto;
  object-fit: contain;
  display: block;
}
@media (max-width: 900px) {
  .custom-product-grid {
    flex-direction: column;
    margin-left: 0;
    gap: 24px;
  }
  .custom-product-gallery {
    flex-direction: row;
    justify-content: center;
    align-items: flex-start;
    width: 100%;
  }
  #productThumbnails {
    flex-direction: row;
    gap: 8px;
    margin-right: 0;
    margin-bottom: 12px;
  }
  .main-image {
    margin-bottom: 0;
    width: 100%;
    display: flex;
    justify-content: center;
  }
  #mainProductImage {
    max-width: 95vw;
    max-height: 60vh;
    width: auto;
    height: auto;
    margin: 0 auto;
  }
}
@media (max-width: 600px) {
    .custom-product-gallery {
        flex-direction: column;
        align-items: flex-start;
        width: 100%;
    }
    #productThumbnails {
        flex-direction: row;
        justify-content: flex-start;
        gap: 8px;
        margin: 0 0 8px 0;
        width: 100%;
    }
    .main-image {
        width: 100%;
        max-width: 100vw;
        display: flex;
        justify-content: flex-start;
        margin-bottom: 0;
        margin-top: 0;
        box-sizing: border-box;
    }
    #mainProductImage {
        max-width: 96vw;
        max-height: 60vw;
        width: auto;
        height: auto;
        margin-left: 0;
        margin-right: auto;
        display: block;
    }
    #colorSwatches {
        flex-wrap: wrap;
        row-gap: 8px;
        column-gap: 6px;
        width: 100%;
    }
    #sizeOptions {
        flex-wrap: wrap; 
        row-gap: 8px;
        column-gap: 8px;
        width: 100%;
    }
    .custom-product-info {
        min-width: 0 !important;
        width: 100%;
    }
}
</style>

<section class="section product-detail-section">
    <div class="container">
        <?php
            $crumbs = [[t('header.nav.home', false), '/'], [t('header.nav.shop', false), '/shop'], [(string)($product['name'] ?? t('studio.title', false)), null]];
            require __DIR__ . '/../partials/breadcrumb.php';
        ?>
        <div class="custom-product-grid">
            <div class="custom-product-gallery" id="customProductGallery">
                <div id="productThumbnails"></div>
                <div class="main-image">
                    <img id="mainProductImage" src="<?= isset($product['image_path']) ? '/' . htmlspecialchars($product['image_path']) : '/images/placeholder.svg' ?>" alt="<?= htmlspecialchars($product['name'] ?? '') ?>">
                </div>
            </div>
            <!-- Right: Product Info & Options -->
            <div class="custom-product-info" style="flex:1;min-width:320px;">
                <h1 style="font-size:2em;font-weight:700;"><?= htmlspecialchars($product['name'] ?? '') ?></h1>
                <!-- Removed rating, free delivery, and decoration sections -->
                <div style="margin-bottom:18px;">
                    <b><?= t('custom_product.colors') ?></b>
                    <div id="colorSwatches" style="display:flex;gap:6px;margin-top:6px;margin-bottom:8px;"></div>
                    <a href="#" id="checkSizesLink" style="font-size:0.98em;text-decoration:underline;"><?= t('custom_product.check_sizes') ?></a>
                </div>
                <div style="margin-bottom:18px;">
                    <span style="font-weight:400;font-size:1em; display:inline-flex; align-items:center; gap:12px; width:100%; justify-content:space-between;">
                        <b><?= t('custom_product.sizes') ?></b>
                        <?php if (!empty($product['size_chart_image'])): ?>
                        <a href="#"
                           onclick="event.preventDefault(); openSizeGuide('<?= htmlspecialchars($product['size_chart_image']) ?>', '<?= htmlspecialchars($product['name'] ?? 'Product') ?>');"
                           style="font-size:0.82em; color:#2A4FE0; text-decoration:none; font-weight:500;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="vertical-align:-2px;margin-right:4px;"><path d="M2 12h20"/><path d="M6 9v6M10 7v10M14 9v6M18 7v10"/></svg>Size guide
                        </a>
                        <?php endif; ?>
                    </span>
                    <div id="sizeOptions" style="display:flex;gap:-12px;margin-top:6px;"></div>
                <?php // A stray, unmatched STYLE end tag sat here in the original markup.
                      // The parser discards such a tag, so the elements around it still
                      // balanced and it was inert — it was NOT a mistyped closing div.
                      // Turning it into a real </div> closed .custom-product-info early,
                      // which made every block below it a flex sibling of the gallery and
                      // laid them out in a row across the desktop page. It is simply
                      // removed instead. ?>
                <style>
                .size-label {
                    font-size: 1.2em;
                    font-weight: 400;
                    background: none;
                    border: none;
                    box-shadow: none;
                    outline: none;
                    margin-left: 0;
                    border-radius: 0;
                    position: relative;
                    padding-left: 0;
                }
                .size-label:not(:first-child)::before {
                    content: '';
                    position: absolute;
                    left: -3px;
                    top: 50%;
                    transform: translateY(-50%);
                    height: 1em;
                    width: 1px;
                    background: #bbb;
                    display: inline-block;
                }
                .size-label:not(:first-child) {
                    margin-left: -20px;
                    
                }
                
                </style>
                </div>
                <!-- Price. This page previously showed none at all — a customer
                     arriving from a featured card had no idea what it cost. The
                     widget also surfaces the volume ladder, computed with the
                     same engine the server charges with. -->
                <div class="price-qty-row">
                    <label for="tierQty"><?= t('pricing.qty') ?></label>
                    <div class="quantity-controls">
                        <button type="button" class="qty-btn" id="tierQtyMinus" aria-label="-">&minus;</button>
                        <input type="number" id="tierQty" class="qty-input" value="1" min="1" max="1000" inputmode="numeric">
                        <button type="button" class="qty-btn" id="tierQtyPlus" aria-label="+">+</button>
                    </div>
                </div>
                <div class="price-tiers"
                     id="productPriceTiers"
                     data-supplier-cost="<?= htmlspecialchars((string)($product['base_price'] ?? 0)) ?>"
                     data-product-name="<?= htmlspecialchars($product['name'] ?? '') ?>"
                     data-product-slug="<?= htmlspecialchars($product['slug'] ?? '') ?>"
                     data-quantity="1"></div>

                <button id="startDesigningBtn" class="btn btn-primary" style="margin-top:18px;min-width:180px;"><?= t('custom_product.start') ?></button>
				<div style="margin-top:18px;color:#555;font-size:1em;">
					<b><?= t('custom_product.delivery') ?></b> <?= t('custom_product.delivery_expected') ?> <span id="deliveryDate"><?= htmlspecialchars($deliveryEstimate ?? '') ?></span> &bull;
				</div>
			</div>
            <!-- Size Matrix Modal -->
<div id="sizeMatrixModal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:1000;align-items:center;justify-content:center;background:rgba(0,0,0,0.35);">
    <div style="background:#fff;padding:32px 36px 32px 36px;border-radius:12px;max-width:98vw;max-height:92vh;overflow:auto;position:relative;box-shadow:0 8px 32px rgba(0,0,0,0.18);min-width:340px;">
        <button id="closeMatrixModal" style="position:absolute;top:18px;right:18px;font-size:2em;background:none;border:none;cursor:pointer;line-height:1;">&times;</button>
        <h2 style="margin-top:0;font-size:2em;font-weight:700;"><?= htmlspecialchars($product['name'] ?? 'Product') ?></h2>
        <div style="color:#444;font-size:1.1em;margin-bottom:2px;"><?= I18n::t('custom_product.matrix.availability', ['date' => date('F j, Y')]) ?></div>
        <div style="color:#888;font-size:0.98em;margin-bottom:18px;"><?= t('custom_product.matrix.na_note') ?></div>
        <div id="sizeMatrixTable"></div>
    </div>
</div>
        </div>
    </div>
		</div>

<script>
const product = <?= json_encode($product ?? []) ?>;
const colors = <?= json_encode($colors ?? []) ?>;
const sizes = <?= json_encode($sizes ?? []) ?>;
const colorSizeMatrix = <?= json_encode($colorSizeMatrix ?? []) ?>; // { color_id: [size_id, ...] }
const thumbnails = <?= json_encode($thumbnails ?? []) ?>;

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
    }

    // Color selection logic
    const colorSwatches = document.getElementById('colorSwatches');
    if (colorSwatches) {
        colorSwatches.addEventListener('click', function(e) {
            if (e.target.classList.contains('color-swatch')) {
                selectedColor = e.target.getAttribute('data-color-id');
                renderSizes(selectedColor);
                setSelectedSwatch(selectedColor);
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
});

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
// stepper. All arithmetic happens in price-tiers.js via window.Pricing, so
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
</script>
<?php require __DIR__ . '/../partials/size_guide_modal.php'; ?>
<?php // ---- Sticky action bar (phones only) ----------------------------
      // On a phone the real call to action sits below the colour row, the
      // size run, the quantity stepper and the price panel — far enough
      // down that a shopper scrolling back up to look at the garment loses
      // it entirely. Pinning price and button to the bottom edge keeps the
      // decision one thumb-reach away at all times.
      //
      // It does not duplicate any logic: the button forwards the tap to the
      // existing #startDesigningBtn, so there is exactly one code path for
      // starting a design, and the price mirrors whatever price-tiers.js
      // has rendered rather than recomputing it. ?>
<div class="mobile-action-bar" role="region" aria-label="<?= t('custom_product.start') ?>">
    <span class="mab-price">
        <span class="mab-label"><?= t('home.rate.from') ?></span>
        <span class="mab-amount" id="mabAmount">&euro;<?= number_format((float)($retailPrice ?? 0), 2) ?></span>
    </span>
    <button type="button" class="btn" id="mabStart"><?= t('custom_product.start') ?></button>
</div>
<script>
(function () {
    document.body.classList.add('has-action-bar');

    var start = document.getElementById('mabStart');
    var real  = document.getElementById('startDesigningBtn');
    if (start && real) {
        start.addEventListener('click', function () { real.click(); });
    }

    // price-tiers.js owns the per-unit figure and re-renders it whenever the
    // quantity changes. Mirroring its output keeps one source of truth; the
    // observer is needed because that block is replaced wholesale, so a
    // reference taken once would go stale on the first update.
    var panel = document.getElementById('productPriceTiers');
    var out   = document.getElementById('mabAmount');
    if (panel && out && 'MutationObserver' in window) {
        var sync = function () {
            var unit = panel.querySelector('.price-tiers-unit');
            if (!unit) return;
            var text = (unit.childNodes[0] && unit.childNodes[0].nodeValue || '').trim();
            if (text) out.textContent = text;
        };
        new MutationObserver(sync).observe(panel, { childList: true, subtree: true });
        sync();
    }
})();
</script>

<?php require __DIR__ . '/../layouts/customer_footer.php'; ?>
