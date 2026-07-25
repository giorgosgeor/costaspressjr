let sizeIndex = 0;
let currentSizeRow = null;

function addSizeRow(sizeName = '', priceModifier = '0.00', selectedColors = []) {
    const container = document.getElementById('sizes-container');
    const row = document.createElement('div');
    row.className = 'size-row';
    row.dataset.index = sizeIndex;
    const colorCount = selectedColors.length;
    row.innerHTML = `
        <input type="text" name="sizes[${sizeIndex}][name]" placeholder="Size name" value="${sizeName}" required>
        <button type="button" class="btn-colors" onclick="openColorModal(this)">
            Colors <span class="color-count">${colorCount}</span>
        </button>
        <input type="number" name="sizes[${sizeIndex}][price_modifier]" placeholder="+$" step="0.01" value="${priceModifier}" title="Additional price for this size">
        <input type="hidden" name="sizes[${sizeIndex}][colors]" value="${selectedColors.join(',')}">
        <button type="button" class="btn-remove" onclick="removeSizeRow(this)">✕</button>
    `;
    container.appendChild(row);
    sizeIndex++;
}

function removeSizeRow(btn) {
    btn.closest('.size-row').remove();
}

function addClothingSizes() {
    ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL'].forEach(size => addSizeRow(size, '0.00'));
}

function addMugSizes() {
    addSizeRow('11oz', '0.00');
    addSizeRow('15oz', '2.00');
}

function openColorModal(btn) {
    currentSizeRow = btn.closest('.size-row');
    const sizeName = currentSizeRow.querySelector('input[type="text"]').value || 'this size';
    const hiddenInput = currentSizeRow.querySelector('input[type="hidden"]');
    const selectedColors = hiddenInput.value ? hiddenInput.value.split(',').map(Number) : [];
    document.getElementById('modalSizeName').textContent = sizeName;
    document.querySelectorAll('#colorCheckboxes input[type="checkbox"]').forEach(cb => {
        cb.checked = selectedColors.includes(parseInt(cb.value));
    });
    document.getElementById('colorModal').classList.add('active');
}

function closeColorModal() {
    document.getElementById('colorModal').classList.remove('active');
    currentSizeRow = null;
}

function saveColorSelection() {
    if (!currentSizeRow) return;
    const checkboxes = document.querySelectorAll('#colorCheckboxes input[type="checkbox"]:checked');
    const selectedIds = Array.from(checkboxes).map(cb => cb.value);
    currentSizeRow.querySelector('input[type="hidden"]').value = selectedIds.join(',');
    currentSizeRow.querySelector('.color-count').textContent = selectedIds.length;
    closeColorModal();
}

document.getElementById('colorModal').addEventListener('click', function(e) {
    if (e.target === this) closeColorModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeColorModal();
});

// ===== PASTE IMAGE SUPPORT =====
let activePasteInputId = null;

document.querySelectorAll('.paste-zone').forEach(zone => {
    zone.addEventListener('click', () => {
        activePasteInputId = zone.dataset.for;
        document.querySelectorAll('.paste-zone').forEach(z => z.classList.remove('active'));
        zone.classList.add('active');
        zone.focus();
    });
    zone.addEventListener('focus', () => {
        activePasteInputId = zone.dataset.for;
        zone.classList.add('active');
    });
    zone.addEventListener('blur', () => zone.classList.remove('active'));
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
            const capturedId = activePasteInputId;
            const reader = new FileReader();
            reader.onload = function(ev) {
                const preview = document.getElementById('paste-preview-' + capturedId);
                const img = document.getElementById('paste-img-' + capturedId);
                if (preview && img) { img.src = ev.target.result; preview.style.display = 'flex'; }
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
    if (zone) zone.textContent = '📋 Or click here and press Ctrl+V to paste';
}

// Load existing sizes on page load
document.addEventListener('DOMContentLoaded', function() {
    existingSizes.forEach(size => {
        const sizeColors = existingVariants
            .filter(v => v.size_id == size.id)
            .map(v => v.color_id);
        addSizeRow(size.size_name, size.price_modifier || '0.00', sizeColors);
    });
});
