<?php
/**
 * One saved-design card.
 *
 * Expects $design (a row from custom_designs joined to its product, with
 * 'uploads' and 'texts' already loaded).
 *
 * Extracted so the Overview and My Designs tabs render identical cards — the
 * positioning maths below is fiddly enough that a second copy would drift.
 */

$colorHex = $design['color_hex'] ?? '#000000';
if (empty($colorHex)) {
    $colorHex = '#000000';
}

// One implementation, shared with the browser (public/js/color-tint.js) so a
// saved-design card shows the same shade as the studio that produced it. This
// file used to carry its own copy of the maths, which had drifted from the
// others and never applied the solved overrides for deep reds.
$productFilter = Tint::filterFor($colorHex);

// Element coordinates are in EDITOR pixels, relative to the print area as it was
// sized when the design was saved. That size is recorded in elements_json._meta
// — use it, because the estimate below is only a fallback for designs saved
// before _meta existed. Getting this wrong scales every element (411x548 read as
// 225x300 misplaces and shrinks everything by ~1.8x).
$metaDAW = null; $metaDAH = null;
if (!empty($design['elements_json'])) {
    $ej = json_decode($design['elements_json'], true);
    if (isset($ej['_meta']['editorDAWidth'])  && $ej['_meta']['editorDAWidth']  > 0) $metaDAW = (float)$ej['_meta']['editorDAWidth'];
    if (isset($ej['_meta']['editorDAHeight']) && $ej['_meta']['editorDAHeight'] > 0) $metaDAH = (float)$ej['_meta']['editorDAHeight'];
}

// Fallback editor design-area dimensions from the product image's natural size.
// In the editor: #designArea = 45% wide x 60% tall of the rendered mockup image.
$phpEditorDAW = 225;
$phpEditorDAH = 300;
if (!empty($design['product_image'])) {
    $imgPath = ltrim($design['product_image'], '/');
    if (strpos($imgPath, 'public/') === 0) $imgPath = substr($imgPath, 7);
    // app/views/customer -> three levels up reaches the project root.
    $fullImgPath = __DIR__ . '/../../../public/' . $imgPath;
    $dim = @getimagesize($fullImgPath);
    if ($dim && $dim[0] > 0 && $dim[1] > 0) {
        $phpEditorDAH = round(300 * ($dim[1] / $dim[0]), 2);
    }
}

// Compose the card live: current product image + the design's own front
// elements, positioned as percentages of the print area. The stored composite
// previews are NOT used here — they were rendered against the previous product
// artwork, so they showed a garment that no longer matches the one you get when
// you open the design.
$daX = (float)($design['da_front_x'] ?? 27.5);
$daY = (float)($design['da_front_y'] ?? 25);
$daW = (float)($design['da_front_w'] ?? 45);
$daH = (float)($design['da_front_h'] ?? 60);
$frontUploads = array_values(array_filter($design['uploads'] ?? [], function ($u) {
    return ($u['view_placement'] ?? 'front') === 'front' && !empty($u['stored_file_path']);
}));
$frontTexts = array_values(array_filter($design['texts'] ?? [], function ($t) {
    return ($t['view_placement'] ?? 'front') === 'front' && !empty($t['text_content']);
}));
// Scale against the print-area size recorded WITH the design, never the current
// estimate. The product artwork was replaced (different pixel sizes and
// framing), so anything derived from today's image would misplace designs saved
// against the old art.
$refW = $metaDAW ?: $phpEditorDAW;
$refH = $metaDAH ?: $phpEditorDAH;
$pct = function ($v, $of) { return $of > 0 ? ($v / $of) * 100 : 0; };
?>
<div class="design-card" data-design-id="<?= $design['id'] ?>">
    <div class="design-card-image" style="position:relative; background:#fff; overflow:hidden;">
        <?php if (!empty($design['product_image'])): ?>
            <img src="/<?= htmlspecialchars(ltrim($design['product_image'], '/')) ?>"
                 alt="<?= htmlspecialchars($design['product_name'] ?? '') ?>" loading="lazy"
                 style="width:100%; height:100%; object-fit:contain; filter:<?= $productFilter ?>;">
            <?php if ($frontUploads || $frontTexts): ?>
            <div style="position:absolute; left:<?= $daX ?>%; top:<?= $daY ?>%; width:<?= $daW ?>%; height:<?= $daH ?>%; overflow:hidden; pointer-events:none; container-type:inline-size;">
                <?php foreach ($frontUploads as $u):
                    $src = ltrim((string)$u['stored_file_path'], '/');
                    if (strpos($src, 'public/') === 0) $src = substr($src, 7);
                ?>
                <img src="/<?= htmlspecialchars($src) ?>" alt="" loading="lazy"
                     style="position:absolute;
                            left:<?= round($pct((float)($u['position_x'] ?? 0), $refW), 3) ?>%;
                            top:<?= round($pct((float)($u['position_y'] ?? 0), $refH), 3) ?>%;
                            width:<?= round($pct((float)($u['width'] ?? $phpEditorDAW), $refW), 3) ?>%;
                            height:<?= round($pct((float)($u['height'] ?? $phpEditorDAH), $refH), 3) ?>%;
                            object-fit:contain;">
                <?php endforeach; ?>
                <?php foreach ($frontTexts as $tx): ?>
                <div style="position:absolute;
                            left:<?= round($pct((float)($tx['position_x'] ?? 0), $refW), 3) ?>%;
                            top:<?= round($pct((float)($tx['position_y'] ?? 0), $refH), 3) ?>%;
                            font-size:<?= round($pct((float)($tx['font_size'] ?? 24), $refW), 3) ?>cqw;
                            color:<?= htmlspecialchars($tx['text_color'] ?? '#000') ?>;
                            font-family:<?= htmlspecialchars($tx['font_family'] ?? 'inherit') ?>;
                            white-space:nowrap; line-height:1;"><?= htmlspecialchars($tx['text_content']) ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <span style="color:#aaa;"><?= t('account.no_preview') ?></span>
        <?php endif; ?>
    </div>
    <div class="design-card-body">
        <div class="design-card-title"><?= htmlspecialchars($design['name']) ?></div>
        <div class="design-card-product"><?= htmlspecialchars($design['product_name'] ?? t('custom.title', false)) ?></div>
        <div class="design-card-date"><?= I18n::t('account.created', ['date' => date('M j, Y', strtotime($design['created_at']))]) ?></div>
        <div class="design-card-actions">
            <button class="btn btn-primary" onclick="addDesignToCart(<?= htmlspecialchars(json_encode([
                'id' => $design['id'],
                'productId' => $design['product_id'],
                'productName' => $design['product_name'] ?? 'Custom Product',
                'designName' => $design['name'],
                'basePrice' => $design['base_price'] ?? 0,
                'productImage' => '/' . ltrim($design['product_image'] ?? '', '/'),
                'colorHex' => $design['color_hex'] ?? '#000000',
                'elementsJson' => $design['elements_json'] ?? '{}',
                'uploads' => $design['uploads'] ?? [],
                'texts' => $design['texts'] ?? [],
                // Deliberately null: the stored composites were rendered against
                // the previous product artwork, so the modal would show a
                // different garment from the card beside it. Passing null makes
                // the modal compose from the live product image and the design's
                // own coordinates, exactly as the card above does.
                'frontPreviewPath' => null,
                'frontDesignPreviewPath' => null,
                'editorDAWidth'  => $phpEditorDAW,
                'editorDAHeight' => $phpEditorDAH,
            ]), ENT_QUOTES, 'UTF-8') ?>)">
                <?= t('account.add_to_cart') ?>
            </button>
            <a href="/shop/custom?load=<?= $design['id'] ?>" class="btn btn-outline"><?= t('account.edit') ?></a>
            <button class="btn btn-danger" onclick="deleteDesign(<?= $design['id'] ?>, '<?= htmlspecialchars(addslashes($design['name'])) ?>')"><?= t('account.delete') ?></button>
        </div>
    </div>
</div>
