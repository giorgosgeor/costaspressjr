<?php $extraCss = ['/css/admin_products.css']; ?>
<?php include __DIR__ . '/../layouts/admin_header.php'; ?>

<div class="admin-container">
    <div class="admin-header">
        <h1>Edit Product: <?= htmlspecialchars($product['name']) ?></h1>
        <a href="/admin/products" class="btn btn-secondary">← Back to Products</a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/admin/products/edit/<?= $product['id'] ?>" enctype="multipart/form-data" class="product-form">
        <?= Csrf::field() ?>
        <div class="form-section">
            <h3>Basic Information</h3>
            
            <div class="form-group">
                <label for="name">Product Name *</label>
                <input type="text" id="name" name="name" required value="<?= htmlspecialchars($product['name']) ?>">
            </div>

            <div class="form-group">
                <label for="base_price">Base Price ($) *</label>
                <input type="number" id="base_price" name="base_price" step="0.01" min="0" required value="<?= htmlspecialchars($product['base_price']) ?>">
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_active" <?= $product['active'] ? 'checked' : '' ?>>
                    Product is Active
                </label>
            </div>
        </div>

        <div class="form-section">
            <h3>Front Image</h3>
            <?php 
            $frontImagePath = __DIR__ . '/../../../../public/images/products/' . $product['id'] . '.png';
            $frontImagePathJpg = __DIR__ . '/../../../../public/images/products/' . $product['id'] . '.jpg';
            $hasFrontImage = file_exists($frontImagePath) || file_exists($frontImagePathJpg);
            ?>
            
            <?php if ($hasFrontImage): ?>
            <div class="current-image">
                <p>Current front image:</p>
                <img src="/images/products/<?= $product['id'] ?>.png?t=<?= time() ?>" alt="Front" 
                     onerror="this.src='/images/products/<?= $product['id'] ?>.jpg?t=<?= time() ?>'"
                     style="max-width: 200px; max-height: 200px; border: 1px solid #ddd; border-radius: 4px;">
                <label class="checkbox-label remove-image-label">
                    <input type="checkbox" name="remove_image" value="1">
                    Remove front image
                </label>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="image"><?= $hasFrontImage ? 'Replace Front Image' : 'Upload Front Image' ?></label>
                <input type="file" id="image" name="image" accept="image/*">
                <div class="paste-zone" data-for="image" tabindex="0">📋 Or click here and press Ctrl+V to paste</div>
                <div class="paste-preview" id="paste-preview-image" style="display:none;">
                    <img id="paste-img-image" alt="Pasted image">
                    <button type="button" class="paste-clear-btn" onclick="clearPaste('image')">✕ Clear</button>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3>Back Image</h3>
            <?php 
            $backImagePath = __DIR__ . '/../../../../public/images/products/' . $product['id'] . '_back.png';
            $backImagePathJpg = __DIR__ . '/../../../../public/images/products/' . $product['id'] . '_back.jpg';
            $hasBackImage = file_exists($backImagePath) || file_exists($backImagePathJpg);
            ?>
            
            <?php if ($hasBackImage): ?>
            <div class="current-image">
                <p>Current back image:</p>
                <img src="/images/products/<?= $product['id'] ?>_back.png?t=<?= time() ?>" alt="Back" 
                     onerror="this.src='/images/products/<?= $product['id'] ?>_back.jpg?t=<?= time() ?>'"
                     style="max-width: 200px; max-height: 200px; border: 1px solid #ddd; border-radius: 4px;">
                <label class="checkbox-label remove-image-label">
                    <input type="checkbox" name="remove_back_image" value="1">
                    Remove back image
                </label>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="back_image"><?= $hasBackImage ? 'Replace Back Image' : 'Upload Back Image' ?></label>
                <input type="file" id="back_image" name="back_image" accept="image/*">
                <div class="paste-zone" data-for="back_image" tabindex="0">📋 Or click here and press Ctrl+V to paste</div>
                <div class="paste-preview" id="paste-preview-back_image" style="display:none;">
                    <img id="paste-img-back_image" alt="Pasted image">
                    <button type="button" class="paste-clear-btn" onclick="clearPaste('back_image')">✕ Clear</button>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3>Left Sleeve Image (Optional)</h3>
            <p class="section-hint">For shirts and long-sleeve products only</p>
            <?php 
            $leftSleeveImagePath = __DIR__ . '/../../../../public/images/products/' . $product['id'] . '_left_sleeve.png';
            $leftSleeveImagePathJpg = __DIR__ . '/../../../../public/images/products/' . $product['id'] . '_left_sleeve.jpg';
            $hasLeftSleeveImage = file_exists($leftSleeveImagePath) || file_exists($leftSleeveImagePathJpg);
            ?>
            
            <?php if ($hasLeftSleeveImage): ?>
            <div class="current-image">
                <p>Current left sleeve image:</p>
                <img src="/images/products/<?= $product['id'] ?>_left_sleeve.png?t=<?= time() ?>" alt="Left Sleeve" 
                     onerror="this.src='/images/products/<?= $product['id'] ?>_left_sleeve.jpg?t=<?= time() ?>'"
                     style="max-width: 200px; max-height: 200px; border: 1px solid #ddd; border-radius: 4px;">
                <label class="checkbox-label remove-image-label">
                    <input type="checkbox" name="remove_left_sleeve_image" value="1">
                    Remove left sleeve image
                </label>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="left_sleeve_image"><?= $hasLeftSleeveImage ? 'Replace Left Sleeve Image' : 'Upload Left Sleeve Image' ?></label>
                <input type="file" id="left_sleeve_image" name="left_sleeve_image" accept="image/*">
                <div class="paste-zone" data-for="left_sleeve_image" tabindex="0">📋 Or click here and press Ctrl+V to paste</div>
                <div class="paste-preview" id="paste-preview-left_sleeve_image" style="display:none;">
                    <img id="paste-img-left_sleeve_image" alt="Pasted image">
                    <button type="button" class="paste-clear-btn" onclick="clearPaste('left_sleeve_image')">✕ Clear</button>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3>Right Sleeve Image (Optional)</h3>
            <p class="section-hint">For shirts and long-sleeve products only</p>
            <?php 
            $rightSleeveImagePath = __DIR__ . '/../../../../public/images/products/' . $product['id'] . '_right_sleeve.png';
            $rightSleeveImagePathJpg = __DIR__ . '/../../../../public/images/products/' . $product['id'] . '_right_sleeve.jpg';
            $hasRightSleeveImage = file_exists($rightSleeveImagePath) || file_exists($rightSleeveImagePathJpg);
            ?>
            
            <?php if ($hasRightSleeveImage): ?>
            <div class="current-image">
                <p>Current right sleeve image:</p>
                <img src="/images/products/<?= $product['id'] ?>_right_sleeve.png?t=<?= time() ?>" alt="Right Sleeve" 
                     onerror="this.src='/images/products/<?= $product['id'] ?>_right_sleeve.jpg?t=<?= time() ?>'"
                     style="max-width: 200px; max-height: 200px; border: 1px solid #ddd; border-radius: 4px;">
                <label class="checkbox-label remove-image-label">
                    <input type="checkbox" name="remove_right_sleeve_image" value="1">
                    Remove right sleeve image
                </label>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="right_sleeve_image"><?= $hasRightSleeveImage ? 'Replace Right Sleeve Image' : 'Upload Right Sleeve Image' ?></label>
                <input type="file" id="right_sleeve_image" name="right_sleeve_image" accept="image/*">
                <div class="paste-zone" data-for="right_sleeve_image" tabindex="0">📋 Or click here and press Ctrl+V to paste</div>
                <div class="paste-preview" id="paste-preview-right_sleeve_image" style="display:none;">
                    <img id="paste-img-right_sleeve_image" alt="Pasted image">
                    <button type="button" class="paste-clear-btn" onclick="clearPaste('right_sleeve_image')">✕ Clear</button>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3>Sizes & Colors</h3>
            <p class="section-hint">Add sizes and select which colors are available for each size.</p>
            
            <div class="size-presets">
                <span>Quick Add:</span>
                <button type="button" class="btn btn-sm" onclick="addClothingSizes()">Clothing (XS-3XL)</button>
                <button type="button" class="btn btn-sm" onclick="addMugSizes()">Mug (11oz, 15oz)</button>
            </div>

            <div id="sizes-container">
                <!-- Size rows will be added here -->
            </div>
            
            <button type="button" class="btn btn-secondary" onclick="addSizeRow()">+ Add Size</button>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Update Product</button>
            <a href="/admin/products" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<!-- Color Selection Modal -->
<div id="colorModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Select Colors for <span id="modalSizeName">Size</span></h3>
            <button type="button" class="modal-close" onclick="closeColorModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="colorCheckboxes" class="color-checkbox-grid">
                <?php foreach ($colors as $color): ?>
                <label class="color-checkbox-item">
                    <input type="checkbox" value="<?= $color['id'] ?>" data-color-name="<?= htmlspecialchars($color['name']) ?>">
                    <span class="color-swatch" style="background-color: <?= htmlspecialchars($color['hex_code']) ?>;"></span>
                    <span class="color-name"><?= htmlspecialchars($color['name']) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            <?php if (empty($colors)): ?>
            <p class="no-colors-msg">No colors available. <a href="/admin/colors">Add colors first</a>.</p>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeColorModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveColorSelection()">Save Selection</button>
        </div>
    </div>
</div>

<script>
const availableColors = <?= json_encode($colors) ?>;
const existingSizes = <?= json_encode($sizes) ?>;
const existingVariants = <?= json_encode($variants) ?>;
</script>
<script src="<?= htmlspecialchars(Asset::url('/js/products_edit.js')) ?>" defer></script>
<?php include __DIR__ . '/../layouts/admin_footer.php'; ?>
