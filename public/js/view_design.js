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

        // Update mockup
        updateMockupProduct();
        updateBackButtonState();

        // Show correct customization panel
        document.querySelectorAll('.product-customization').forEach(panel => {
            panel.style.display = 'none';
        });
        const customPanel = document.getElementById('customization-' + selectedProductId);
        if (customPanel) {
            customPanel.style.display = 'block';
            const firstSize = customPanel.querySelector('input[type="radio"]:checked');
            if (firstSize) {
                updateColors(firstSize);
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

function updateBackButtonState() {
    const backBtn = document.querySelector('.side-btn[data-side="back"]');
    if (currentProductBackImage) {
        backBtn.classList.remove('disabled');
        backBtn.title = 'View back of product';
    } else {
        backBtn.classList.add('disabled');
        backBtn.title = 'Back image not available';
        // If currently viewing back and no back image, switch to front
        if (currentSide === 'back') {
            document.querySelector('.side-btn[data-side="front"]').click();
        }
    }
}

function updateMockupProduct() {
    const mockupProduct = document.getElementById('mockupProduct');
    const imagePath = currentSide === 'front' ? currentProductFrontImage : currentProductBackImage;

    if (mockupProduct && imagePath) {
        mockupProduct.src = '/' + imagePath;
        mockupProduct.style.display = 'block';
        mockupProduct.dataset.front = currentProductFrontImage;
        mockupProduct.dataset.back = currentProductBackImage;

        // Re-apply current color tint to the new image
        const selectedColor = document.querySelector('input[name^="color_"]:checked');
        if (selectedColor && selectedColor.dataset.colorHex) {
            // Small delay to ensure image is loaded
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
    if (isWhite) {
        // White product - desaturate completely and brighten significantly
        mockupProduct.style.filter = 'saturate(0) brightness(2) contrast(0.8)';
    } else if (isBlack) {
        // Black product - desaturate and darken significantly
        mockupProduct.style.filter = 'saturate(0) brightness(0.1) contrast(1.5)';
    } else if (isGray) {
        // Gray - desaturate and adjust brightness based on lightness
        const brightness = 0.2 + (hsl.l / 100) * 1.5;
        mockupProduct.style.filter = `saturate(0) brightness(${brightness})`;
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

        mockupProduct.style.filter = `sepia(1) saturate(${saturate}) hue-rotate(${hueRotate}deg) brightness(${brightness})`;
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

    const pos = designPositions[currentSide];
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

// ==================== FIXED DESIGN SIDE SWITCHING ====================
function applyFixedDesignForSide(side) {
    const designElement = document.getElementById('designElement');
    const designArea = document.getElementById('designArea');
    const mockupDesign = document.getElementById('mockupDesign');
    if (!designElement || !designArea || !mockupDesign) return;

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
    const customPanel = document.getElementById('customization-' + selectedProductId);
    let sizeModifier = 0;
    if (customPanel) {
        const selectedSize = customPanel.querySelector('input[name="size_' + selectedProductId + '"]:checked');
        if (selectedSize) {
            sizeModifier = parseFloat(selectedSize.dataset.priceModifier) || 0;
        }
    }
    document.getElementById('basePrice').textContent = '€' + selectedBasePrice.toFixed(2);
    if (sizeModifier > 0) {
        document.getElementById('sizeModifierRow').style.display = 'flex';
        document.getElementById('sizeModifier').textContent = '+€' + sizeModifier.toFixed(2);
    } else {
        document.getElementById('sizeModifierRow').style.display = 'none';
    }
    // Second design cost
    let secondDesignCost = 0;
    if (document.getElementById('addSecondDesign').checked) {
        secondDesignCost = designPrice;
        document.getElementById('secondDesignRow').style.display = 'flex';
    } else {
        document.getElementById('secondDesignRow').style.display = 'none';
    }
    const total = selectedBasePrice + designPrice + sizeModifier + secondDesignCost;
    document.getElementById('totalPrice').textContent = '€' + total.toFixed(2);
}

// ==================== CART ====================
function changeQuantity(delta) {
    const input = document.getElementById('quantity');
    let value = parseInt(input.value) + delta;
    if (value < 1) value = 1;
    if (value > 99) value = 99;
    input.value = value;
}

function addToCart() {
    const customPanel = document.getElementById('customization-' + selectedProductId);
    let sizeId = null;
    let colorId = null;
    let errorMsg = '';
    if (!selectedProductId) {
        errorMsg = 'Please select a product.';
    } else if (customPanel) {
        const selectedSize = customPanel.querySelector('input[name="size_' + selectedProductId + '"]:checked');
        const selectedColor = customPanel.querySelector('input[name="color_' + selectedProductId + '"]:checked');
        if (!selectedSize) {
            errorMsg = 'Please select a size.';
        } else if (!selectedColor) {
            errorMsg = 'Please select a color.';
        } else {
            sizeId = selectedSize.value;
            colorId = selectedColor.value;
        }
    } else {
        errorMsg = 'Please select a product.';
    }
    // Check design upload (main design is always present, but check for second design if checked)
    const addSecondDesign = document.getElementById('addSecondDesign');
    if (addSecondDesign && addSecondDesign.checked) {
        const secondDesignFile = document.getElementById('secondDesignFile');
        if (!secondDesignFile.files || !secondDesignFile.files[0]) {
            errorMsg = 'Please upload a file for the second design.';
        }
    }
    if (errorMsg) {
        alert(errorMsg);
        return;
    }
    const quantity = document.getElementById('quantity').value;
    const cartData = {
        design_id: designData.id,
        product_id: selectedProductId,
        size_id: sizeId,
        color_id: colorId,
        quantity: quantity,
        design_positions: designPositions
    };
    // If second design, include file name (actual upload will be handled in backend if needed)
    if (addSecondDesign && addSecondDesign.checked) {
        const secondDesignFile = document.getElementById('secondDesignFile');
        if (secondDesignFile.files && secondDesignFile.files[0]) {
            cartData.second_design = true;
            cartData.second_design_filename = secondDesignFile.files[0].name;
        }
    }
    // Send to backend via AJAX
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/cart/add', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                alert('Added to cart!');
                // Optionally update cart count, etc.
            } else {
                alert('Failed to add to cart: ' + xhr.responseText);
            }
        }
    };
    xhr.send(JSON.stringify(cartData));
}

// ==================== INITIALIZATION ====================
document.addEventListener('DOMContentLoaded', function() {
    // Initialize design element
    const designElement = document.getElementById('designElement');
    const designArea = document.getElementById('designArea');
    if (designElement) {
        if (isFixedDesign && typeof savedDesignPos !== 'undefined' && designArea) {
            // Apply admin-set position (wait for layout)
            setTimeout(() => {
                const areaW = designArea.offsetWidth;
                const areaH = designArea.offsetHeight;
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
    // Initialize colors for first product
    const firstProduct = document.querySelector('.product-customization');
    if (firstProduct) {
        const firstSize = firstProduct.querySelector('input[type="radio"]:checked');
        if (firstSize) {
            updateColors(firstSize);
        }
    }
    // Initialize drag & resize (only for non-fixed designs)
    if (!isFixedDesign) {
        initDesignInteraction();
    }
    // Check back button state
    updateBackButtonState();
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
            oppositeSideLabel.textContent = 'back';
            secondSideUploadLabel.textContent = 'Back';
        } else {
            oppositeSideLabel.textContent = 'front';
            secondSideUploadLabel.textContent = 'Front';
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
