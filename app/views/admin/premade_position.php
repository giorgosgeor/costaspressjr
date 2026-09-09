<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="icon" type="image/png" href="/images/logo.png">
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php // This page renders standalone, outside the admin layout, so it has to
          // pull in style.css itself — every colour, radius and font below is a
          // var() defined there. Without it the controls render unstyled. ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(Asset::url('/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(Asset::url('/css/premade_position.css')) ?>">
    <script src="/js/vendor/interact.min.js"></script>
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
    <?php
        // Placement is per garment. Start on the product named in ?product=,
        // otherwise the first one, and seed the inputs from THAT product's row.
        $selectedId = (int)($_GET['product'] ?? 0);
        $selected = null;
        foreach ($products as $p) { if ((int)$p['id'] === $selectedId) { $selected = $p; break; } }
        if (!$selected) $selected = $products[0] ?? null;
    ?>
    <input type="hidden" name="product_id" id="productId" value="<?= (int)($selected['id'] ?? 0) ?>">
    <input type="hidden" name="design_pos_x" id="posX" value="<?= $selected['pos_x'] ?? ($design['design_pos_x'] ?? 0) ?>">
    <input type="hidden" name="design_pos_y" id="posY" value="<?= $selected['pos_y'] ?? ($design['design_pos_y'] ?? 0) ?>">
    <input type="hidden" name="design_pos_size" id="posSize" value="<?= $selected['pos_size'] ?? ($design['design_pos_size'] ?? 55) ?>">
    <input type="hidden" name="design_pos_back_x" id="posBackX" value="<?= $selected['pos_back_x'] ?? ($design['design_pos_back_x'] ?? 0) ?>">
    <input type="hidden" name="design_pos_back_y" id="posBackY" value="<?= $selected['pos_back_y'] ?? ($design['design_pos_back_y'] ?? 0) ?>">
    <input type="hidden" name="design_pos_back_size" id="posBackSize" value="<?= $selected['pos_back_size'] ?? ($design['design_pos_back_size'] ?? 55) ?>">
    <input type="hidden" name="remove_back_image" id="removeBackImage" value="0">

<div class="pe-layout">

    <!-- LEFT: Mockup -->
    <div class="pe-mockup-panel">
        <?php if (!empty($products)): ?>
        <div class="pe-product-select">
            <select id="productSelect" onchange="loadProductImage()">
                <?php foreach ($products as $p): ?>
                <option value="<?= $p['id'] ?>"
                        <?= (int)$p['id'] === (int)($selected['id'] ?? 0) ? 'selected' : '' ?>
                        data-front="<?= htmlspecialchars($p['image_path'] ?? '') ?>"
                        data-back="<?= htmlspecialchars($p['back_image_path'] ?? '') ?>"
                        data-x="<?= htmlspecialchars((string)$p['pos_x']) ?>"
                        data-y="<?= htmlspecialchars((string)$p['pos_y']) ?>"
                        data-size="<?= htmlspecialchars((string)$p['pos_size']) ?>"
                        data-back-x="<?= htmlspecialchars((string)$p['pos_back_x']) ?>"
                        data-back-y="<?= htmlspecialchars((string)$p['pos_back_y']) ?>"
                        data-back-size="<?= htmlspecialchars((string)$p['pos_back_size']) ?>">
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
            <?php // Must be the SELECTED product, not the first — otherwise ?product=
                  // shows one garment in the dropdown and a different one in the mockup. ?>
            <?php if (!empty($selected['image_path'])): ?>
            <img id="mockupProduct" src="/<?= htmlspecialchars($selected['image_path']) ?>" class="pe-mockup-product" alt="Product">
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
// Placement belongs to the design/product PAIR, so this is seeded from the
// selected product's link row, and reloaded whenever the product changes.
let positions = {
    front: { x: <?= (float)($selected['pos_x'] ?? 0) ?>, y: <?= (float)($selected['pos_y'] ?? 0) ?>, size: <?= (float)($selected['pos_size'] ?? 55) ?> },
    back:  { x: <?= (float)($selected['pos_back_x'] ?? 0) ?>, y: <?= (float)($selected['pos_back_y'] ?? 0) ?>, size: <?= (float)($selected['pos_back_size'] ?? 55) ?> }
};
</script>
<script src="<?= htmlspecialchars(Asset::url('/js/premade_position.js')) ?>" defer></script>

</body>
</html>
