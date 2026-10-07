/* product.js — from views/shop/product.php, loaded where the inline script used to run. */
const productData = JSON.parse(document.getElementById('product-data').textContent);

function changeQty(delta) {
    const input = document.getElementById('quantity');
    let val = parseInt(input.value) + delta;
    if (val < 1) val = 1;
    if (val > 10) val = 10;
    input.value = val;
}

// --- Color/Size Filtering Logic ---
const variants = productData.variants;
const sizeSelect = document.getElementById('size');
const colorInputs = document.querySelectorAll('input[name="color"]');
const startDesigningBtn = document.getElementById('startDesigningBtn');
let selectedColor = null;
let selectedSize = null;

function filterSizesByColor(selectedColorVal) {
    for (let i = 0; i < sizeSelect.options.length; i++) {
        const opt = sizeSelect.options[i];
        if (!opt.value) continue;
        if (opt.getAttribute('data-color') === selectedColorVal) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    }
    sizeSelect.value = '';
    selectedSize = null;
    checkEnableStart();
}

colorInputs.forEach(input => {
    input.addEventListener('change', function() {
        selectedColor = this.value;
        filterSizesByColor(this.value);
        checkEnableStart();
    });
});

sizeSelect?.addEventListener('change', function() {
    selectedSize = this.value;
    checkEnableStart();
});

function checkEnableStart() {
    startDesigningBtn.disabled = !(selectedColor && selectedSize);
}

// Optionally, auto-select first color
if (colorInputs.length) {
    colorInputs[0].checked = true;
    selectedColor = colorInputs[0].value;
    filterSizesByColor(colorInputs[0].value);
}

startDesigningBtn.disabled = true;
startDesigningBtn.addEventListener('click', function() {
    if (selectedColor && selectedSize) {
        sessionStorage.setItem('custom_product_id', productData.productId);
        sessionStorage.setItem('custom_color', selectedColor);
        sessionStorage.setItem('custom_size_variant_id', selectedSize);
        window.location.href = '/shop/custom';
    }
});

// Update price when size changes — data-retail carries the server-computed
// customer price for the variant (never expose the raw supplier cost here).
sizeSelect?.addEventListener('change', function() {
    const fallback = productData.fallbackPrice;
    const retail = this.options[this.selectedIndex].dataset.retail;
    document.getElementById('product-price').textContent = parseFloat(retail || fallback).toFixed(2);
});
