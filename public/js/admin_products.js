let editSizeIndex = 0;
let currentEditSizeRow = null;
let availableColors = [];

// Load available colors on page load
document.addEventListener('DOMContentLoaded', function() {
    fetch('/admin/api/colors', { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            availableColors = data;
            renderColorCheckboxes();
        })
        .catch(() => {
            // Fallback if API not available
            availableColors = [];
        });
});

function renderColorCheckboxes() {
    const container = document.getElementById('colorCheckboxes');
    if (!availableColors.length) {
        container.innerHTML = '<p style="text-align:center;color:#666;">No colors available. <a href="/admin/colors">Add colors first</a>.</p>';
        return;
    }
    container.innerHTML = availableColors.map(c => {
        const isWhite = c.hex_code.toLowerCase() === '#ffffff' || c.hex_code.toLowerCase() === '#fff' || c.name.toLowerCase() === 'white';
        return `
        <label class="color-checkbox-item">
            <input type="checkbox" value="${c.id}" data-color-name="${c.name}">
            <span class="color-swatch${isWhite ? ' is-white' : ''}" style="background-color: ${c.hex_code};"></span>
            <span class="color-name">${c.name}</span>
        </label>
    `}).join('');
}

function openEditModal(productId) {
    const modal = document.getElementById('editProductModal');
    const loading = document.getElementById('editModalLoading');
    const form = document.getElementById('editProductForm');

    modal.classList.add('active');
    loading.style.display = 'block';
    form.style.display = 'none';
    editSizeIndex = 0;

    // Fetch product data
    fetch('/admin/api/product/' + productId, { credentials: 'same-origin' })
        .then(r => {
            if (!r.ok) throw new Error('Failed to load');
            return r.json();
        })
        .then(data => {
            populateEditForm(data);
            loading.style.display = 'none';
            form.style.display = 'block';
        })
        .catch(err => {
            alert('Error loading product');
            closeEditModal();
        });
}

function populateEditForm(data) {
    document.getElementById('editProductId').value = data.product.id;
    document.getElementById('editName').value = data.product.name;
    document.getElementById('editBasePrice').value = data.product.base_price;
    document.getElementById('editDescription').value = data.product.description || '';
    document.getElementById('editIsActive').checked = data.product.active == 1;

    // Reset remove image flags
    document.getElementById('removeImage').value = '0';
    document.getElementById('removeBackImage').value = '0';
    document.getElementById('removeLeftSleeveImage').value = '0';
    document.getElementById('removeRightSleeveImage').value = '0';

    // Show current front image if exists
    const imagePreview = document.getElementById('editCurrentImage');
    const removeBtn = document.getElementById('removeImageBtn');
    if (data.product.has_image && data.product.image_path) {
        imagePreview.innerHTML = `<img src="/${data.product.image_path}?t=${Date.now()}" alt="Current image">`;
        removeBtn.style.display = 'inline-block';
    } else {
        imagePreview.innerHTML = '<small style="color:#666;">No image</small>';
        removeBtn.style.display = 'none';
    }

    // Show current back image if exists
    const backImagePreview = document.getElementById('editCurrentBackImage');
    const removeBackBtn = document.getElementById('removeBackImageBtn');
    if (data.product.has_back_image && data.product.back_image_path) {
        backImagePreview.innerHTML = `<img src="/${data.product.back_image_path}?t=${Date.now()}" alt="Current back image">`;
        removeBackBtn.style.display = 'inline-block';
    } else {
        backImagePreview.innerHTML = '<small style="color:#666;">No back image</small>';
        removeBackBtn.style.display = 'none';
    }

    // Show current left sleeve image if exists
    const leftSleevePreview = document.getElementById('editCurrentLeftSleeveImage');
    const removeLeftSleeveBtn = document.getElementById('removeLeftSleeveImageBtn');
    if (data.product.has_left_sleeve_image && data.product.left_sleeve_image_path) {
        leftSleevePreview.innerHTML = `<img src="/${data.product.left_sleeve_image_path}?t=${Date.now()}" alt="Current left sleeve">`;
        removeLeftSleeveBtn.style.display = 'inline-block';
    } else {
        leftSleevePreview.innerHTML = '<small style="color:#666;">No left sleeve image</small>';
        removeLeftSleeveBtn.style.display = 'none';
    }

    // Show current right sleeve image if exists
    const rightSleevePreview = document.getElementById('editCurrentRightSleeveImage');
    const removeRightSleeveBtn = document.getElementById('removeRightSleeveImageBtn');
    if (data.product.has_right_sleeve_image && data.product.right_sleeve_image_path) {
        rightSleevePreview.innerHTML = `<img src="/${data.product.right_sleeve_image_path}?t=${Date.now()}" alt="Current right sleeve">`;
        removeRightSleeveBtn.style.display = 'inline-block';
    } else {
        rightSleevePreview.innerHTML = '<small style="color:#666;">No right sleeve image</small>';
        removeRightSleeveBtn.style.display = 'none';
    }

    // Build size-color map
    const sizeColorMap = {};
    (data.variants || []).forEach(v => {
        if (!sizeColorMap[v.size_id]) sizeColorMap[v.size_id] = [];
        sizeColorMap[v.size_id].push(parseInt(v.color_id));
    });

    // Load sizes
    const container = document.getElementById('editSizesContainer');
    container.innerHTML = '';
    editSizeIndex = 0;

    (data.sizes || []).forEach(size => {
        const colors = sizeColorMap[size.id] || [];
        addSizeRowEdit(size.size_name, size.price_modifier, colors, size.id);
    });
}

function closeEditModal() {
    document.getElementById('editProductModal').classList.remove('active');
}

function addSizeRowEdit(sizeName = '', priceModifier = '0.00', selectedColors = [], sizeId = null) {
    const container = document.getElementById('editSizesContainer');
    const row = document.createElement('div');
    row.className = 'size-row';
    row.dataset.index = editSizeIndex;
    if (sizeId) row.dataset.sizeId = sizeId;

    const colorCount = selectedColors.length;

    row.innerHTML = `
        <input type="text" name="sizes[${editSizeIndex}][name]" placeholder="Size" value="${sizeName}" required>
        <button type="button" class="btn-colors" onclick="openColorModalEdit(this)">
            Colors <span class="color-count">${colorCount}</span>
        </button>
        <input type="number" name="sizes[${editSizeIndex}][price_modifier]" placeholder="+$" step="0.01" value="${priceModifier}">
        <input type="hidden" name="sizes[${editSizeIndex}][colors]" value="${selectedColors.join(',')}">
        ${sizeId ? `<input type="hidden" name="sizes[${editSizeIndex}][id]" value="${sizeId}">` : ''}
        <button type="button" class="btn-remove" onclick="this.closest('.size-row').remove()">✕</button>
    `;

    container.appendChild(row);
    editSizeIndex++;
}

function addClothingSizesEdit() {
    ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL'].forEach(s => addSizeRowEdit(s, '0.00'));
}

function addMugSizesEdit() {
    addSizeRowEdit('11oz', '0.00');
    addSizeRowEdit('15oz', '2.00');
}

function openColorModalEdit(btn) {
    currentEditSizeRow = btn.closest('.size-row');
    const sizeName = currentEditSizeRow.querySelector('input[type="text"]').value || 'this size';
    const hiddenInput = currentEditSizeRow.querySelector('input[name$="[colors]"]');
    const selectedColors = hiddenInput.value ? hiddenInput.value.split(',').map(Number).filter(n => n > 0) : [];

    document.getElementById('modalSizeName').textContent = sizeName;

    // Update checkboxes
    document.querySelectorAll('#colorCheckboxes input[type="checkbox"]').forEach(cb => {
        cb.checked = selectedColors.includes(parseInt(cb.value));
    });

    document.getElementById('colorModal').classList.add('active');
}

function closeColorModal() {
    document.getElementById('colorModal').classList.remove('active');
    currentEditSizeRow = null;
}

function removeProductImage() {
    document.getElementById('removeImage').value = '1';
    document.getElementById('editCurrentImage').innerHTML = '<small style="color:#c00;">Image will be removed on save</small>';
    document.getElementById('removeImageBtn').style.display = 'none';
    document.getElementById('editImage').value = ''; // Clear any selected file
}

function removeProductBackImage() {
    document.getElementById('removeBackImage').value = '1';
    document.getElementById('editCurrentBackImage').innerHTML = '<small style="color:#c00;">Back image will be removed on save</small>';
    document.getElementById('removeBackImageBtn').style.display = 'none';
    document.getElementById('editBackImage').value = ''; // Clear any selected file
}

function removeProductLeftSleeveImage() {
    document.getElementById('removeLeftSleeveImage').value = '1';
    document.getElementById('editCurrentLeftSleeveImage').innerHTML = '<small style="color:#c00;">Left sleeve image will be removed on save</small>';
    document.getElementById('removeLeftSleeveImageBtn').style.display = 'none';
    document.getElementById('editLeftSleeveImage').value = '';
}

function removeProductRightSleeveImage() {
    document.getElementById('removeRightSleeveImage').value = '1';
    document.getElementById('editCurrentRightSleeveImage').innerHTML = '<small style="color:#c00;">Right sleeve image will be removed on save</small>';
    document.getElementById('removeRightSleeveImageBtn').style.display = 'none';
    document.getElementById('editRightSleeveImage').value = '';
}

function saveColorSelection() {
    if (!currentEditSizeRow) return;

    const checkboxes = document.querySelectorAll('#colorCheckboxes input[type="checkbox"]:checked');
    const selectedIds = Array.from(checkboxes).map(cb => cb.value);

    currentEditSizeRow.querySelector('input[name$="[colors]"]').value = selectedIds.join(',');
    currentEditSizeRow.querySelector('.color-count').textContent = selectedIds.length;

    closeColorModal();
}

function saveProduct() {
    const form = document.getElementById('editProductForm');
    const productId = document.getElementById('editProductId').value;
    const formData = new FormData(form);

    fetch('/admin/products/edit/' + productId, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(r => {
        if (r.redirected || r.ok) {
            window.location.reload();
        } else {
            throw new Error('Failed to save');
        }
    })
    .catch(() => {
        alert('Error saving product. Please try again.');
    });
}

// Close modals on outside click
document.getElementById('editProductModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
document.getElementById('colorModal').addEventListener('click', function(e) {
    if (e.target === this) closeColorModal();
});

// Close modals on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeColorModal();
        closeEditModal();
    }
});

// ===== PASTE IMAGE SUPPORT =====
let activePasteInputId = null;

document.addEventListener('focusin', function(e) {
    if (e.target.classList.contains('paste-zone')) {
        activePasteInputId = e.target.dataset.for;
        document.querySelectorAll('.paste-zone').forEach(z => z.classList.remove('active'));
        e.target.classList.add('active');
    }
});
document.addEventListener('focusout', function(e) {
    if (e.target.classList.contains('paste-zone')) {
        e.target.classList.remove('active');
    }
});
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('paste-zone')) {
        activePasteInputId = e.target.dataset.for;
        document.querySelectorAll('.paste-zone').forEach(z => z.classList.remove('active'));
        e.target.classList.add('active');
        e.target.focus();
    }
});
document.addEventListener('paste', function(e) {
    if (!activePasteInputId) return;
    const items = e.clipboardData?.items;
    if (!items) return;
    for (const item of items) {
        if (item.type.startsWith('image/')) {
            const blob = item.getAsFile();
            if (!blob) continue;
            const input = document.getElementById(activePasteInputId);
            if (!input) continue;
            const ext = blob.type === 'image/jpeg' ? 'jpg' : 'png';
            const dt = new DataTransfer();
            dt.items.add(new File([blob], 'pasted-image.' + ext, { type: blob.type }));
            input.files = dt.files;
            const reader = new FileReader();
            const capturedId = activePasteInputId;
            reader.onload = function(ev) {
                const preview = document.getElementById('paste-preview-' + capturedId);
                const img = document.getElementById('paste-img-' + capturedId);
                if (preview && img) {
                    img.src = ev.target.result;
                    preview.style.display = 'flex';
                }
                const zone = document.querySelector('.paste-zone[data-for="' + capturedId + '"]');
                if (zone) zone.textContent = '✓ Image pasted — click to replace';
            };
            reader.readAsDataURL(blob);
            e.preventDefault();
            break;
        }
    }
});

function clearPaste(inputId) {
    const input = document.getElementById(inputId);
    if (input) input.value = '';
    const preview = document.getElementById('paste-preview-' + inputId);
    if (preview) preview.style.display = 'none';
    const zone = document.querySelector('.paste-zone[data-for="' + inputId + '"]');
    if (zone) zone.textContent = '📋 Click here then Ctrl+V to paste';
}
