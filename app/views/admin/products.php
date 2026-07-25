<?php $title = 'Manage Products'; ?>
<?php $extraCss = ['/css/admin_products.css']; ?>
<?php require __DIR__ . '/../layouts/admin_header.php'; ?>

<div class="admin-header">
    <h1>Manage Products</h1>
    <div class="admin-header-actions">
        <a href="/admin/colors" class="btn">Manage Colors</a>
        <a href="/admin/products/add" class="btn btn-success">+ Add New Product</a>
    </div>
</div>

<p class="page-description">These are the base products that customers can customize with their designs.</p>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Image</th>
                <th>ID</th>
                <th>Product Name</th>
                <th>Base Price</th>
                <th>Sizes</th>
                <th>Colors</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
            <tr>
                <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                    No products found. <a href="/admin/products/add">Add your first product</a>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($products as $product): ?>
            <tr>
                <td class="product-thumb">
                    <?php 
                    $hasImage = false;
                    $imagePath = '';
                    if (!empty($product['image_path'])) {
                        $fullPath = __DIR__ . '/../../../public/' . $product['image_path'];
                        if (file_exists($fullPath)) {
                            $imagePath = '/' . $product['image_path'];
                            $hasImage = true;
                        }
                    }
                    if ($hasImage): ?>
                        <img src="<?= $imagePath ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                    <?php else: ?>
                        <div class="no-image">No Image</div>
                    <?php endif; ?>
                </td>
                <td><?= $product['id'] ?></td>
                <td>
                    <strong><?= htmlspecialchars($product['name']) ?></strong>
                    <?php if ($product['description']): ?>
                    <br><small class="text-muted"><?= htmlspecialchars(substr($product['description'], 0, 50)) ?>...</small>
                    <?php endif; ?>
                </td>
                <td><strong>$<?= number_format($product['base_price'], 2) ?></strong></td>
                <td>
                    <span class="count-badge"><?= $product['size_count'] ?? 0 ?> sizes</span>
                </td>
                <td>
                    <span class="count-badge"><?= $product['color_count'] ?? 0 ?> colors</span>
                </td>
                <td>
                    <span class="status-badge <?= $product['active'] ? 'active' : 'inactive' ?>">
                        <?= $product['active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td>
                <td class="actions">
                    <button type="button" class="btn btn-sm" onclick="openEditModal(<?= $product['id'] ?>)">Edit</button>
                    <form method="post" action="/admin/products/delete/<?= $product['id'] ?>" style="display:inline;" class="delete-form">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn-sm btn-danger delete-confirm">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Edit Product Modal -->
<div id="editProductModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>Edit Product</h3>
            <button type="button" class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="editModalLoading" class="modal-loading">
                <div class="spinner"></div>
                <p>Loading product...</p>
            </div>
            <form id="editProductForm" method="POST" enctype="multipart/form-data" style="display: none;">
                <?= Csrf::field() ?>
                <input type="hidden" id="editProductId" name="product_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="editName">Product Name *</label>
                        <input type="text" id="editName" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="editBasePrice">Base Price ($) *</label>
                        <input type="number" id="editBasePrice" name="base_price" step="0.01" min="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="editDescription">Description</label>
                    <textarea id="editDescription" name="description" rows="3"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="editImage">Front Image</label>
                        <div id="editCurrentImage" class="current-image-preview"></div>
                        <input type="file" id="editImage" name="image" accept="image/*">
                        <input type="hidden" id="removeImage" name="remove_image" value="0">
                        <button type="button" id="removeImageBtn" class="btn btn-sm btn-danger" style="margin-top:5px; display:none;" onclick="removeProductImage()">Remove Image</button>
                        <div class="paste-zone" data-for="editImage" tabindex="0">📋 Click here then Ctrl+V to paste</div>
                        <div class="paste-preview" id="paste-preview-editImage" style="display:none;">
                            <img id="paste-img-editImage" alt=""><button type="button" class="paste-clear-btn" onclick="clearPaste('editImage')">✕</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="editBackImage">Back Image</label>
                        <div id="editCurrentBackImage" class="current-image-preview"></div>
                        <input type="file" id="editBackImage" name="back_image" accept="image/*">
                        <input type="hidden" id="removeBackImage" name="remove_back_image" value="0">
                        <button type="button" id="removeBackImageBtn" class="btn btn-sm btn-danger" style="margin-top:5px; display:none;" onclick="removeProductBackImage()">Remove Back Image</button>
                        <div class="paste-zone" data-for="editBackImage" tabindex="0">📋 Click here then Ctrl+V to paste</div>
                        <div class="paste-preview" id="paste-preview-editBackImage" style="display:none;">
                            <img id="paste-img-editBackImage" alt=""><button type="button" class="paste-clear-btn" onclick="clearPaste('editBackImage')">✕</button>
                        </div>
                        <small style="display:block; margin-top:5px; color:#666;">Optional: For front/back design placement</small>
                    </div>
                    <div class="form-group">
                        <label for="editLeftSleeveImage">Left Sleeve Image</label>
                        <div id="editCurrentLeftSleeveImage" class="current-image-preview"></div>
                        <input type="file" id="editLeftSleeveImage" name="left_sleeve_image" accept="image/*">
                        <input type="hidden" id="removeLeftSleeveImage" name="remove_left_sleeve_image" value="0">
                        <button type="button" id="removeLeftSleeveImageBtn" class="btn btn-sm btn-danger" style="margin-top:5px; display:none;" onclick="removeProductLeftSleeveImage()">Remove Left Sleeve</button>
                        <div class="paste-zone" data-for="editLeftSleeveImage" tabindex="0">📋 Click here then Ctrl+V to paste</div>
                        <div class="paste-preview" id="paste-preview-editLeftSleeveImage" style="display:none;">
                            <img id="paste-img-editLeftSleeveImage" alt=""><button type="button" class="paste-clear-btn" onclick="clearPaste('editLeftSleeveImage')">✕</button>
                        </div>
                        <small style="display:block; margin-top:5px; color:#666;">Optional: For shirts with sleeve designs</small>
                    </div>
                    <div class="form-group">
                        <label for="editRightSleeveImage">Right Sleeve Image</label>
                        <div id="editCurrentRightSleeveImage" class="current-image-preview"></div>
                        <input type="file" id="editRightSleeveImage" name="right_sleeve_image" accept="image/*">
                        <input type="hidden" id="removeRightSleeveImage" name="remove_right_sleeve_image" value="0">
                        <button type="button" id="removeRightSleeveImageBtn" class="btn btn-sm btn-danger" style="margin-top:5px; display:none;" onclick="removeProductRightSleeveImage()">Remove Right Sleeve</button>
                        <div class="paste-zone" data-for="editRightSleeveImage" tabindex="0">📋 Click here then Ctrl+V to paste</div>
                        <div class="paste-preview" id="paste-preview-editRightSleeveImage" style="display:none;">
                            <img id="paste-img-editRightSleeveImage" alt=""><button type="button" class="paste-clear-btn" onclick="clearPaste('editRightSleeveImage')">✕</button>
                        </div>
                        <small style="display:block; margin-top:5px; color:#666;">Optional: For shirts with sleeve designs</small>
                    </div>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="editIsActive" name="is_active" value="1">
                        Product is active
                    </label>
                </div>

                <div class="form-section">
                    <h4>Sizes & Colors</h4>
                    <p class="section-hint">Manage sizes and their available colors.</p>
                    
                    <div class="size-presets">
                        <span>Quick Add:</span>
                        <button type="button" class="btn btn-sm" onclick="addClothingSizesEdit()">Clothing</button>
                        <button type="button" class="btn btn-sm" onclick="addMugSizesEdit()">Mug</button>
                    </div>

                    <div id="editSizesContainer">
                        <!-- Size rows loaded dynamically -->
                    </div>
                    
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addSizeRowEdit()">+ Add Size</button>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveProduct()">Save Changes</button>
        </div>
    </div>
</div>

<!-- Color Selection Modal (for size color picking) -->
<div id="colorModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Select Colors for <span id="modalSizeName">Size</span></h3>
            <button type="button" class="modal-close" onclick="closeColorModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="colorCheckboxes" class="color-checkbox-grid">
                <!-- Colors loaded via AJAX -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeColorModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveColorSelection()">Save Selection</button>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars(Asset::url('/js/admin_products.js')) ?>" defer></script>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
