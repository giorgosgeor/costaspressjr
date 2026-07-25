<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="stylesheet" href="/css/premade_position.css">
    <script src="https://cdn.jsdelivr.net/npm/interactjs/dist/interact.min.js"></script>
</head>
<body>

<div class="pe-header">
    <h1>Position Editor — <?= htmlspecialchars($design['name']) ?> <small style="opacity:0.7;font-weight:400;">(<?= htmlspecialchars($design['section_name']) ?>)</small></h1>
    <a href="/admin/premade" class="back-link">← Back to Designs</a>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="pe-saved-banner show">Position saved successfully.</div>
<?php endif; ?>

<form method="POST" action="/admin/premade/position/<?= $design['id'] ?>" enctype="multipart/form-data">
    <?= Csrf::field() ?>
    <input type="hidden" name="design_pos_x" id="posX" value="<?= $design['design_pos_x'] ?? 0 ?>">
    <input type="hidden" name="design_pos_y" id="posY" value="<?= $design['design_pos_y'] ?? 0 ?>">
    <input type="hidden" name="design_pos_size" id="posSize" value="<?= $design['design_pos_size'] ?? 55 ?>">
    <input type="hidden" name="design_pos_back_x" id="posBackX" value="<?= $design['design_pos_back_x'] ?? 0 ?>">
    <input type="hidden" name="design_pos_back_y" id="posBackY" value="<?= $design['design_pos_back_y'] ?? 0 ?>">
    <input type="hidden" name="design_pos_back_size" id="posBackSize" value="<?= $design['design_pos_back_size'] ?? 55 ?>">
    <input type="hidden" name="remove_back_image" id="removeBackImage" value="0">

<div class="pe-layout">

    <!-- LEFT: Mockup -->
    <div class="pe-mockup-panel">
        <?php if (!empty($products)): ?>
        <div class="pe-product-select">
            <select id="productSelect" onchange="loadProductImage()">
                <?php foreach ($products as $p): ?>
                <option value="<?= $p['id'] ?>"
                        data-front="<?= htmlspecialchars($p['image_path'] ?? '') ?>"
                        data-back="<?= htmlspecialchars($p['back_image_path'] ?? '') ?>">
                    <?= htmlspecialchars($p['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="pe-side-toggle">
            <button type="button" class="pe-side-btn active" id="btnFront" onclick="switchSide('front')">Front</button>
            <button type="button" class="pe-side-btn" id="btnBack" onclick="switchSide('back')">Back</button>
        </div>

        <div class="pe-mockup-container" id="mockupContainer">
            <?php if (!empty($products[0]['image_path'])): ?>
            <img id="mockupProduct" src="/<?= htmlspecialchars($products[0]['image_path']) ?>" class="pe-mockup-product" alt="Product">
            <?php else: ?>
            <div class="pe-mockup-placeholder" id="mockupProduct">No product image</div>
            <?php endif; ?>

            <div class="pe-design-area" id="designArea">
                <div class="pe-design-el <?= !empty($design['image_path']) ? 'visible' : '' ?>" id="designEl">
                    <img id="designImg"
                         src="<?= !empty($design['image_path']) ? '/' . htmlspecialchars($design['image_path']) : '' ?>"
                         class="pe-design-img" alt="Design">
                    <div class="resize-handle"></div>
                </div>
            </div>
        </div>

        <div class="pe-mockup-hint">Drag design to reposition · Drag corner handle to resize</div>
    </div>

    <!-- RIGHT: Controls -->
    <div class="pe-controls-panel">

        <div class="pe-section">
            <h3>Front Design</h3>
            <div class="pe-image-preview" id="frontPreview">
                <?php if (!empty($design['image_path'])): ?>
                    <img src="/<?= htmlspecialchars($design['image_path']) ?>" alt="Front design">
                <?php else: ?>
                    <span class="pe-image-placeholder">No front image</span>
                <?php endif; ?>
            </div>
            <?php if (empty($design['image_path'])): ?>
            <p class="pe-no-image-note">Upload a front image via the main edit form first.</p>
            <?php endif; ?>
        </div>

        <div class="pe-section">
            <h3>Back Design <span style="color:#bbb;font-weight:400;text-transform:none;">(optional)</span></h3>
            <div class="pe-image-preview" id="backPreview">
                <?php if (!empty($design['back_image_path'])): ?>
                    <img id="backPreviewImg" src="/<?= htmlspecialchars($design['back_image_path']) ?>" alt="Back design">
                <?php else: ?>
                    <span class="pe-image-placeholder" id="backPreviewPlaceholder">No back image</span>
                    <img id="backPreviewImg" src="" style="display:none;" alt="Back design preview">
                <?php endif; ?>
            </div>
            <label class="pe-upload-label" for="backImageInput">
                <?= !empty($design['back_image_path']) ? 'Replace' : 'Upload back image' ?>
            </label>
            <input type="file" name="back_image" id="backImageInput" accept="image/*" style="display:none;" onchange="previewBackImage(this)">
            <?php if (!empty($design['back_image_path'])): ?>
            <span class="pe-remove-btn" onclick="confirmRemoveBack()">Remove</span>
            <?php endif; ?>
        </div>

        <div class="pe-section">
            <h3>Position Controls</h3>
            <div class="pe-pos-controls">
                <button type="button" class="pe-pos-btn" onclick="resetPosition()">Reset</button>
                <button type="button" class="pe-pos-btn" onclick="centerDesign()">Center</button>
            </div>
        </div>

        <div class="pe-save-section">
            <button type="submit" class="pe-save-btn">Save Position</button>
        </div>
    </div>

</div>
</form>

<script>
// PHP-generated data — must be inline
const frontImage       = '<?= addslashes($design['image_path'] ?? '') ?>';
const backImageInitial = '<?= addslashes($design['back_image_path'] ?? '') ?>';
let positions = {
    front: { x: <?= (float)($design['design_pos_x'] ?? 0) ?>, y: <?= (float)($design['design_pos_y'] ?? 0) ?>, size: <?= (float)($design['design_pos_size'] ?? 55) ?> },
    back:  { x: <?= (float)($design['design_pos_back_x'] ?? 0) ?>, y: <?= (float)($design['design_pos_back_y'] ?? 0) ?>, size: <?= (float)($design['design_pos_back_size'] ?? 55) ?> }
};
</script>
<script src="<?= htmlspecialchars(Asset::url('/js/premade_position.js')) ?>" defer></script>

</body>
</html>
