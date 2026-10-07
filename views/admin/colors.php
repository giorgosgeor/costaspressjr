<?php $title = 'Manage Colors'; ?>
<?php require __DIR__ . '/../layouts/admin_header.php'; ?>

<div class="admin-header">
    <h1>Manage Colors</h1>
    <a href="/admin/products" class="btn">Back to Products</a>
</div>

<div class="colors-page">
    <div class="form-section" style="max-width: 400px; margin-bottom: 30px;">
        <h3 class="form-section-title">Add New Color</h3>
        <form method="post" action="/admin/colors/add" class="add-color-form">
            <?= Csrf::field() ?>
            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <label for="color_name">Color Name</label>
                    <input type="text" id="color_name" name="color_name" required placeholder="e.g., Navy Blue">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="color_hex">Color</label>
                    <input type="color" id="color_hex" name="color_hex" value="#000000" class="color-picker">
                </div>
                <div class="form-group" style="flex: 0;">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-success">Add</button>
                </div>
            </div>
        </form>
    </div>
    
    <div class="form-section">
        <h3 class="form-section-title">Available Colors (<?= count($colors) ?>)</h3>
        
        <?php if (empty($colors)): ?>
        <p class="form-hint">No colors defined yet. Add your first color above.</p>
        <?php else: ?>
        <div class="colors-grid">
            <?php foreach ($colors as $color):
                $hex = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string)$color['color_hex']) ? $color['color_hex'] : '#000000';
            ?>
            <div class="color-card">
                <div class="color-preview" style="background-color: <?= htmlspecialchars($hex, ENT_QUOTES, 'UTF-8') ?>;"></div>
                <div class="color-info">
                    <strong><?= htmlspecialchars($color['color_name']) ?></strong>
                    <span class="color-hex-label"><?= htmlspecialchars($hex, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="color-actions">
                    <form method="post" action="/admin/colors/delete/<?= $color['id'] ?>" style="display:inline;" class="delete-form">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn-sm btn-danger delete-confirm" title="Delete">×</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
