<?php $extraCss = ['/css/admin_products.css']; ?>
<?php include __DIR__ . '/../layouts/admin_header.php'; ?>

<div class="admin-container">
    <div class="admin-header">
        <h1>Add New Product</h1>
        <a href="/admin/products" class="btn btn-secondary">← Back to Products</a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/admin/products/add" enctype="multipart/form-data" class="product-form">
        <?= Csrf::field() ?>
        <div class="form-section">
            <h3>Basic Information</h3>
            
            <div class="form-group">
                <label for="name">Product Name *</label>
                <input type="text" id="name" name="name" required placeholder="e.g., T-Shirt, Hoodie, Mug">
            </div>

            <div class="form-group">
                <label for="base_price">Base Price ($) *</label>
                <input type="number" id="base_price" name="base_price" step="0.01" min="0" required placeholder="0.00">
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4" placeholder="Product description..."></textarea>
            </div>

            <div class="form-group">
                <label for="image">Product Image</label>
                <input type="file" id="image" name="image" accept="image/*">
                <div class="paste-zone" data-for="image" tabindex="0">📋 Or click here and press Ctrl+V to paste</div>
                <div class="paste-preview" id="paste-preview-image" style="display:none;">
                    <img id="paste-img-image" alt="Pasted image">
                    <button type="button" class="paste-clear-btn" onclick="clearPaste('image')">✕ Clear</button>
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
            <button type="submit" class="btn btn-primary">Create Product</button>
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
</script>
<script src="<?= htmlspecialchars(Asset::url('/js/products_add.js')) ?>" defer></script>
<?php include __DIR__ . '/../layouts/admin_footer.php'; ?>
