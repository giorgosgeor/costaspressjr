<?php $title = 'Design Area Editor'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - Admin</title>
    <link rel="stylesheet" href="/css/design_area_editor.css">
    <script src="https://cdn.jsdelivr.net/npm/interactjs/dist/interact.min.js"></script>
</head>
<body>

<div class="dae-header">
    <h1>📐 Design Area Editor</h1>
    <a href="/admin/products">← Back to Products</a>
</div>
<div class="dae-saved" id="savedBanner">✅ Saved successfully!</div>

<div class="dae-layout">
    <!-- LEFT: Mockup -->
    <div class="dae-mockup">
        <div class="view-tabs" id="viewTabs">
            <button class="view-tab active" data-view="front">Front</button>
            <button class="view-tab" data-view="back" id="tabBack" style="display:none">Back</button>
            <button class="view-tab" data-view="lsleeve" id="tabLsleeve" style="display:none">Left Sleeve</button>
            <button class="view-tab" data-view="rsleeve" id="tabRsleeve" style="display:none">Right Sleeve</button>
        </div>

        <div class="mockup-wrap" id="mockupWrap" style="display:none">
            <img id="mockupImg" src="" alt="Product mockup">
            <div id="designBox">
                <div class="box-label" id="boxLabel">Design Area</div>
                <div class="resize-handle nw" data-dir="nw"></div>
                <div class="resize-handle n"  data-dir="n"></div>
                <div class="resize-handle ne" data-dir="ne"></div>
                <div class="resize-handle e"  data-dir="e"></div>
                <div class="resize-handle se" data-dir="se"></div>
                <div class="resize-handle s"  data-dir="s"></div>
                <div class="resize-handle sw" data-dir="sw"></div>
                <div class="resize-handle w"  data-dir="w"></div>
            </div>
        </div>
        <div class="no-image-msg" id="noImageMsg">Select a product to begin editing</div>
    </div>

    <!-- RIGHT: Controls -->
    <div class="dae-controls">
        <div class="dae-section">
            <h3>Product</h3>
            <select class="dae-select" id="productSelect" onchange="loadProduct()">
                <option value="">— Select a product —</option>
                <?php foreach ($products as $p): ?>
                <option value="<?= $p['id'] ?>"
                    data-front="<?= htmlspecialchars($p['image_path'] ?? '') ?>"
                    data-back="<?= htmlspecialchars($p['back_image_path'] ?? '') ?>"
                    data-lsleeve="<?= htmlspecialchars($p['left_sleeve_image_path'] ?? '') ?>"
                    data-rsleeve="<?= htmlspecialchars($p['right_sleeve_image_path'] ?? '') ?>"
                    data-da='<?= json_encode([
                        "front" => ["x"=>$p['da_front_x'],"y"=>$p['da_front_y'],"w"=>$p['da_front_w'],"h"=>$p['da_front_h']],
                        "back"  => ["x"=>$p['da_back_x'], "y"=>$p['da_back_y'], "w"=>$p['da_back_w'], "h"=>$p['da_back_h']],
                        "lsleeve"=>["x"=>$p['da_lsleeve_x'],"y"=>$p['da_lsleeve_y'],"w"=>$p['da_lsleeve_w'],"h"=>$p['da_lsleeve_h']],
                        "rsleeve"=>["x"=>$p['da_rsleeve_x'],"y"=>$p['da_rsleeve_y'],"w"=>$p['da_rsleeve_w'],"h"=>$p['da_rsleeve_h']],
                    ]) ?>'>
                    <?= htmlspecialchars($p['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="dae-section">
            <h3>Position &amp; Size <span style="font-weight:400;text-transform:none;color:#aaa;font-size:11px;">(% of mockup)</span></h3>
            <div class="coord-grid">
                <div class="coord-field">
                    <label>Left (X)</label>
                    <input type="number" id="inX" min="0" max="100" step="0.1" oninput="applyFromInputs()">
                </div>
                <div class="coord-field">
                    <label>Top (Y)</label>
                    <input type="number" id="inY" min="0" max="100" step="0.1" oninput="applyFromInputs()">
                </div>
                <div class="coord-field">
                    <label>Width (W)</label>
                    <input type="number" id="inW" min="1" max="100" step="0.1" oninput="applyFromInputs()">
                </div>
                <div class="coord-field">
                    <label>Height (H)</label>
                    <input type="number" id="inH" min="1" max="100" step="0.1" oninput="applyFromInputs()">
                </div>
            </div>
            <p class="coord-hint">Values are percentages of the mockup image size (0–100).</p>
        </div>

        <div class="dae-section">
            <h3>Copy to other views</h3>
            <div class="dae-copy-row">
                <button class="btn-copy" onclick="copyToView('front')">→ Front</button>
                <button class="btn-copy" onclick="copyToView('back')">→ Back</button>
                <button class="btn-copy" onclick="copyToView('lsleeve')">→ L.Sleeve</button>
                <button class="btn-copy" onclick="copyToView('rsleeve')">→ R.Sleeve</button>
            </div>
        </div>

        <button class="btn-save" onclick="saveAll()">💾 Save Design Area</button>
    </div>
</div>

<script>
// PHP-generated data — must be inline
const products = <?= json_encode($products) ?>;
</script>
<script src="<?= htmlspecialchars(Asset::url('/js/design_area_editor.js')) ?>" defer></script>
</body>
</html>
