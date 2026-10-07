/* save-design-modal.js — from views/shop/designer.php, loaded where the inline script used to run. */

function openSaveDesignModal() {
    // First close any open editing modals
    closeUploadEditor();
    hideTextEditor();
    hideImageEditor();

    const isEditing = !!window.loadedDesignId;
    const editingModeDiv = document.getElementById('saveEditingMode');
    const saveModalTitle = document.getElementById('saveModalTitle');
    const saveBtn = document.getElementById('saveDesignModalBtn');

    if (isEditing && window.loadedDesignName) {
        // Show editing mode
        editingModeDiv.style.display = 'block';
        document.getElementById('editingDesignName').textContent = window.loadedDesignName;
        saveModalTitle.textContent = window.I18N.t('studio.save_modal.title_edit');
        saveBtn.textContent = window.I18N.t('studio.save_modal.save_new');
    } else {
        // Hide editing mode - show only new save
        editingModeDiv.style.display = 'none';
        saveModalTitle.textContent = window.I18N.t('studio.save_modal.title');
        saveBtn.textContent = window.I18N.t('studio.save_modal.save');
    }

    document.getElementById('saveDesignModal').style.display = 'flex';
}
function closeSaveDesignModal() {
    document.getElementById('saveDesignModal').style.display = 'none';
    // Reset login notice so it's hidden next time
    const notice = document.getElementById('saveLoginNotice');
    if (notice) notice.style.display = 'none';
    const saveNewMode = document.getElementById('saveNewMode');
    if (saveNewMode) saveNewMode.style.display = '';
}

// --- Upload Editor Modal Logic ---
function openUploadEditor() {
    hideTextEditor();
    hideImageEditor();
    closeChangeColorModal();
    document.getElementById('uploadEditorModal').style.display = 'flex';
}
function closeUploadEditor() {
    document.getElementById('uploadEditorModal').style.display = 'none';
}
function handleUploadFile(files) {
    if (!files || !files.length) return;
    const file = files[0];
    if (!file.type.startsWith('image/')) {
        UI.error(window.I18N.t('studio.error.image_only'));
        return;
    }
    if (file.size > 20 * 1024 * 1024) {
        UI.error(window.I18N.t('studio.error.file_too_large'));
        return;
    }
    const reader = new FileReader();
    reader.onload = function(event) {
        addImageElement(event.target.result);
        saveRecentUpload(event.target.result);
        closeUploadEditor();
    };
    reader.readAsDataURL(file);
}
function handleUploadDrop(e) {
    e.preventDefault();
    e.currentTarget.classList.remove('dragover');
    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
        handleUploadFile(e.dataTransfer.files);
    }
}
function _uploadsKey() {
    return currentUserId ? 'recentUploads_' + currentUserId : null;
}
function saveRecentUpload(dataUrl) {
    const key = _uploadsKey();
    if (!key) return; // guests: don't persist uploads
    let recents = JSON.parse(localStorage.getItem(key) || '[]');
    // Deduplicate — remove existing copy, add to front
    recents = recents.filter(u => u !== dataUrl);
    recents.unshift(dataUrl);
    if (recents.length > 20) recents = recents.slice(0, 20);
    localStorage.setItem(key, JSON.stringify(recents));
    renderRecentUploads();
}
function renderRecentUploads() {
    const key = _uploadsKey();
    let recents = key ? JSON.parse(localStorage.getItem(key) || '[]') : [];
    const wrapper = document.getElementById('uploadRecentList');
    const strip = document.getElementById('uploadRecentStrip');
    if (!wrapper || !strip) return;
    if (!recents.length) { wrapper.style.display = 'none'; return; }
    wrapper.style.display = '';
    strip.innerHTML = '';
    recents.forEach(url => {
        const img = document.createElement('img');
        img.src = url;
        img.className = 'upload-recent-item';
        img.title = 'Click to add to design';
        img.addEventListener('click', () => {
            addImageElement(url);
            closeUploadEditor();
        });
        strip.appendChild(img);
    });
}
document.addEventListener('DOMContentLoaded', function() {
    renderRecentUploads();
});

// --- Whats Next Panel Upload/Add Text Logic ---
function triggerWhatsNextUpload() {
    hideTextEditor();
    document.getElementById('whatsNextImageUpload').click();
    showImageEditor();
}
function triggerWhatsNextAddText() {
    closeUploadEditor();
    hideImageEditor();
    closeChangeColorModal();
    showTextEditor();
}

// --- Change Color Modal Logic ---
function openChangeColorModal() {
    closeUploadEditor();
    hideTextEditor();
    hideImageEditor();
    var modal = document.getElementById('changeColorModal');
    var optionsDiv = document.getElementById('changeColorOptions');
    if (!window.currentProduct) {
        UI.error('Please select a product first.');
        return;
    }

    // If data not loaded yet, fetch it now
    if (!window.studioVariantsData || window.studioVariantsData.colors.length === 0) {
        optionsDiv.innerHTML = '<p style="text-align:center;padding:1rem;">Loading colors...</p>';
        modal.style.display = 'flex';

        fetch('/api/product-variants/' + window.currentProduct.id)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                window.studioVariantsData = {
                    variants: data.variants || [],
                    sizes: data.sizes || [],
                    colors: data.colors || []
                };
                // Re-render the modal with loaded data
                renderChangeColorModalContent();
                // Also render the studio panel
                renderStudioColorSwatches();
            })
            .catch(function(err) {
                console.error('Failed to fetch colors:', err);
                optionsDiv.innerHTML = '<p style="color:red;text-align:center;">Failed to load colors. Please try again.</p>';
            });
        return;
    }

    renderChangeColorModalContent();
    modal.style.display = 'flex';
}

function renderChangeColorModalContent() {
    var optionsDiv = document.getElementById('changeColorOptions');
    var colors = window.studioVariantsData.colors || [];
    var sizes = window.studioVariantsData.sizes || [];
    var variants = window.studioVariantsData.variants || [];

    var html = '';
    html += '<div style="margin-bottom:1.2rem;"><b>Colors:</b><div class="modal-color-swatches">';
    colors.forEach(function(c) {
        var selected = (window.studioSelectedColorId && String(c.id) === String(window.studioSelectedColorId)) ? 'modal-color-selected' : '';
        var borderStyle = (c.hex && (c.hex.toLowerCase() === '#ffffff' || c.hex.toLowerCase() === '#fff')) ? 'border:2px solid #ccc;' : '';
        html += `<div class="modal-color-swatch ${selected}" title="${c.name || 'Color'}" style="background:${c.hex || '#ccc'};${borderStyle}" data-color-id="${c.id}" data-hex="${c.hex || '#ccc'}" onclick="selectModalColor('${c.id}', '${c.hex || '#ccc'}')">${selected ? '<span class="modal-color-check">&#10003;</span>' : ''}</div>`;
    });
    html += '</div></div>';

    // Show sizes for currently selected color
    html += '<div style="margin-bottom:1.2rem;"><b>Available Sizes:</b><div class="modal-size-list" id="modalSizeList">';
    if (window.studioSelectedColorId) {
        var availableSizes = [];
        variants.forEach(function(v) {
            if (String(v.color_id) === String(window.studioSelectedColorId) && v.is_available) {
                var sz = sizes.find(function(s) { return String(s.id) === String(v.size_id); });
                if (sz && !availableSizes.some(function(s) { return s.id === sz.id; })) {
                    availableSizes.push(sz);
                }
            }
        });
        if (availableSizes.length > 0) {
            availableSizes.forEach(function(sz) {
                html += `<span class="modal-size-item">${sz.name}</span>`;
            });
        } else {
            html += '<span style="color:#888;">No sizes available</span>';
        }
    } else {
        html += '<span style="color:#888;">Select a color to see sizes</span>';
    }
    html += '</div></div>';

    optionsDiv.innerHTML = html;
}
function closeChangeColorModal() {
    document.getElementById('changeColorModal').style.display = 'none';
}
function selectModalColor(colorId, colorHex) {
    // Apply color directly to mockup
    if (colorHex) {
        applyColorTint(colorHex);
    }

    // Update studio selected color
    window.studioSelectedColorId = colorId;

    // Update the studio panel swatches
    var studioContainer = document.getElementById('studioColorSwatches');
    if (studioContainer) {
        studioContainer.querySelectorAll('.studio-color-swatch').forEach(function(s) {
            s.classList.remove('selected');
            s.style.border = '3px solid #ddd';
            var hex = (s.dataset.hex || '').toLowerCase();
            if (hex === '#ffffff' || hex === '#fff') {
                s.style.border = '3px solid #ccc';
            }
            if (String(s.dataset.colorId) === String(colorId)) {
                s.classList.add('selected');
                s.style.border = '3px solid #4CAF50';
            }
        });
    }

    // Update sizes display
    renderStudioSizesForColor(colorId);

    // Re-render modal to show updated selection (keep modal open)
    renderChangeColorModalContent();
}
function selectModalSize(sizeId) {
    // Sizes are display-only in the modal
}

// Prevent browser from opening files on window drag/drop
window.addEventListener('dragover', function(e) { e.preventDefault(); }, false);
window.addEventListener('drop', function(e) { e.preventDefault(); }, false);
