<?php $title = 'Manage Premade Designs'; ?>
<?php require __DIR__ . '/../layouts/admin_header.php'; ?>

<div class="admin-header">
    <h1>Premade Designs</h1>
    <div class="admin-header-actions">
        <button type="button" class="btn btn-success" onclick="openAddModal()">+ Add New Design</button>
    </div>
</div>

<p class="page-description">Pre-made designs that customers can purchase directly. Organize them by section (Anime, etc.)</p>

<!-- Section Filter -->
<div class="section-filter">
    <label>Filter by Section:</label>
    <select id="sectionFilter" onchange="filterBySection()">
        <option value="">All Sections</option>
        <?php foreach ($sections as $section): ?>
            <option value="<?= htmlspecialchars($section['slug']) ?>"><?= htmlspecialchars($section['name']) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Image</th>
                <th>ID</th>
                <th>Design Name</th>
                <th>Section</th>
                <th>Products</th>
                <th>Price</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="designsTable">
            <?php if (empty($designs)): ?>
            <tr>
                <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                    No premade designs found. <a href="#" onclick="openAddModal(); return false;">Add your first design</a>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($designs as $design): ?>
            <tr data-section="<?= htmlspecialchars($design['section_slug']) ?>">
                <td class="product-thumb">
                    <?php if (!empty($design['image_path'])): ?>
                        <img src="/<?= htmlspecialchars($design['image_path']) ?>" alt="<?= htmlspecialchars($design['name']) ?>">
                    <?php else: ?>
                        <div class="no-image">No Image</div>
                    <?php endif; ?>
                </td>
                <td><?= $design['id'] ?></td>
                <td>
                    <strong><?= htmlspecialchars($design['name']) ?></strong>
                    <?php if ($design['description']): ?>
                    <br><small class="text-muted"><?= htmlspecialchars(substr($design['description'], 0, 50)) ?>...</small>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="section-badge"><?= htmlspecialchars($design['section_name']) ?></span>
                </td>
                <td>
                    <span class="count-badge"><?= $design['product_count'] ?? 0 ?> products</span>
                </td>
                <td><strong>$<?= number_format($design['price'], 2) ?></strong></td>
                <td>
                    <span class="status-badge <?= $design['active'] ? 'active' : 'inactive' ?>">
                        <?= $design['active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td>
                <td class="actions">
                    <button type="button" class="btn btn-sm" onclick="openEditModal(<?= $design['id'] ?>)">Edit</button>
                    <button type="button" class="btn btn-sm btn-position" onclick="window.open('/admin/premade/position/<?= $design['id'] ?>', '_blank', 'width=900,height=700')">Position</button>
                    <form method="post" action="/admin/premade/delete/<?= $design['id'] ?>" style="display:inline;" class="delete-form">
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

<!-- Add/Edit Design Modal -->
<div id="designModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Add New Design</h3>
            <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="modalLoading" class="modal-loading" style="display: none;">
                <div class="spinner"></div>
                <p>Loading...</p>
            </div>
            <form id="designForm" method="POST" enctype="multipart/form-data">
                <?= Csrf::field() ?>
                <input type="hidden" id="designId" name="design_id" value="">
                
                <div class="form-group">
                    <label for="section_id">Section *</label>
                    <select id="section_id" name="section_id" required>
                        <option value="">-- Select Section --</option>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?= $section['id'] ?>"><?= htmlspecialchars($section['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="designName">Design Name *</label>
                    <input type="text" id="designName" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="designDescription">Description</label>
                    <textarea id="designDescription" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="designPrice">Price ($) *</label>
                    <input type="number" id="designPrice" name="price" step="0.01" min="0" required>
                    <small>This is the design price (added to product base price)</small>
                </div>

                <div class="form-group">
                    <label>Available on Products *</label>
                    <div class="product-checkboxes" id="productCheckboxes">
                        <?php if (empty($products)): ?>
                            <p class="text-muted">No products available. <a href="/admin/products/add">Add products first</a></p>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                            <label class="product-checkbox">
                                <input type="checkbox" name="product_ids[]" value="<?= $product['id'] ?>">
                                <span class="product-checkbox-label">
                                    <?php if (!empty($product['image_path'])): ?>
                                        <img src="/<?= htmlspecialchars($product['image_path']) ?>" alt="" class="product-mini-thumb">
                                    <?php endif; ?>
                                    <?= htmlspecialchars($product['name']) ?>
                                    <small>($<?= number_format($product['base_price'], 2) ?>)</small>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <small>Select which products this design can be printed on</small>
                </div>

                <div class="form-group">
                    <label for="designImage">Design Image</label>
                    <input type="file" id="designImage" name="image" accept="image/*">
                    <small>Accepts: JPG, PNG, GIF, WebP (Max 10MB)</small>
                    <div id="currentImagePreview" style="display: none; margin-top: 10px;">
                        <img id="previewImg" src="" alt="Current Image" style="max-width: 200px; max-height: 150px; border-radius: 5px;">
                        <br>
                        <label style="margin-top: 5px; display: inline-block;">
                            <input type="checkbox" id="removeImage" name="remove_image" value="1">
                            Remove current image
                        </label>
                    </div>
                </div>
                
                <div class="form-group" id="activeGroup" style="display: none;">
                    <label>
                        <input type="checkbox" id="designActive" name="is_active" value="1" checked>
                        Active (visible to customers)
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-success" id="submitBtn">Add Design</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars(Asset::url('/js/premade_designs.js')) ?>" defer></script>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
