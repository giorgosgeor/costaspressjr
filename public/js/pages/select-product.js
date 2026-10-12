/* select-product.js — from views/shop/select_product.php, loaded where the inline script used to run. */
const selectProductData = JSON.parse(document.getElementById('select-product-data').textContent);

const products = selectProductData.products;
let selectedProduct = products[0] || null;
let selectedColor = null;
let selectedSize = null;

function renderProduct(product) {
    document.getElementById('productName').textContent = product.name;
    // Set main image to first color by default
    if (product.colors && product.colors.length > 0) {
        selectedColor = product.colors[0];
        document.getElementById('mainProductImage').src = selectedColor ? '/' + selectedColor.image_path : '';
    } else {
        selectedColor = null;
        document.getElementById('mainProductImage').src = '';
    }
    // Render color swatches
    let swatchHtml = '';
    if (product.colors) {
        product.colors.forEach(color => {
            swatchHtml += `<span class="color-swatch" data-color-id="${color.id}" style="display:inline-block;width:28px;height:28px;border-radius:50%;background:${color.hex};margin:0 4px;cursor:pointer;border:2px solid #ccc;"></span>`;
        });
    }
    document.getElementById('colorSwatches').innerHTML = swatchHtml;
    // Render size options. A product with a single size ("One Size" caps) offers
    // no actual choice, so select it silently instead of showing a dropdown.
    const sizes = product.sizes || [];
    if (sizes.length < 2) {
        selectedSize = sizes[0] || null;
        document.getElementById('sizeOptions').innerHTML = '';
        return;
    }
    selectedSize = null;
    let sizeHtml = '<label>' + (window.I18N ? window.I18N.t('shop.select.size_label') : 'Size:') + '</label><select id="sizeSelect"><option value="">' + (window.I18N ? window.I18N.t('shop.select.select_size') : 'Select size') + '</option>';
    sizes.forEach(size => {
        sizeHtml += `<option value="${size.id}">${size.size_name}</option>`;
    });
    sizeHtml += '</select>';
    document.getElementById('sizeOptions').innerHTML = sizeHtml;
}

// Initial render
if (selectedProduct) renderProduct(selectedProduct);

document.getElementById('colorSwatches').addEventListener('click', function(e) {
    if (e.target.classList.contains('color-swatch')) {
        const colorId = e.target.getAttribute('data-color-id');
        selectedColor = selectedProduct.colors.find(c => c.id == colorId);
        document.getElementById('mainProductImage').src = selectedColor ? '/' + selectedColor.image_path : '';
    }
});

document.getElementById('sizeOptions').addEventListener('change', function(e) {
    if (e.target.id === 'sizeSelect') {
        selectedSize = selectedProduct.sizes.find(s => s.id == e.target.value);
    }
});

// Opening a product needs no script: each card's name is a link to the
// product's page (/product/{id}-{slug}), stretched over the card in CSS.
