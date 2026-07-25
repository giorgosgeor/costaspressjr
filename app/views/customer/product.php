<?php $title = $product['name']; ?>
<?php require __DIR__ . '/../layouts/customer_header.php'; ?>

<section class="section product-detail-section">
    <div class="container">
        <nav class="breadcrumb">
            <a href="/"><?= t('header.nav.home') ?></a> &gt;
            <a href="/shop"><?= t('header.nav.shop') ?></a> &gt;
            <span><?= htmlspecialchars($product['name']) ?></span>
        </nav>
        
        <div class="product-detail-grid">
            <div class="product-gallery">
                <div class="main-image">
    <?php
    $prodImg = $product['image_path'] ?? '';
    if ($prodImg && strpos($prodImg, 'public/') === 0) $prodImg = substr($prodImg, 7);
    if ($prodImg && $prodImg[0] !== '/') $prodImg = '/' . $prodImg;
    if (!$prodImg) $prodImg = '/images/placeholder.svg';
?>
                <img src="<?= htmlspecialchars($prodImg) ?>" alt="<?= htmlspecialchars($product['name']) ?>" onerror="this.src='/images/placeholder.svg'" id="main-product-image">
                </div>
            </div>
            
            <div class="product-details">
                <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
                <p class="product-type-label"><?= htmlspecialchars($product['type']) ?></p>
                <p class="product-price-large">€<span id="product-price"><?= number_format($product['base_price'], 2) ?></span></p>
                
                <form action="/cart/add" method="post" class="product-form">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    <?php if (!empty($variants)): ?>
                    <div class="form-group">
                        <label><?= t('product.color') ?></label>
                        <div class="color-options" id="colorOptions">
                            <?php 
                            $colors = [];
                            foreach ($variants as $v) {
                                if (!in_array($v['color'], $colors) && $v['color']) {
                                    $colors[] = $v['color'];
                                    echo '<label class="color-option">';
                                    echo '<input type="radio" name="color" value="' . htmlspecialchars($v['color']) . '">';
                                    echo '<span class="color-swatch" style="background-color: ' . htmlspecialchars($v['color']) . '" title="' . htmlspecialchars($v['color']) . '"></span>';
                                    echo '</label>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="size"><?= t('product.size') ?></label>
                        <select id="size" name="variant_id" required>
                            <option value=""><?= t('product.select_size') ?></option>
                            <?php 
                            foreach ($variants as $v) {
                                echo '<option value="' . $v['id'] . '" data-color="' . htmlspecialchars($v['color']) . '" data-price="' . $v['price_modifier'] . '">' . htmlspecialchars($v['size']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="quantity"><?= t('product.quantity') ?></label>
                        <div class="quantity-selector">
                            <button type="button" class="qty-btn" onclick="changeQty(-1)">-</button>
                            <input type="number" id="quantity" name="quantity" value="1" min="1" max="10">
                            <button type="button" class="qty-btn" onclick="changeQty(1)">+</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="description"><?= t('product.instructions') ?></label>
                        <textarea id="description" name="description" rows="3" placeholder="<?= t('product.instructions_placeholder') ?>"></textarea>
                    </div>
                    <button type="submit" class="btn btn-lg btn-block btn-success"><?= t('product.add_to_cart') ?></button>
                </form>
                <button id="startDesigningBtn" class="btn btn-primary" style="margin-top:18px;"><?= t('product.start_designing') ?></button>
                <div class="product-meta">
                    <div class="meta-item"><?= t('product.free_shipping') ?></div>
                    <div class="meta-item"><?= t('product.easy_returns') ?></div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
function changeQty(delta) {
    const input = document.getElementById('quantity');
    let val = parseInt(input.value) + delta;
    if (val < 1) val = 1;
    if (val > 10) val = 10;
    input.value = val;
}

// --- Color/Size Filtering Logic ---
const variants = <?php echo json_encode($variants ?? []); ?>;
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
        sessionStorage.setItem('custom_product_id', <?= json_encode($product['id']) ?>);
        sessionStorage.setItem('custom_color', selectedColor);
        sessionStorage.setItem('custom_size_variant_id', selectedSize);
        window.location.href = '/shop/custom';
    }
});

// Update price when size changes
sizeSelect?.addEventListener('change', function() {
    const basePrice = <?= $product['base_price'] ?>;
    const modifier = parseFloat(this.options[this.selectedIndex].dataset.price) || 0;
    document.getElementById('product-price').textContent = (basePrice + modifier).toFixed(2);
});
</script>

<?php require __DIR__ . '/../layouts/customer_footer.php'; ?>
