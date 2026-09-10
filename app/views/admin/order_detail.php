<?php $title = 'Order #' . $order['id']; ?>
<?php
// CSS-filter approximation of tinting a white shirt to the order's color.
// (Same algorithm as cart.php so the admin sees what the customer saw.)
if (!function_exists('adminOrderHexToHSL')) {
    function adminOrderHexToHSL($hex) {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        if (strlen($hex) !== 6) return ['h'=>0,'s'=>0,'l'=>100];
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $h = 0; $s = 0;
        if ($max !== $min) {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            switch ($max) {
                case $r: $h = (($g - $b) / $d + ($g < $b ? 6 : 0)) / 6; break;
                case $g: $h = (($b - $r) / $d + 2) / 6; break;
                case $b: $h = (($r - $g) / $d + 4) / 6; break;
            }
        }
        return ['h' => $h * 360, 's' => $s * 100, 'l' => $l * 100];
    }
}
if (!function_exists('adminOrderProductColorFilter')) {
    // Delegated to Tint so the production view shows the same shade the
    // customer saw when they ordered — this copy had drifted from the studio's
    // and never applied the solved overrides for deep reds.
    function adminOrderProductColorFilter($hex) {
        if (!$hex) return '';
        return Tint::filterFor($hex);
    }
}
?>
<?php require __DIR__ . '/../layouts/admin_header.php'; ?>


<a href="/admin/orders" class="order-back">← Back to Orders</a>

<div class="admin-header">
    <h1>Order #<?= $order['id'] ?></h1>
    <span class="status status-<?= htmlspecialchars($order['status']) ?>"><?= htmlspecialchars($order['status']) ?></span>
</div>

<!-- Summary Cards -->
<div class="order-grid">
    <!-- Customer Info -->
    <div class="info-card">
        <h3>👤 Customer</h3>
        <div class="info-row"><span class="label">Username</span><span class="value"><?= htmlspecialchars($order['username'] ?? 'Guest') ?></span></div>
        <div class="info-row"><span class="label">Email</span><span class="value"><?= htmlspecialchars($order['email'] ?? '—') ?></span></div>
        <div class="info-row"><span class="label">Phone</span><span class="value"><?= htmlspecialchars($order['phone'] ?? '—') ?></span></div>
        <div class="info-row"><span class="label">Order Date</span><span class="value"><?= htmlspecialchars($order['created_at']) ?></span></div>
        <?php if (!empty($order['tracking_token'])): ?>
        <div class="info-row"><span class="label">Tracking Number</span><span class="value" style="letter-spacing:0.08em;"><?= htmlspecialchars($order['tracking_token']) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($order['shipping_address'])): ?>
        <div class="info-row"><span class="label">Shipping Address</span><span class="value"><?= htmlspecialchars($order['shipping_address']) ?></span></div>
        <?php endif; ?>
    </div>

    <!-- Payment Info -->
    <div class="info-card">
        <h3>💳 Payment</h3>
        <?php if ($payment): ?>
        <?php
            $methodLabels = [
                'card' => '💳 Credit/Debit Card',
                'revolut' => '🔵 Revolut',
                'paypal' => '🅿️ PayPal',
                'gcash' => '💚 GCash',
                'applepay' => '🍎 Apple Pay',
                'googlepay' => '🔷 Google Pay'
            ];
            $brandIcons = ['visa'=>'💙 Visa','mastercard'=>'🟠 Mastercard','amex'=>'🔵 Amex','discover'=>'🟡 Discover'];
            $pm = $payment['payment_method'] ?? 'card';
        ?>
        <div class="info-row">
            <span class="label">Payment Method</span>
            <span class="value"><?= $methodLabels[$pm] ?? ucfirst($pm) ?></span>
        </div>
        <?php if ($pm === 'card'): ?>
        <div class="info-row">
            <span class="label">Card Brand</span>
            <span class="value">
                <?= $brandIcons[$payment['card_brand']] ?? ucfirst($payment['card_brand'] ?? 'Card') ?>
            </span>
        </div>
        <div class="info-row"><span class="label">Card Holder</span><span class="value"><?= htmlspecialchars($payment['card_holder'] ?? '—') ?></span></div>
        <div class="info-row"><span class="label">Card Number</span><span class="value">•••• •••• •••• <?= htmlspecialchars($payment['card_last4'] ?? '—') ?></span></div>
        <div class="info-row"><span class="label">Expires</span><span class="value"><?= $payment['card_exp_month'] ?>/<?= $payment['card_exp_year'] ?></span></div>
        <div class="info-row"><span class="label">Billing ZIP</span><span class="value"><?= htmlspecialchars($payment['billing_zip'] ?? '—') ?></span></div>
        <?php endif; ?>
        <div class="info-row"><span class="label">Amount Charged</span><span class="value" style="color:#28a745; font-size:1.1rem;">€<?= number_format($payment['amount'], 2) ?></span></div>
        <div class="info-row"><span class="label">Payment Status</span><span class="value"><span class="status status-delivered"><?= htmlspecialchars($payment['status']) ?></span></span></div>
        <?php else: ?>
        <p style="color:#999;">No payment information recorded.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Order Totals + Status Update -->
<div class="order-grid">
    <div class="info-card">
        <h3>📦 Order Summary</h3>
        <div class="info-row"><span class="label">Total Items</span><span class="value"><?= (int)$order['total_products'] ?> pcs</span></div>
        <div class="info-row"><span class="label">Unique Products</span><span class="value"><?= count($orderItems) ?></span></div>
        <?php
            $customCount = 0;
            $premadeCount = 0;
            foreach ($orderItems as $oi) {
                if (!empty($oi['is_custom_design'])) $customCount++;
                else $premadeCount++;
            }
        ?>
        <div class="info-row"><span class="label">Custom Designs</span><span class="value"><?= $customCount ?></span></div>
        <div class="info-row"><span class="label">Standard Items</span><span class="value"><?= $premadeCount ?></span></div>
        <div class="info-row" style="border-top:1px solid #eee; padding-top:10px; margin-top:6px;">
            <span class="label" style="font-size:1.05rem;">Grand Total</span>
            <span class="value" style="font-size:1.3rem; color:#28a745;">€<?= number_format($order['total_price'], 2) ?></span>
        </div>
    </div>
    <div class="info-card">
        <h3>🔄 Update Status</h3>
        <?php $statusLabels = ['pending'=>'Pending','processing'=>'Processing','in-transit'=>'In Transit','delivered'=>'Delivered','cancelled'=>'Cancelled']; ?>
        <p style="margin-bottom:8px; color:#666; font-size:0.9rem;">Current: <span class="status status-<?= htmlspecialchars($order['status']) ?>"><?= htmlspecialchars($statusLabels[$order['status']] ?? ucfirst($order['status'])) ?></span></p>
        <form method="POST" action="/admin/orders/status/<?= $order['id'] ?>" class="status-form">
            <?= Csrf::field() ?>
            <select name="status">
                <?php foreach (['pending','processing','in-transit','delivered','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= $statusLabels[$s] ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Update</button>
        </form>
        <?php if (!empty($order['notes'])): ?>
        <div style="margin-top:14px;">
            <strong style="font-size:0.85rem; color:#888;">Notes:</strong>
            <p style="font-size:0.9rem; color:#333; margin-top:4px;"><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Order Items -->
<h2 style="margin:28px 0 16px; font-size:1.3rem;">🛍️ Order Items (<?= count($orderItems) ?>)</h2>

<?php
$viewLabels = [
    'front' => 'Front View',
    'back' => 'Back View',
    'left-sleeve' => 'Left Sleeve',
    'right-sleeve' => 'Right Sleeve'
];
?>

<?php foreach ($orderItems as $idx => $item):
    $lineTotal = ((float)$item['unit_price'] + (float)($item['custom_design_fee'] ?? 0)) * (int)$item['quantity'];
    $hasPreviews = !empty($item['parsed_previews']);
?>
<div class="order-item-card">
    <div class="order-item-header">
        <div>
            <div class="item-title"><?= htmlspecialchars($item['product_name'] ?? 'Product #' . $item['product_id']) ?></div>
            <div class="item-subtitle">
                Item #<?= $idx + 1 ?>
                <?php if (!empty($item['is_custom_design'])): ?> · <span style="color:#007bff;">✨ Custom Design</span><?php endif; ?>
                <?php if (!empty($item['design_id'])): ?> · <span style="color:#999; font-size:0.82rem;">Design #<?= (int)$item['design_id'] ?></span><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="item-details-grid">
        <div class="item-detail">
            <span class="dt">Size</span>
            <span class="dd"><?= htmlspecialchars($item['size_name'] ?? '—') ?></span>
        </div>
        <div class="item-detail">
            <span class="dt">Color</span>
            <span class="dd">
                <?php if (!empty($item['color_hex'])): ?>
                <span class="color-chip" style="background:<?= htmlspecialchars($item['color_hex']) ?>"></span>
                <?php endif; ?>
                <?= htmlspecialchars($item['color_name'] ?? '—') ?>
                <?php if (!empty($item['color_hex'])): ?>
                <span style="color:#aaa; font-size:0.8rem;">(<?= htmlspecialchars($item['color_hex']) ?>)</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="item-detail">
            <span class="dt">Quantity</span>
            <span class="dd"><?= (int)$item['quantity'] ?></span>
        </div>
        <div class="item-detail">
            <span class="dt">Unit Price</span>
            <span class="dd">€<?= number_format($item['unit_price'], 2) ?></span>
        </div>
        <div class="item-detail">
            <span class="dt">Design Fee</span>
            <span class="dd"><?= (float)$item['custom_design_fee'] > 0 ? '€' . number_format($item['custom_design_fee'], 2) : '—' ?></span>
        </div>
        <div class="item-detail">
            <span class="dt">Line Total</span>
            <span class="dd" style="color:#28a745; font-size:1.05rem;">€<?= number_format($lineTotal, 2) ?></span>
        </div>
    </div>

    <!-- Design Preview Images -->
    <?php
        $isPremade = !empty($item['premade_design_id']) || !empty($item['premade_design_image']);
    ?>
    <?php
        // Only render composite previews (front/back/sleeves). Skip the
        // standalone design tiles (front_design, back_design, …) that newer
        // saves include — admins want the on-shirt mockups, and showing
        // both makes some orders look like they have "extras".
        $compositePreviews = [];
        if (!empty($item['parsed_previews']) && is_array($item['parsed_previews'])) {
            foreach ($item['parsed_previews'] as $view => $previewPath) {
                if (preg_match('/(_design|-design)$/', $view)) continue;
                $compositePreviews[$view] = $previewPath;
            }
        }
        $hasComposite = !empty($compositePreviews);
    ?>
    <?php if ($hasComposite): ?>
        <div class="preview-section">
            <h4>🎨 <?= !empty($item['is_custom_design']) ? 'Design Previews' : 'Premade Design Previews' ?> — click to enlarge</h4>
            <div class="preview-grid">
                <?php foreach ($compositePreviews as $view => $previewPath):
                    $previewUrl = '/' . ltrim($previewPath, '/');
                    $label = $viewLabels[$view] ?? ucfirst(str_replace('-', ' ', $view));
                ?>
                <div class="preview-card" onclick="openPreviewModal('<?= htmlspecialchars($previewUrl) ?>', '<?= htmlspecialchars($label) ?> — <?= htmlspecialchars($item['product_name'] ?? '') ?>')">
                    <img src="<?= htmlspecialchars($previewUrl) ?>" alt="<?= htmlspecialchars($label) ?>" onerror="this.parentElement.style.display='none'">
                    <div class="preview-label"><?= htmlspecialchars($label) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php elseif ($isPremade): ?>
        <?php
            // Build a composite preview live: product image tinted to the
            // ordered color, with the premade design overlaid.
            $productImg = $item['product_image'] ?? '';
            if ($productImg && $productImg[0] !== '/') $productImg = '/' . ltrim($productImg, '/');
            $designImg = $item['premade_design_image'] ?? '';
            if ($designImg && $designImg[0] !== '/') $designImg = '/' . ltrim($designImg, '/');
            $colorHex   = $item['color_hex'] ?? '';
            $colorFilter = adminOrderProductColorFilter($colorHex);
            $posX  = (float)($item['premade_pos_x'] ?? 0);
            $posY  = (float)($item['premade_pos_y'] ?? 0);
            $size  = (float)($item['premade_pos_size'] ?? 55);
            // Same projection cart.php uses to map stored coords to %.
            $overlayLeft = 50 + $posX * 0.25;
            $overlayTop  = 55 + $posY * 0.375;
            $overlayW    = $size * 0.5;
        ?>
        <div class="preview-section">
            <h4>🎨 Premade Design<?= !empty($item['premade_design_name']) ? ' — ' . htmlspecialchars($item['premade_design_name']) : '' ?></h4>
            <div class="preview-grid">
                <div class="preview-card" style="cursor:default;">
                    <div style="position:relative; aspect-ratio:1; background:#fff; overflow:hidden; display:flex; align-items:center; justify-content:center;">
                        <?php if ($productImg): ?>
                        <img src="<?= htmlspecialchars($productImg) ?>"
                             alt="<?= htmlspecialchars($item['product_name'] ?? 'Product') ?>"
                             style="position:absolute; inset:0; width:100%; height:100%; object-fit:contain; filter: <?= htmlspecialchars($colorFilter) ?>;"
                             onerror="this.style.display='none'">
                        <?php endif; ?>
                        <?php if ($designImg): ?>
                        <img src="<?= htmlspecialchars($designImg) ?>"
                             alt="<?= htmlspecialchars($item['premade_design_name'] ?? 'Design') ?>"
                             style="position:absolute; left:<?= $overlayLeft ?>%; top:<?= $overlayTop ?>%; width:<?= $overlayW ?>%; transform:translate(-50%,-50%); pointer-events:none;"
                             onerror="this.style.display='none'">
                        <?php endif; ?>
                    </div>
                    <div class="preview-label">Front (with design)</div>
                </div>
                <?php if ($designImg): ?>
                <div class="preview-card" onclick="openPreviewModal('<?= htmlspecialchars($designImg) ?>', 'Premade Design — <?= htmlspecialchars($item['premade_design_name'] ?? '') ?>')">
                    <img src="<?= htmlspecialchars($designImg) ?>"
                         alt="<?= htmlspecialchars($item['premade_design_name'] ?? 'Design') ?>"
                         style="background:#fafafa; object-fit:contain;"
                         onerror="this.parentElement.style.display='none'">
                    <div class="preview-label">Design only</div>
                </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($item['premade_design_id'])): ?>
            <p style="font-size:0.78rem; color:#888; margin:10px 22px 4px;">Premade Design ID: #<?= (int)$item['premade_design_id'] ?></p>
            <?php endif; ?>
        </div>
    <?php elseif (!empty($item['is_custom_design'])): ?>
        <div class="no-preview-msg">
            ⚠️ No design preview images available for this item. Previews are generated when the customer saves their design in the editor.
            <?php if (!empty($item['design_id'])): ?>
            <br><small>Design ID: #<?= (int)$item['design_id'] ?></small>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="padding:10px 22px 18px; color:#aaa; font-size:0.9rem;">Standard product — no custom design.</div>
    <?php endif; ?>

    <?php
        // ── Production details ────────────────────────────────────────────
        // Everything needed to actually print this item: the customer's own
        // artwork files (downloadable at full resolution) and the exact
        // specification of any text they added. Both were already being
        // fetched by the controller but never shown, so there was no way to
        // get the source files or reproduce the type.
        $itemUploads = $item['uploads'] ?? [];
        $itemTexts   = $item['texts'] ?? [];
        // The two tables spell sleeve placements differently
        // (uploads use 'left-sleeve', texts use 'left_sleeve').
        $placementLabel = function ($p) {
            $p = str_replace('_', '-', (string)$p);
            return [
                'front' => 'Front', 'back' => 'Back',
                'left-sleeve' => 'Left sleeve', 'right-sleeve' => 'Right sleeve',
            ][$p] ?? ucfirst($p);
        };
    ?>
    <?php if ($itemUploads || $itemTexts): ?>
    <div class="production-section">
        <h4>Production files &amp; specification</h4>

        <?php if ($itemUploads): ?>
        <div class="prod-block">
            <div class="prod-block-title">Customer artwork <span class="prod-count"><?= count($itemUploads) ?></span></div>
            <div class="prod-uploads">
                <?php foreach ($itemUploads as $up):
                    $raw = (string)($up['stored_file_path'] ?? '');
                    $web = '/' . ltrim(preg_replace('#^public/#', '', $raw), '/');
                    // app/views/admin -> three levels up reaches the project root.
                    $abs = __DIR__ . '/../../../public/' . ltrim(preg_replace('#^public/#', '', $raw), '/');
                    $exists = is_file($abs);
                    $bytes  = $exists ? filesize($abs) : 0;
                    $dim    = $exists ? @getimagesize($abs) : false;
                    $orig   = $up['original_filename'] ?: basename($raw);
                ?>
                <div class="prod-upload">
                    <div class="prod-thumb">
                        <?php if ($exists): ?>
                            <img src="<?= htmlspecialchars($web) ?>" alt="" loading="lazy"
                                 onclick="openPreviewModal('<?= htmlspecialchars($web) ?>', '<?= htmlspecialchars($orig) ?>')">
                        <?php else: ?>
                            <span class="prod-missing">file missing</span>
                        <?php endif; ?>
                    </div>
                    <div class="prod-meta">
                        <div class="prod-filename" title="<?= htmlspecialchars($orig) ?>"><?= htmlspecialchars($orig) ?></div>
                        <dl class="prod-specs">
                            <dt>Placement</dt><dd><?= htmlspecialchars($placementLabel($up['placement'] ?? 'front')) ?></dd>
                            <?php if ($dim): ?>
                            <dt>Source size</dt><dd><?= (int)$dim[0] ?> × <?= (int)$dim[1] ?> px</dd>
                            <?php endif; ?>
                            <?php if ($bytes): ?>
                            <dt>File</dt><dd><?= number_format($bytes / 1024, 0) ?> KB</dd>
                            <?php endif; ?>
                            <dt>Placed at</dt><dd><?= (int)($up['position_x'] ?? 0) ?>, <?= (int)($up['position_y'] ?? 0) ?></dd>
                            <dt>Printed size</dt><dd><?= (int)($up['width'] ?? 0) ?> × <?= (int)($up['height'] ?? 0) ?></dd>
                        </dl>
                        <?php if ($exists): ?>
                        <a class="prod-download" href="<?= htmlspecialchars($web) ?>"
                           download="<?= htmlspecialchars($orig) ?>">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Download original
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($itemTexts): ?>
        <div class="prod-block">
            <div class="prod-block-title">Text to print <span class="prod-count"><?= count($itemTexts) ?></span></div>
            <table class="prod-text-table">
                <thead>
                    <tr><th>Text</th><th>Font</th><th>Size</th><th>Colour</th><th>Style</th><th>Placement</th><th>Position</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($itemTexts as $tx):
                    $styles = [];
                    if (!empty($tx['is_bold']))      $styles[] = 'Bold';
                    if (!empty($tx['is_italic']))    $styles[] = 'Italic';
                    if (!empty($tx['is_underline'])) $styles[] = 'Underline';
                    $spec = sprintf(
                        "%s | %s %spx | %s | %s | %s | x:%d y:%d",
                        $tx['text_content'] ?? '',
                        $tx['font_family'] ?? 'Arial',
                        (int)($tx['font_size'] ?? 24),
                        $tx['text_color'] ?? '#000000',
                        $styles ? implode('+', $styles) : 'Regular',
                        $placementLabel($tx['placement'] ?? 'front'),
                        (int)($tx['position_x'] ?? 0), (int)($tx['position_y'] ?? 0)
                    );
                ?>
                    <tr>
                        <td class="prod-text-value"
                            style="font-family:<?= htmlspecialchars($tx['font_family'] ?? 'Arial') ?>;
                                   color:<?= htmlspecialchars($tx['text_color'] ?? '#000') ?>;
                                   <?= !empty($tx['is_bold']) ? 'font-weight:700;' : '' ?>
                                   <?= !empty($tx['is_italic']) ? 'font-style:italic;' : '' ?>
                                   <?= !empty($tx['is_underline']) ? 'text-decoration:underline;' : '' ?>"><?= htmlspecialchars($tx['text_content'] ?? '') ?></td>
                        <td><?= htmlspecialchars($tx['font_family'] ?? 'Arial') ?></td>
                        <td><?= (int)($tx['font_size'] ?? 24) ?> px</td>
                        <td>
                            <span class="color-chip" style="background:<?= htmlspecialchars($tx['text_color'] ?? '#000') ?>"></span>
                            <?= htmlspecialchars($tx['text_color'] ?? '#000000') ?>
                        </td>
                        <td><?= $styles ? htmlspecialchars(implode(', ', $styles)) : '—' ?></td>
                        <td><?= htmlspecialchars($placementLabel($tx['placement'] ?? 'front')) ?></td>
                        <td><?= (int)($tx['position_x'] ?? 0) ?>, <?= (int)($tx['position_y'] ?? 0) ?></td>
                        <td><button type="button" class="prod-copy" data-spec="<?= htmlspecialchars($spec, ENT_QUOTES, 'UTF-8') ?>">Copy</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<!-- Fullscreen Preview Modal -->
<div class="preview-modal-overlay" id="previewModal" onclick="closePreviewModal(event)">
    <div class="preview-modal-content" onclick="event.stopPropagation()">
        <button class="preview-modal-close" onclick="closePreviewModal()">&times;</button>
        <img id="previewModalImg" src="" alt="Design Preview">
        <div class="preview-modal-label" id="previewModalLabel"></div>
    </div>
</div>

<script src="<?= htmlspecialchars(Asset::url('/js/admin_order_detail.js')) ?>" defer></script>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
