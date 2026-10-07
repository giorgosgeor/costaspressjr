/* image-editor.js — from views/shop/designer.php, loaded where the inline script used to run. */

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
                                    if (!elementDiv) return UI.error('Image not found');
                                    const imgDiv = elementDiv.querySelector('img');
                                    if (!imgDiv) return UI.error('Image not found');
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
                                    // No endOnly restrictRect here either - it
                                    // caused the same snap-back on this overlay
                                    // as on the design elements. See initInteract.
                                    interact(overlay).draggable({
                                        listeners: {
                                            start (event) {
                                                const t = event.target;
                                                t._dragX = parseFloat(t.style.left) || 0;
                                                t._dragY = parseFloat(t.style.top) || 0;
                                            },
                                            move (event) {
                                                const target = event.target;
                                                if (typeof target._dragX !== 'number') target._dragX = parseFloat(target.style.left) || 0;
                                                if (typeof target._dragY !== 'number') target._dragY = parseFloat(target.style.top) || 0;
                                                target._dragX += event.dx;
                                                target._dragY += event.dy;
                                                const maxX = Math.max(0, designArea.clientWidth  - target.offsetWidth);
                                                const maxY = Math.max(0, designArea.clientHeight - target.offsetHeight);
                                                const x = Math.max(0, Math.min(target._dragX, maxX));
                                                const y = Math.max(0, Math.min(target._dragY, maxY));
                                                target.style.left = x + 'px';
                                                target.style.top = y + 'px';
                                            },
                                            end (event) {
                                                const target = event.target;
                                                target._dragX = parseFloat(target.style.left) || 0;
                                                target._dragY = parseFloat(target.style.top) || 0;
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
