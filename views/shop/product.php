<?php $title = $product['name']; ?>
<?php require View::path('layouts/customer_header'); ?>

<section class="section product-detail-section">
    <div class="container">
        <?php
            $crumbs = [[t('header.nav.home', false), '/'], [t('header.nav.shop', false), '/shop'], [(string)$product['name'], null]];
            require View::path('partials/breadcrumb');
        ?>
        
        <div class="product-detail-grid">
            <div class="product-gallery">
                <div class="main-image">
    <?php
    $prodImg = $product['image_path'] ?? '';
    if ($prodImg && strpos($prodImg, 'public/') === 0) $prodImg = substr($prodImg, 7);
    if ($prodImg && $prodImg[0] !== '/') $prodImg = '/' . $prodImg;
    if (!$prodImg) $prodImg = '/images/placeholder.svg';
?>
                <img src="<?= htmlspecialchars($prodImg) ?>" alt="<?= htmlspecialchars($product['name']) ?>" data-fallback="/images/placeholder.svg" id="main-product-image">
                </div>
            </div>
            
            <div class="product-details">
                <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
                <?php if (!empty($product['description'])): ?>
                <p class="product-type-label"><?= htmlspecialchars($product['description']) ?></p>
                <?php endif; ?>
                <p class="product-price-large">€<span id="product-price"><?= number_format($product['retail_price'] ?? $product['base_price'], 2) ?></span></p>
                
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
                                // data-retail is the customer qty-1 price for this
                                // variant, computed server-side by the Pricing engine.
                                echo '<option value="' . $v['id'] . '" data-color="' . htmlspecialchars($v['color']) . '" data-retail="' . number_format((float)($v['retail_price'] ?? 0), 2, '.', '') . '">' . htmlspecialchars($v['size']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="quantity"><?= t('product.quantity') ?></label>
                        <div class="quantity-selector">
                            <button type="button" class="qty-btn" data-on-click="changeQty" data-args='[-1]'>-</button>
                            <input type="number" id="quantity" name="quantity" value="1" min="1" max="10">
                            <button type="button" class="qty-btn" data-on-click="changeQty" data-args='[1]'>+</button>
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

<?= View::json('product-data', ['variants' => $variants ?? [], 'productId' => $product['id'], 'fallbackPrice' => number_format((float)($product['retail_price'] ?? $product['base_price']), 2, '.', '')]) ?>
<?= View::script('/js/pages/product.js') ?>

<?php require View::path('layouts/customer_footer'); ?>
