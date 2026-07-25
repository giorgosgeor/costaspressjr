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
        if (productData.image_path) {
            thumbHtml += `<img src="/${productData.image_path}" data-view="front" style="width:54px;height:54px;object-fit:cover;cursor:pointer;border-radius:6px;border:2px solid #eee;margin-bottom:8px;" title="Front">`;
        }
        // Back image
        if (productData.back_image_path) {
            thumbHtml += `<img src="/${productData.back_image_path}" data-view="back" style="width:54px;height:54px;object-fit:cover;cursor:pointer;border-radius:6px;border:2px solid #eee;margin-bottom:8px;" title="Back">`;
        }
        // Left sleeve
        if (productData.left_sleeve_image_path) {
            thumbHtml += `<img src="/${productData.left_sleeve_image_path}" data-view="left-sleeve" style="width:54px;height:54px;object-fit:cover;cursor:pointer;border-radius:6px;border:2px solid #eee;margin-bottom:8px;" title="Left Sleeve">`;
        }
        // Right sleeve
        if (productData.right_sleeve_image_path) {
            thumbHtml += `<img src="/${productData.right_sleeve_image_path}" data-view="right-sleeve" style="width:54px;height:54px;object-fit:cover;cursor:pointer;border-radius:6px;border:2px solid #eee;margin-bottom:8px;" title="Right Sleeve">`;
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
    document.querySelectorAll('.color-swatch').forEach(swatch => {
        if (swatch.getAttribute('data-color-id') == colorId) {
            swatch.style.outline = '3px solid #007bff';
        } else {
            swatch.style.outline = '';
        }
    });
}

function renderColors() {
    let html = '';
    colors.forEach(color => {
        html += `<span class="color-swatch" data-color-id="${color.id}" title="${color.name}" style="display:inline-block;width:28px;height:28px;border-radius:6px;background:${color.hex};border:2px solid #ccc;cursor:pointer;"></span>`;
    });
    document.getElementById('colorSwatches').innerHTML = html;
}

function renderSizes(colorId) {
    let html = '';
    const availableSizes = colorSizeMatrix[colorId] || [];
    sizes.forEach(size => {
        const enabled = availableSizes.includes(size.id);
        html += `<span class="size-label" style="display:inline-block;margin-right:10px;${enabled ? 'color:#222;' : 'color:#bbb;'}">${size.size_name}</span>`;
    });
    document.getElementById('sizeOptions').innerHTML = html;
}

// Move modal logic inside DOMContentLoaded
// (No extra closing brace here)

function renderSizeMatrixTable() {
    // Build a table like the screenshot
    let html = '<table style="width:100%;border-collapse:collapse;text-align:center;">';
    html += '<tr><th style="text-align:left;padding:6px 8px;">PRODUCT COLOR</th>';
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
    if (isWhite) {
        img.style.filter = 'saturate(0) brightness(2) contrast(0.8)';
    } else if (isBlack) {
        img.style.filter = 'saturate(0) brightness(0.4) contrast(1.2)';
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
        let size = null;
        const sizeLabels = document.querySelectorAll('#sizeOptions .size-label');
        sizeLabels.forEach(label => {
            if (label.style.color === 'rgb(34, 34, 34)' || label.style.color === '#222' || label.style.color === 'color: #222;') {
                if (label.classList.contains('selected')) {
                    size = label.textContent.trim();
                }
            }
        });
        if (!size) {
            for (let label of sizeLabels) {
                if (label.style.color === 'rgb(34, 34, 34)' || label.style.color === '#222' || label.style.color === 'color: #222;') {
                    size = label.textContent.trim();
                    break;
                }
            }
        }
        // Store in sessionStorage
        sessionStorage.setItem('custom_product_id', productData.id);
        sessionStorage.setItem('custom_color', color);
        sessionStorage.setItem('custom_size', size);
        // Redirect to new route
        window.location.href = '/shop/custom_product/shop_custom';
    });
});
