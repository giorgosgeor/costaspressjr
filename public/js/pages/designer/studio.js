/* studio.js — from views/shop/designer.php, loaded where the inline script used to run. */
const designerData = JSON.parse(document.getElementById('designer-data').textContent);

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
const productsData = designerData.products;

// Current logged-in user ID (null when guest) — used to scope upload history
const currentUserId = designerData.currentUserId;

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
const loadDesignData = designerData.loadDesign;

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

    // Prefer the live catalogue entry, but fall back to the product data the
    // design itself carries. productsData only lists ACTIVE products that have
    // artwork, so a design whose product was later deactivated used to hit the
    // early return here and render nothing at all — and the studio's
    // first-product fallback then filled the empty editor with an unrelated
    // garment, which is why the wrong product appeared.
    let product = productsData.find(p => String(p.id) === String(designData.product_id));
    if (!product && designData.image_path) {
        product = {
            id: designData.product_id,
            name: designData.product_name || 'Product',
            slug: designData.product_slug || '',
            base_price: designData.base_price || 0,
            size_chart_image: designData.size_chart_image || '',
            image_path: designData.image_path,
            back_image_path: designData.back_image_path || '',
            left_sleeve_image_path: designData.left_sleeve_image_path || '',
            right_sleeve_image_path: designData.right_sleeve_image_path || '',
            da_front_x: designData.da_front_x, da_front_y: designData.da_front_y,
            da_front_w: designData.da_front_w, da_front_h: designData.da_front_h,
            da_back_x:  designData.da_back_x,  da_back_y:  designData.da_back_y,
            da_back_w:  designData.da_back_w,  da_back_h:  designData.da_back_h,
            da_lsleeve_x: designData.da_lsleeve_x, da_lsleeve_y: designData.da_lsleeve_y,
            da_lsleeve_w: designData.da_lsleeve_w, da_lsleeve_h: designData.da_lsleeve_h,
            da_rsleeve_x: designData.da_rsleeve_x, da_rsleeve_y: designData.da_rsleeve_y,
            da_rsleeve_w: designData.da_rsleeve_w, da_rsleeve_h: designData.da_rsleeve_h
        };
        console.warn('[studio] product %s is no longer listed; using the data stored with the design',
                     designData.product_id);
    }
    if (!product) {
        console.error('[studio] cannot load design %s: product %s has no artwork',
                      designData.id, designData.product_id);
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

    // Dots + swipe. The label tracks .active on its own, so switchView() needs
    // no changes. Swipes that start on a design element are ignored so dragging
    // artwork still works.
    if (window.ViewSwitcher) {
        window.ViewSwitcher.init({
            dots: '#viewDots',
            dotSelector: '.view-btn',
            label: '#viewCurrentLabel',
            surface: '#mockupContainer',
            ignore: '.design-element'
        });
    }

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

    // Fall back to the first available product. There are no `.product-choice`
    // cards on this page any more, so nothing else selects a product on a cold
    // visit — the studio opened showing "Select a product" and an empty frame.
    // Guests now enter the studio directly, so this is the first screen they
    // see; it has to be usable immediately. Runs last so a session handoff, a
    // saved studio snapshot or a loaded design all take precedence.
    setTimeout(function () {
        // Never override an explicit intent. If the page was opened with
        // ?load=<design>, that design owns the editor — even if it failed to
        // render, substituting an unrelated product here would be worse than
        // showing nothing, because the customer would be editing (and could
        // buy) a garment they never chose.
        if (loadDesignData) return;
        if (window.currentProduct || !Array.isArray(productsData) || !productsData.length) return;
        var p = productsData[0];
        window.currentProduct = {
            id: p.id,
            name: p.name,
            basePrice: parseFloat(p.base_price),
            imagePath: p.image_path,
            backImagePath: p.back_image_path || '',
            leftSleeveImagePath: p.left_sleeve_image_path || '',
            rightSleeveImagePath: p.right_sleeve_image_path || '',
            sizeChartImage: p.size_chart_image || '',
            da_front_x: p.da_front_x, da_front_y: p.da_front_y,
            da_front_w: p.da_front_w, da_front_h: p.da_front_h,
            da_back_x:  p.da_back_x,  da_back_y:  p.da_back_y,
            da_back_w:  p.da_back_w,  da_back_h:  p.da_back_h,
            da_lsleeve_x: p.da_lsleeve_x, da_lsleeve_y: p.da_lsleeve_y,
            da_lsleeve_w: p.da_lsleeve_w, da_lsleeve_h: p.da_lsleeve_h,
            da_rsleeve_x: p.da_rsleeve_x, da_rsleeve_y: p.da_rsleeve_y,
            da_rsleeve_w: p.da_rsleeve_w, da_rsleeve_h: p.da_rsleeve_h
        };
        var ls = document.getElementById('leftSleeveBtn');
        var rs = document.getElementById('rightSleeveBtn');
        if (ls) ls.style.display = window.currentProduct.leftSleeveImagePath ? '' : 'none';
        if (rs) rs.style.display = window.currentProduct.rightSleeveImagePath ? '' : 'none';
        try { initStudioColorPanel(p.id); } catch (e) {}
        updateMockupImage();
        applyDesignArea();
        updateSummary();
    }, 120);
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

// Bounds for a design element inside the design area.
//
// clientWidth/clientHeight, not getBoundingClientRect(): left/top are plain
// CSS pixels in the design area's own coordinate space, and the layout box is
// what they are measured against. (Rotation is applied to the inner <img>,
// never to this container, so the two agree.)
function designElementBounds(target) {
    const area = target.parentNode;
    if (!area) return { maxX: 0, maxY: 0 };
    return {
        maxX: Math.max(0, area.clientWidth  - target.offsetWidth),
        maxY: Math.max(0, area.clientHeight - target.offsetHeight)
    };
}

function clampToBox(value, max) {
    return Math.max(0, Math.min(value, max));
}

function initInteract(div, element) {
    interact(div)
        .draggable({
            inertia: false,
            // No restrictRect modifier.
            //
            // There used to be one, with endOnly:true, running ALONGSIDE the
            // manual clamp below - and that combination is what threw the
            // element back across the box when you dragged past an edge and
            // let go. interact.js tracks its own unclamped coordinates, so
            // while our listener was pinning the element to the boundary,
            // interact still believed it was wherever the pointer had gone.
            // At drag-end the modifier computed a correction against ITS
            // position and handed us the difference as a delta, which we
            // applied on top of our already-clamped position - a large jump
            // backwards, landing roughly where the element had started.
            //
            // All bounds logic is ours now, applied every frame, so there is
            // no second opinion to reconcile and nothing to undo at the end.
            listeners: {
                start (event) {
                    // The pointer's intended position, tracked unclamped and
                    // separately from what is rendered. This is what makes the
                    // element STICK to the edge: carry on past the boundary and
                    // it stays pinned there, and it only starts moving again
                    // once the pointer is genuinely back inside - rather than
                    // sliding away from the cursor the instant you reverse.
                    const t = event.target;
                    t._dragX = parseFloat(t.style.left) || 0;
                    t._dragY = parseFloat(t.style.top) || 0;
                },
                move (event) {
                    const t = event.target;
                    if (typeof t._dragX !== 'number') t._dragX = parseFloat(t.style.left) || 0;
                    if (typeof t._dragY !== 'number') t._dragY = parseFloat(t.style.top) || 0;
                    t._dragX += event.dx;
                    t._dragY += event.dy;

                    const b = designElementBounds(t);
                    const x = clampToBox(t._dragX, b.maxX);
                    const y = clampToBox(t._dragY, b.maxY);
                    t.style.left = x + 'px';
                    t.style.top  = y + 'px';

                    const el = elements[currentView].find(e => e.id === t.id);
                    if (el) { el.x = x; el.y = y; }
                },
                end (event) {
                    // Settle on exactly what is on screen: the furthest point
                    // inside the box that the drag reached. Nothing is restored
                    // and nothing moves after the pointer is released.
                    const t = event.target;
                    const b = designElementBounds(t);
                    const x = clampToBox(parseFloat(t.style.left) || 0, b.maxX);
                    const y = clampToBox(parseFloat(t.style.top) || 0, b.maxY);
                    t.style.left = x + 'px';
                    t.style.top  = y + 'px';
                    t._dragX = x;
                    t._dragY = y;

                    const el = elements[currentView].find(e => e.id === t.id);
                    if (el) { el.x = x; el.y = y; }
                }
            }
        })
        .resizable({
            // Resizing is reachable ONLY from the corner handle.
            //
            // Every edge used to be a resize zone with interact's default
            // ~10px grab margin. On a small element on a phone that leaves
            // almost no interior to drag from, so a tap meant to MOVE the
            // artwork resized it instead - and since the UI only ever draws
            // one handle, at the bottom-right, nothing on screen explained
            // why. Passing a selector makes that handle the single resize
            // affordance, so everywhere else is unambiguously "move".
            edges: { bottom: '.resize-handle', right: '.resize-handle' },
            listeners: {
                move (event) {
                    const t = event.target;
                    const area = t.parentNode;
                    const areaW = area ? area.clientWidth  : event.rect.width;
                    const areaH = area ? area.clientHeight : event.rect.height;

                    // Resizing from a left or top handle moves the element as
                    // well as sizing it. deltaRect carries that movement; it
                    // was ignored before, so dragging the left handle grew the
                    // element to the RIGHT instead of towards the pointer.
                    let left = (parseFloat(t.style.left) || 0) + event.deltaRect.left;
                    let top  = (parseFloat(t.style.top)  || 0) + event.deltaRect.top;
                    let w = event.rect.width;
                    let h = event.rect.height;

                    // Never larger than the box, and never outside it. Same
                    // rule as dragging: pushing a handle past an edge stops at
                    // the edge instead of reverting.
                    w = Math.max(20, Math.min(w, areaW));
                    h = Math.max(20, Math.min(h, areaH));
                    left = clampToBox(left, areaW - w);
                    top  = clampToBox(top,  areaH - h);

                    t.style.left   = left + 'px';
                    t.style.top    = top + 'px';
                    t.style.width  = w + 'px';
                    t.style.height = h + 'px';

                    // Keep the drag tracker in step, or the next drag would
                    // resume from the pre-resize position and jump.
                    t._dragX = left;
                    t._dragY = top;

                    const el = elements[currentView].find(e => e.id === t.id);
                    if (el) {
                        el.x = left;
                        el.y = top;
                        el.width = w;
                        el.height = h;
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
        // Opening a specific saved design (?load=<id>) beats any snapshot.
        // Without this the sequence was: loadExistingDesign() sets the design's
        // real product and colour, then this ran 60ms later on a timer and
        // replaced BOTH with whatever was last in localStorage — so editing a
        // saved Female Tank Top showed a Female T-Shirt, in the previously used
        // colour (often black). The design's own data must win.
        if (typeof loadDesignData !== 'undefined' && loadDesignData) {
            dbg('editing a saved design, skipping restore');
            return false;
        }

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

        // Restore primitives BEFORE calling render functions that read them.
        // The VIEW is deliberately not restored: it used to be assigned here
        // directly, which bypassed switchView() and so never moved the .active
        // dot — the label read FRONT while the mockup showed the back, and the
        // first dot click appeared to do nothing because it was "switching" to
        // the view already marked active. The studio now always opens on the
        // front, which is also what customers expect.
        currentView = 'front';
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
