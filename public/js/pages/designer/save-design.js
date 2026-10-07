/* save-design.js — from views/shop/designer.php, loaded where the inline script used to run. */

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
    // options.returnOnly — render the previews and hand them back WITHOUT
    // posting them. Used before a design exists server-side (the login-bounce
    // save), where there is no designId to attach them to yet.
    window.generateAndSavePreviews = async function generateAndSavePreviews(designId, options = {}) {
        const returnOnly = !!options.returnOnly;
        if ((!designId && !returnOnly) || !window.currentProduct) {
            console.warn('Cannot generate previews: missing designId or product');
            return returnOnly ? {} : undefined;
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

        // Caller wants the images, not a save (no design row exists yet).
        if (returnOnly) return previews;

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
                await resp.json();
            } catch (err) {
                console.error('Failed to save previews:', err);
            }
        }
    }

    // Front-view snapshot as a data URL, for the save-then-login bounce: the
    // payload is stashed in sessionStorage and replayed after login. This was
    // previously only defined in public/js/shop_custom.js, which was never
    // loaded — so the `typeof` guard at the call site always failed and the
    // preview was silently lost.
    window.captureCurrentFrontPreview = async function captureCurrentFrontPreview() {
        try {
            const previews = await window.generateAndSavePreviews(null, { returnOnly: true });
            return (previews && previews.front) ? previews.front : null;
        } catch (e) {
            console.warn('Front preview capture failed:', e);
            return null;
        }
    };

    // Update Design button handler (for editing existing designs)
    document.getElementById('updateDesignBtn').addEventListener('click', function() {
        if (!window.loadedDesignId) {
            UI.error('No design loaded to update.');
            return;
        }

        let allElements = [];
        Object.keys(elements).forEach(view => {
            allElements = allElements.concat(elements[view].map(el => ({...el, view})));
        });

        if (!window.currentProduct) {
            UI.error('Please select a product before saving your design.');
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
                    UI.error('Session expired. Please log in and try again.');
                }
            }
        };
        xhr.send(JSON.stringify(designData));
    });

    // Delete Design button handler
    document.getElementById('deleteDesignBtn').addEventListener('click', function() {
        if (!window.loadedDesignId) {
            UI.error('No design loaded to delete.');
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
                    UI.error('Failed to delete design. Please try again.');
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
            UI.success(window.I18N.t('studio.not_saved'));
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
                    UI.error(window.I18N.t('checkout.errors.generic'));
                    btn.disabled = false; btn.textContent = window.I18N.t('studio.save_modal.save_new');
                }
            } catch(e) {
                UI.error(window.I18N.t('studio.cart.error_generic'));
                btn.disabled = false; btn.textContent = window.I18N.t('studio.save_modal.save_new');
            }
        })();
    });

    // Shared: open the size/color/quantity modal for an already-saved design.
    function openCartModalForDesign(designId, designName) {
        // Raw pre-margin print add-ons (front+back = €3, each sleeve = €1). These
        // are marked up through the margin in updateCartPrices().
        const frontCount = (typeof elements !== 'undefined' && elements['front']) ? elements['front'].length : 0;
        const backCount = (typeof elements !== 'undefined' && elements['back']) ? elements['back'].length : 0;
        const leftSleeveCount = (typeof elements !== 'undefined' && elements['left-sleeve']) ? elements['left-sleeve'].length : 0;
        const rightSleeveCount = (typeof elements !== 'undefined' && elements['right-sleeve']) ? elements['right-sleeve'].length : 0;
        let designFee = 0;
        if (frontCount > 0 && backCount > 0) designFee += 3.0;
        if (leftSleeveCount > 0) designFee += 1.0;
        if (rightSleeveCount > 0) designFee += 1.0;

        const productName = window.currentProduct ? window.currentProduct.name : 'Custom Product';
        const basePrice = window.currentProduct ? window.currentProduct.basePrice : 0;

        openAddToCartModal(
            designId,
            window.currentProduct ? window.currentProduct.id : null,
            productName,
            designName,
            basePrice,
            designFee
        );
    }

    // Add to Cart button handler (saved-design modal) - design already saved
    document.getElementById('addToCartNowBtn').addEventListener('click', function() {
        if (!window.savedDesignId) {
            UI.success(window.I18N.t('studio.not_saved'));
            return;
        }
        const designName = document.getElementById('saveDesignName') ? document.getElementById('saveDesignName').value : 'Your Design';
        openCartModalForDesign(window.savedDesignId, designName);
    });

    // Direct Add to Cart (studio toolbar) — works for guests. The cart needs a
    // design row to reference, so the design is saved silently first: cart_flow
    // lets the server accept the save under the session's guest user row. The
    // explicit "Save Design" library flow still requires a real login.
    document.getElementById('addToCartDirectBtn')?.addEventListener('click', async function() {
        const btn = this;
        if (!window.currentProduct) {
            UI.success(window.I18N.t('studio.not_saved'));
            return;
        }
        const hasAny = typeof elements !== 'undefined' &&
            ['front', 'back', 'left-sleeve', 'right-sleeve'].some(v => (elements[v] || []).length > 0);
        if (!hasAny) {
            UI.error(window.I18N.t('studio.cart.error_empty_design'));
            return;
        }

        // Re-use the already-saved design when nothing needs re-saving is not
        // trivial to detect, so save a fresh snapshot each time the direct
        // button is used — the cart item then references exactly what's on
        // screen right now.
        let allElements = [];
        Object.keys(elements).forEach(view => {
            allElements = allElements.concat(elements[view].map(el => ({...el, view})));
        });
        const _da = document.getElementById('designArea');
        const autoName = (window.currentProduct.name || 'Custom') + ' ' + new Date().toISOString().slice(0, 10);
        const designData = {
            cart_flow: true,
            name: autoName.slice(0, 25),
            product_id: window.currentProduct.id,
            size_id: null, color_id: null,
            elements: allElements,
            color_hex: currentColorHex,
            editorDAWidth:  _da ? _da.offsetWidth  : 225,
            editorDAHeight: _da ? _da.offsetHeight : 300
        };

        btn.disabled = true;
        try {
            const resp = await fetch('/custom-design/save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(designData)
            });
            const parsed = await resp.json().catch(() => null);
            if (!parsed || !parsed.id) {
                UI.error((parsed && parsed.error) || window.I18N.t('studio.cart.error_generic'));
                return;
            }
            window.savedDesignId = parsed.id;
            openCartModalForDesign(parsed.id, designData.name);
        } catch (e) {
            console.error('Direct add-to-cart save failed:', e);
            UI.error(window.I18N.t('studio.cart.error_generic'));
        } finally {
            btn.disabled = false;
        }
    });

    // Go to Checkout button handler
    // There is no #goToCheckoutBtn in this page's markup, so this threw
    // "Cannot read properties of null" inside DOMContentLoaded and aborted
    // the rest of that handler. Guarded rather than deleted, in case the
    // button is reinstated.
    var _checkoutBtn = document.getElementById('goToCheckoutBtn');
    if (_checkoutBtn) {
        _checkoutBtn.addEventListener('click', function() {
            window.location.href = '/cart';
        });
    }
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
